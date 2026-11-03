<?php

namespace Modules\Control\Services;

use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Modules\Control\Exceptions\ControlException;
use Modules\Control\Models\OutboxEvent;
use Modules\Dashboard\Models\Media;

/**
 * Sends the preview of a picture to Jocko Control before the event that
 * announces it, so the picture is never listed there without its file.
 *
 * Repeating is safe (Jocko Control answers `already_stored`). A picture whose
 * file cannot be read or that Jocko Control refuses for good just has no
 * preview; it must never block the events behind it. Network trouble is a
 * ControlException and keeps the batch pending, like any other push.
 */
#[Singleton]
class MediaUploader
{
    /**
     * A preview Jocko Control keeps failing on (a server error, not a network
     * one) is given up after this many tries, so it cannot hold back every
     * event behind it. `control:resync media` sends it again later.
     */
    private const GIVE_UP_AFTER_ATTEMPTS = 20;

    public function __construct(
        private readonly ControlClient $client,
        private readonly MediaPreview $preview,
    ) {}

    /**
     * Cut a batch short so it needs at most `files.max_per_batch` uploads; the
     * events after the cut stay pending for the next batch, in order.
     *
     * @param  Collection<int, OutboxEvent>  $events
     * @return Collection<int, OutboxEvent>
     */
    public function limit(Collection $events): Collection
    {
        $budget = max(1, (int) config('control.files.max_per_batch'));
        $used = 0;

        return $events->takeUntil(function (OutboxEvent $event) use ($budget, &$used): bool {
            if (! $this->needsFile($event)) {
                return false;
            }

            return ++$used > $budget;
        })->values();
    }

    /**
     * @param  list<OutboxEvent>  $events
     *
     * @throws ControlException
     */
    public function upload(array $events): void
    {
        foreach ($events as $event) {
            if (! $this->needsFile($event)) {
                continue;
            }

            $id = $event->payload['id'] ?? null;
            $media = is_int($id) ? Media::query()->find($id) : null;

            if ($media === null) {
                continue;
            }

            $preview = $this->preview->make($media);

            if ($preview === null) {
                continue;
            }

            try {
                $this->client->pushFile($media->getKey(), $preview['sha256'], $preview['mime'], $preview['bytes']);
            } catch (ControlException $e) {
                if (! $this->giveUp($e, $event)) {
                    throw $e;
                }

                Log::warning("The preview of picture {$media->getKey()} was not sent ({$e->errorCode}: {$e->getMessage()}); the picture is listed without it.");
            }
        }
    }

    /**
     * Refused for good (too large, wrong type), or failing on the server's side for too long.
     */
    private function giveUp(ControlException $e, OutboxEvent $event): bool
    {
        return in_array($e->status, [413, 422], true)
            || ($e->status >= 500 && $event->attempts >= self::GIVE_UP_AFTER_ATTEMPTS);
    }

    private function needsFile(OutboxEvent $event): bool
    {
        return $event->aggregate_type === 'media' && ! str_ends_with($event->event_type, '.deleted');
    }
}
