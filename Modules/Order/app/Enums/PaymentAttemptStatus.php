<?php

namespace Modules\Order\Enums;

enum PaymentAttemptStatus: string
{
    case PENDING = 'pending';
    case PAID = 'paid';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';
    case EXPIRED = 'expired';

    public function isFinal(): bool
    {
        return $this !== self::PENDING;
    }

    public function label(): string
    {
        return (string) __("order::strings.attempt_statuses.{$this->value}");
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'amber',
            self::PAID => 'green',
            self::FAILED => 'red',
            self::CANCELLED, self::EXPIRED => 'zinc',
        };
    }
}
