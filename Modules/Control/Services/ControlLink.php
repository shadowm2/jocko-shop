<?php

namespace Modules\Control\Services;

use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Control\Exceptions\ControlException;

/**
 * Remembers whether the last attempt to reach Jocko Control worked, so a shop
 * that is offline for a day logs one line when it drops and one when it
 * recovers instead of one per minute. Also feeds `control:status`.
 */
#[Singleton]
class ControlLink
{
    private const LAST_CONTACT = 'control.last_contact_at';

    private const LAST_ERROR = 'control.last_error';

    private const PUSH_REPORT = 'control.push_report';

    private const SNAPSHOT_DONE = 'snapshot_queued_at';

    public function succeeded(): void
    {
        $failure = $this->lastError();

        Cache::forever(self::LAST_CONTACT, now()->toIso8601String());
        Cache::forget(self::LAST_ERROR);

        if ($failure !== null) {
            Log::info('Jocko Control is reachable again.', ['was' => $failure['code'], 'since' => $failure['since']]);
        }
    }

    public function failed(ControlException $e): void
    {
        $previous = $this->lastError();
        $changed = $previous === null || $previous['code'] !== $e->errorCode;

        Cache::forever(self::LAST_ERROR, [
            'code' => $e->errorCode,
            'message' => $e->getMessage(),
            'status' => $e->status,
            'since' => $changed ? now()->toIso8601String() : $previous['since'],
            'at' => now()->toIso8601String(),
        ]);

        if (! $changed) {
            return;
        }

        $context = ['code' => $e->errorCode, 'status' => $e->status];

        $e->needsAttention()
            ? Log::error('Jocko Control refused or could not be used: '.$e->getMessage(), $context)
            : Log::warning('Jocko Control is not reachable right now: '.$e->getMessage(), $context);
    }

    /**
     * Remember how the last push went, to tell Jocko Control with the next heartbeat.
     */
    public function pushed(bool $ok, ?string $error = null): void
    {
        Cache::forever(self::PUSH_REPORT, array_filter([
            'status' => $ok ? 'success' : 'failed',
            'error' => $error === null ? null : mb_substr($error, 0, 500),
        ]));
    }

    /**
     * The outcome of the push since the last heartbeat, once.
     *
     * @return array{status: 'success'|'failed', error?: string}|null
     */
    public function pullPushReport(): ?array
    {
        $report = Cache::pull(self::PUSH_REPORT);

        /** @var array{status: 'success'|'failed', error?: string}|null */
        return is_array($report) ? $report : null;
    }

    /**
     * Kept in the database, not the cache: a `cache:clear` must not make the
     * shop queue a second full copy of its data.
     */
    public function markSnapshotQueued(): void
    {
        DB::table('control_state')->updateOrInsert(
            ['key' => self::SNAPSHOT_DONE],
            ['value' => now()->toIso8601String(), 'created_at' => now(), 'updated_at' => now()],
        );
    }

    public function snapshotQueuedAt(): ?string
    {
        $value = DB::table('control_state')->where('key', self::SNAPSHOT_DONE)->value('value');

        return is_string($value) ? $value : null;
    }

    public function lastContactAt(): ?string
    {
        $value = Cache::get(self::LAST_CONTACT);

        return is_string($value) ? $value : null;
    }

    /**
     * @return array{code: string, message: string, status: int, since: string, at: string}|null
     */
    public function lastError(): ?array
    {
        $value = Cache::get(self::LAST_ERROR);

        /** @var array{code: string, message: string, status: int, since: string, at: string}|null */
        return is_array($value) ? $value : null;
    }
}
