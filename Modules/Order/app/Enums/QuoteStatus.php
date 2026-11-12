<?php

namespace Modules\Order\Enums;

enum QuoteStatus: string
{
    case DRAFT = 'draft';
    case SENT = 'sent';
    case ACCEPTED = 'accepted';
    case REJECTED = 'rejected';
    case CONVERTED = 'converted';

    public static function getDefault(): self
    {
        return self::DRAFT;
    }

    /**
     * Translated display label.
     */
    public function label(): string
    {
        return (string) __("order::strings.quote_statuses.{$this->value}");
    }

    /**
     * Badge colour, so the state is readable at a glance.
     */
    public function color(): string
    {
        return match ($this) {
            self::DRAFT => 'zinc',
            self::SENT => 'blue',
            self::ACCEPTED => 'green',
            self::REJECTED => 'red',
            self::CONVERTED => 'green',
        };
    }

    /**
     * Whether the customer has not answered yet, so the quote can still lapse.
     */
    public function isOpen(): bool
    {
        return $this === self::DRAFT || $this === self::SENT;
    }
}
