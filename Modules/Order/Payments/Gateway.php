<?php

namespace Modules\Order\Payments;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Modules\Order\Models\PaymentAttempt;

/**
 * Shared plumbing of the gateway drivers.
 */
abstract class Gateway implements PaymentGateway
{
    protected function http(): PendingRequest
    {
        return Http::acceptJson()
            ->timeout((int) config('payments.timeout'))
            ->withoutRedirecting()
            ->withUserAgent('jocko-shop');
    }

    /**
     * The attempt's amount in rials, which is what most gateways want.
     */
    protected function rials(PaymentAttempt $attempt): int
    {
        return (int) round((float) $attempt->amount * ($attempt->currency === 'IRT' ? 10 : 1));
    }

    /**
     * @return array<string, mixed>
     *
     * @throws GatewayException
     */
    protected function json(Response $response): array
    {
        $data = $response->json();

        if (! is_array($data) || ($response->serverError())) {
            throw new GatewayException($this->label().' answered with an unexpected response (HTTP '.$response->status().').');
        }

        return $data;
    }

    protected function secret(string $key): string
    {
        $value = config($key);

        return is_string($value) ? trim($value) : '';
    }
}
