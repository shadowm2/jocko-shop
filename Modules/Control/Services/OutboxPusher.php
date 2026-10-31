<?php

namespace Modules\Control\Services;

use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Carbon;
use Modules\Control\Exceptions\ControlException;
use Modules\Control\Models\OutboxEvent;

/**
 * Sends pending outbox events to Jocko Control in order, oldest first.
 *
 * Delivery is at-least-once and safe to repeat: an event keeps its event_id,
 * and Jocko Control answers `already_accepted` for one it has seen. An event
 * stays pending until Jocko Control confirms it. One it refuses for good
 * (malformed, too large) is parked as rejected instead of blocking the queue.
 */
#[Singleton]
class OutboxPusher
{
    public function __construct(
        private readonly ControlClient $client,
        private readonly ControlLink $link,
        private readonly OutboxSnapshot $snapshot,
    ) {}

    /**
     * @param  int|null  $maxBatches  how many batches this run may send; the configured maximum when null
     * @return array{pushed: int, rejected: int, remaining: int}
     *
     * @throws ControlException when Jocko Control cannot be reached or refuses the batch
     */
    public function push(?int $maxBatches = null): array
    {
        $this->snapshot->ensureInitial();

        $pushed = 0;
        $rejected = 0;
        $batchSize = max(1, (int) config('control.sync.batch_size'));

        try {
            for ($batch = 0; $batch < ($maxBatches ?? (int) config('control.sync.max_batches_per_run')); $batch++) {
                $events = OutboxEvent::query()->pending()->orderBy('id')->limit($batchSize)->get();

                if ($events->isEmpty()) {
                    break;
                }

                [$accepted, $refused] = $this->send(array_values($events->all()));

                $pushed += $accepted;
                $rejected += $refused;
            }
        } catch (ControlException $e) {
            $this->link->pushed(false, $e->getMessage());

            throw $e;
        }

        $this->link->pushed($rejected === 0, $rejected === 0 ? null : "{$rejected} event(s) were refused by Jocko Control.");
        $this->prune();

        return ['pushed' => $pushed, 'rejected' => $rejected, 'remaining' => $this->pendingCount()];
    }

    public function hasPending(): bool
    {
        return OutboxEvent::query()->pending()->exists();
    }

    public function pendingCount(): int
    {
        return OutboxEvent::query()->pending()->count();
    }

    /**
     * @param  list<OutboxEvent>  $events
     * @return array{int, int} how many were confirmed, how many were refused for good
     *
     * @throws ControlException
     */
    private function send(array $events): array
    {
        try {
            $response = $this->client->pushEvents(array_map($this->wire(...), $events));
        } catch (ControlException $e) {
            OutboxEvent::query()->whereKey(array_map(fn (OutboxEvent $event): int => $event->getKey(), $events))
                ->increment('attempts', 1, ['last_error' => mb_substr($e->errorCode.': '.$e->getMessage(), 0, 500)]);

            throw $e;
        }

        $byEventId = [];

        foreach ($events as $event) {
            $byEventId[$event->event_id] = $event;
        }

        $confirmed = [];

        foreach (['accepted', 'already_accepted'] as $key) {
            foreach ((array) ($response[$key] ?? []) as $item) {
                if (isset($byEventId[$item['event_id'] ?? ''])) {
                    $confirmed[] = $byEventId[$item['event_id']]->getKey();
                }
            }
        }

        OutboxEvent::query()->whereKey($confirmed)->update(['pushed_at' => now(), 'last_error' => null]);

        $refused = 0;

        foreach ((array) ($response['rejected'] ?? []) as $item) {
            $event = $byEventId[$item['event_id'] ?? ''] ?? null;

            if ($event === null) {
                continue;
            }

            $event->update([
                'rejected_code' => mb_substr((string) ($item['code'] ?? 'rejected'), 0, 100),
                'last_error' => mb_substr((string) ($item['message'] ?? ''), 0, 500),
                'attempts' => $event->attempts + 1,
            ]);
            $refused++;
        }

        // Anything Jocko Control did not mention stays pending and is sent again.
        // Without progress the loop would spin on the same batch, so stop.
        if ($confirmed === [] && $refused === 0) {
            throw new ControlException('Jocko Control confirmed none of the submitted events.', 'invalid_response', 200);
        }

        return [count($confirmed), $refused];
    }

    /**
     * @return array<string, mixed>
     */
    private function wire(OutboxEvent $event): array
    {
        return [
            'event_id' => $event->event_id,
            'event_type' => $event->event_type,
            'aggregate_type' => $event->aggregate_type,
            'aggregate_id' => $event->aggregate_id,
            'base_version' => null,
            'payload' => $event->payload,
            'occurred_at' => Carbon::parse($event->occurred_at)->utc()->format('Y-m-d\TH:i:s.u\Z'),
        ];
    }

    /**
     * Confirmed events are only kept for a while, as a trail for support.
     */
    private function prune(): void
    {
        OutboxEvent::query()
            ->whereNotNull('pushed_at')
            ->where('pushed_at', '<', now()->subDays((int) config('control.sync.retention_days')))
            ->limit(1000)
            ->delete();
    }
}
