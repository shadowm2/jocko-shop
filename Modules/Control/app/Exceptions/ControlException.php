<?php

namespace Modules\Control\Exceptions;

use RuntimeException;
use Throwable;

/**
 * A call to Jocko Control did not succeed. $errorCode is the server's machine
 * readable code (unauthenticated, shop_suspended, ...) or one of ours:
 * not_configured, insecure_url, unreachable, invalid_response.
 * $status is the HTTP status, or 0 when there was no response.
 *
 * Messages never contain the token.
 */
class ControlException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly int $status = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $status, $previous);
    }

    public static function notConfigured(): self
    {
        return new self('JOCKO_CONTROL_URL and JOCKO_CONTROL_TOKEN are not both set.', 'not_configured');
    }

    public static function insecureUrl(): self
    {
        return new self('JOCKO_CONTROL_URL must use https:// in production, otherwise the token would travel in clear text.', 'insecure_url');
    }

    public static function unreachable(Throwable $previous): self
    {
        return new self('Jocko Control could not be reached: '.$previous->getMessage(), 'unreachable', 0, $previous);
    }

    /**
     * True when only a person can fix it (the token was revoked or replaced),
     * as opposed to a transient or expected condition.
     */
    public function needsAttention(): bool
    {
        return in_array($this->errorCode, ['unauthenticated', 'device_revoked', 'not_configured', 'insecure_url'], true);
    }
}
