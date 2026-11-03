<?php

namespace Modules\Order\Payments;

use Modules\Order\Models\PaymentAttempt;

/**
 * An online payment gateway. The customer pays on the gateway's page and is sent
 * back to the shop; the shop then asks the gateway itself whether it was paid and
 * never believes what the browser says.
 */
interface PaymentGateway
{
    /** Stored on the attempt, e.g. "zarinpal". */
    public function key(): string;

    public function label(): string;

    /** Whether the credentials needed are set. */
    public function isConfigured(): bool;

    /**
     * Ask the gateway for a payment of the attempt's amount.
     *
     * @throws GatewayException
     */
    public function start(PaymentAttempt $attempt, string $callbackUrl, string $description): GatewayStart;

    /**
     * The gateway's token for the payment, as it appears in the parameters the
     * customer's browser comes back with.
     *
     * @param  array<string, mixed>  $params
     */
    public function referenceFrom(array $params): ?string;

    /**
     * Whether the return itself says the customer cancelled or failed, so there
     * is nothing to verify. Gateways that say nothing here return false.
     *
     * @param  array<string, mixed>  $params
     */
    public function returnedUnpaid(array $params): bool;

    /**
     * Ask the gateway whether the attempt was paid. Safe to repeat.
     *
     * @throws GatewayException when the gateway cannot be reached or answers nonsense
     */
    public function verify(PaymentAttempt $attempt): GatewayVerification;
}
