<?php

namespace Modules\Control\Services;

use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Facades\Cache;
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
