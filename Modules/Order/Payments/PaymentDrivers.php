<?php

namespace Modules\Order\Payments;

use Illuminate\Container\Attributes\Singleton;

/**
 * The gateways and terminals this shop knows. Add a driver here to offer it.
 */
#[Singleton]
class PaymentDrivers
{
    /** @var list<class-string<PaymentGateway>> */
    private const GATEWAYS = [ZarinpalGateway::class, NextPayGateway::class, ZibalGateway::class];

    /** @var list<class-string<PosTerminal>> */
    private const TERMINALS = [ManualPosTerminal::class];

    /**
     * @return array<string, PaymentGateway> every gateway, keyed by its key
     */
    public function gateways(): array
    {
        $gateways = [];

        foreach (self::GATEWAYS as $class) {
            $gateway = resolve($class);
            $gateways[$gateway->key()] = $gateway;
        }

        return $gateways;
    }

    /**
     * @return array<string, PaymentGateway> those that can be used (their credentials are set)
     */
    public function configuredGateways(): array
    {
        return array_filter($this->gateways(), fn (PaymentGateway $gateway): bool => $gateway->isConfigured());
    }

    public function gateway(string $key): ?PaymentGateway
    {
        return $this->gateways()[$key] ?? null;
    }

    /**
     * @return array<string, PosTerminal>
     */
    public function terminals(): array
    {
        $terminals = [];

        foreach (self::TERMINALS as $class) {
            $terminal = resolve($class);

            if ($terminal->isConfigured()) {
                $terminals[$terminal->key()] = $terminal;
            }
        }

        return $terminals;
    }

    public function terminal(string $key): ?PosTerminal
    {
        return $this->terminals()[$key] ?? null;
    }
}
