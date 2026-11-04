<?php

namespace Modules\Order\Enums;

enum ChequeStatus: string
{
    case PENDING = 'pending';
    case CLEARED = 'cleared';
    case BOUNCED = 'bounced';

    /**
     * Translated display label.
     */
    public function label(): string
    {
        return (string) __("order::strings.cheque_statuses.{$this->value}");
    }

    /**
     * Badge colour, so the state is readable at a glance.
     */
    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'amber',
            self::CLEARED => 'green',
            self::BOUNCED => 'red',
        };
    }
}
