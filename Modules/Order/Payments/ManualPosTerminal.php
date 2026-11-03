<?php

namespace Modules\Order\Payments;

use Modules\Order\Models\PaymentAttempt;

/**
 * Any card terminal, used by hand: the amount is keyed in on the terminal and the
 * cashier confirms the approval here with the terminal's reference number.
 */
class ManualPosTerminal implements PosTerminal
{
    public function key(): string
    {
        return 'manual';
    }

    public function label(): string
    {
        return (string) __('order::strings.Manual POS');
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function isAutomatic(): bool
    {
        return false;
    }

    public function start(PaymentAttempt $attempt): void {}

    public function check(PaymentAttempt $attempt): GatewayVerification
    {
        return GatewayVerification::notPaid();
    }
}
