<?php

namespace Modules\Order\Payments;

use Illuminate\Http\Client\ConnectionException;
use Modules\Order\Models\PaymentAttempt;

/**
 * NextPay (https://nextpay.org/nx/docs). Requests are form encoded. The customer
 * comes back with `trans_id`, `order_id` and `amount` and nothing that says
 * whether it worked, so every return is verified. The amount is sent in the
 * shop's own currency unit (`currency` IRT or IRR) for both calls.
 */
class NextPayGateway extends Gateway
{
    private const BASE = 'https://nextpay.org/nx/gateway';

    public function key(): string
    {
        return 'nextpay';
    }

    public function label(): string
    {
        return 'NextPay';
    }

    public function isConfigured(): bool
    {
        return $this->secret('payments.nextpay.api_key') !== '';
    }

    public function start(PaymentAttempt $attempt, string $callbackUrl, string $description): GatewayStart
    {
        $body = $this->post('/token', [
            'api_key' => $this->secret('payments.nextpay.api_key'),
            'order_id' => $attempt->public_id,
            'amount' => (int) round((float) $attempt->amount),
            'currency' => $attempt->currency,
            'callback_uri' => $callbackUrl,
            'payer_desc' => mb_substr($description, 0, 200),
        ]);

        $transId = $body['trans_id'] ?? null;

        // -1 means "token issued, waiting for the customer".
        if (($body['code'] ?? null) !== -1 || ! is_string($transId) || $transId === '') {
            throw new GatewayException('NextPay refused the payment: code '.($body['code'] ?? '?'));
        }

        return new GatewayStart($transId, self::BASE.'/payment/'.$transId);
    }

    public function referenceFrom(array $params): ?string
    {
        return is_string($params['trans_id'] ?? null) && $params['trans_id'] !== '' ? $params['trans_id'] : null;
    }

    public function returnedUnpaid(array $params): bool
    {
        return false;
    }

    public function verify(PaymentAttempt $attempt): GatewayVerification
    {
        $body = $this->post('/verify', [
            'api_key' => $this->secret('payments.nextpay.api_key'),
            'trans_id' => (string) $attempt->reference,
            'amount' => (int) round((float) $attempt->amount),
            'currency' => $attempt->currency,
        ]);

        $code = $body['code'] ?? null;

        if ($code === 0) {
            $ref = $body['Shaparak_Ref_Id'] ?? null;
            $card = $body['card_holder'] ?? null;

            return GatewayVerification::paid(is_scalar($ref) ? (string) $ref : null, is_string($card) ? $card : null);
        }

        // -1 and -3: waiting for the customer or the bank. -37/-38: the token is not known (yet).
        if (in_array($code, [-1, -3, -37, -38], true)) {
            return GatewayVerification::notPaid('code '.$code);
        }

        // -2 rejected, -4 cancelled, and the rest: it will not be paid.
        return GatewayVerification::failed('code '.($code ?? '?'));
    }

    /**
     * @param  array<string, mixed>  $form
     * @return array<string, mixed>
     *
     * @throws GatewayException
     */
    private function post(string $path, array $form): array
    {
        try {
            $response = $this->http()->asForm()->post(self::BASE.$path, $form);
        } catch (ConnectionException $e) {
            throw new GatewayException('NextPay could not be reached.', previous: $e);
        }

        return $this->json($response);
    }
}
