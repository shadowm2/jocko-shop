<?php

namespace Modules\Control\Services;

use Illuminate\Container\Attributes\Singleton;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * How many records of each synchronized type this shop holds, so Jocko Control
 * can compare them with its own copy and notice a gap before a restore needs it.
 */
#[Singleton]
class RecordCounts
{
    /** Counting every table is cheap but not free: report at most this often. */
    public const EVERY_MINUTES = 30;

    private const SENT = 'control.record_counts.sent';

    public function __construct(private readonly SyncEntities $entities) {}

    /**
     * @return array<string, array{alive: int, removed: int}> entity type => records, and how many of them are soft-deleted
     */
    public function all(): array
    {
        $counts = [];

        foreach ($this->entities->models() as $class) {
            $query = $this->entities->queryWithDeleted($class);
            $total = (clone $query)->count();
            $removed = in_array(SoftDeletes::class, class_uses_recursive($class), true)
                ? (clone $query)->whereNotNull('deleted_at')->count()
                : 0;

            $counts[$this->entities->typeOf(new $class)] = ['alive' => $total - $removed, 'removed' => $removed];
        }

        return $counts;
    }

    /**
     * The counts to put in the next heartbeat, or null when it is not time or the
     * shop still has changes it has not sent (the two copies are expected to differ
     * until those are sent, so comparing now would only raise a false alarm).
     *
     * @return array<string, array{alive: int, removed: int}>|null
     */
    public function forHeartbeat(?int $pendingEvents): ?array
    {
        if ($pendingEvents !== 0 || ! Cache::add(self::SENT, true, now()->addMinutes(self::EVERY_MINUTES))) {
            return null;
        }

        try {
            return $this->all();
        } catch (Throwable) {
            Cache::forget(self::SENT);

            return null;
        }
    }
}
