<?php

namespace Modules\Order\Payments;

use Modules\Order\Models\PaymentAttempt;

/**
 * A gateway that can give a confirmed payment back to the customer's card.
 */
interface RefundableGateway extends PaymentGateway
{
    /**
     * Whether the gateway would still take a refund request for this payment
     * (gateways only allow it for a short time after the payment is confirmed).
     */
    public function canRefund(PaymentAttempt $attempt): bool;

    /**
     * Ask for the money back.
     *
     * @throws GatewayException when the gateway refuses or cannot be reached
     */
    public function refund(PaymentAttempt $attempt): void;
}
