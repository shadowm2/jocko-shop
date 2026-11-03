<?php

namespace Modules\Order\Enums;

enum PaymentMethod: string
{
    case CASH = 'cash';
    case CARD = 'card';
    case TRANSFER = 'transfer';
    case CHEQUE = 'cheque';
    case POS = 'pos';
    case ONLINE = 'online';

    public static function getDefault(): self
    {
        return self::CASH;
    }

    /**
     * Translated display label.
     */
    public function label(): string
    {
        return (string) __("order::strings.payment_methods.{$this->value}");
    }
}
