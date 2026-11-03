<?php

namespace Modules\Order\Payments;

use Modules\Order\Models\PaymentAttempt;

/**
 * A card terminal that takes a payment for an order.
 *
 * Terminals of different banks speak different, mostly private, protocols, so
 * each kind is a driver. `manual` works with any terminal: the cashier takes the
 * payment on it and confirms here. A driver for a terminal that can be driven from
 * the shop (amount sent over the network, result read back) answers `isAutomatic`
 * and implements `start` and `check`.
 */
interface PosTerminal
{
    public function key(): string;

    public function label(): string;

    public function isConfigured(): bool;

    /** Whether the terminal reports the result itself, instead of the cashier confirming it. */
    public function isAutomatic(): bool;

    /**
     * Send the amount to the terminal. Does nothing for manual terminals.
     *
     * @throws GatewayException
     */
    public function start(PaymentAttempt $attempt): void;

    /**
     * What the terminal says about the attempt (automatic terminals only).
     *
     * @throws GatewayException
     */
    public function check(PaymentAttempt $attempt): GatewayVerification;
}
