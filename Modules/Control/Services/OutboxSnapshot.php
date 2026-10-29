<?php

namespace Modules\Control\Services;

use Illuminate\Container\Attributes\Singleton;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Queues the current state of every synchronized record, including deleted
 * ones. Needed once, because records created before this installation was
 * linked have no event, and as a repair if the outbox ever lost a change.
 */
#[Singleton]
class OutboxSnapshot
{
    private const CHUNK = 500;

    public function __construct(
        private readonly SyncEntities $entities,
        private readonly OutboxRecorder $outbox,
        private readonly ControlLink $link,
    ) {}

    /**
     * Queue the first full copy, once per installation.
     */
    public function ensureInitial(): void
    {
        if ($this->link->snapshotQueuedAt() !== null) {
            return;
        }

        $this->enqueue();
        $this->link->markSnapshotQueued();
    }

    /**
     * @param  list<string>  $types  only these entity types; all when empty
     * @return array<string, int> events queued per entity type
     */
    public function enqueue(array $types = []): array
    {
        $queued = [];

        foreach ($this->entities->models() as $class) {
            /** @var Model $prototype */
            $prototype = new $class;
            $type = $this->entities->typeOf($prototype);

            if ($types !== [] && ! in_array($type, $types, true)) {
                continue;
            }

            // Stamp the copy with the time before it was read: a change made
            // while it is being read is newer and still wins.
            $readAt = now();
            $queued[$type] = 0;

            $this->entities->queryWithDeleted($class)->chunkById(self::CHUNK, function ($records) use ($readAt, $type, &$queued): void {
                DB::transaction(function () use ($records, $readAt, $type, &$queued): void {
                    foreach ($records as $record) {
                        $this->outbox->record($record, 'snapshot', $readAt, reload: false);
                        $queued[$type]++;
                    }
                });
            });
        }

        return $queued;
    }
}
