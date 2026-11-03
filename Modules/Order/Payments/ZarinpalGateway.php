<?php

namespace Modules\Order\Payments;

use Illuminate\Http\Client\ConnectionException;
use Modules\Order\Models\PaymentAttempt;

/**
 * Zarinpal, API v4 (https://www.zarinpal.com/docs/paymentGateway/connectToGateway.html).
 * Amounts are sent in rials (the default) both when creating and when verifying.
 * The customer comes back with `Authority` and `Status` (OK or NOK).
 */
class ZarinpalGateway extends Gateway
{
    public function key(): string
    {
        return 'zarinpal';
    }

    public function label(): string
    {
        return 'Zarinpal';
    }

    public function isConfigured(): bool
    {
        return $this->secret('payments.zarinpal.merchant_id') !== '';
    }

    private function host(): string
    {
        return config('payments.zarinpal.sandbox') ? 'https://sandbox.zarinpal.com' : 'https://payment.zarinpal.com';
    }

    public function start(PaymentAttempt $attempt, string $callbackUrl, string $description): GatewayStart
    {
        $body = $this->post('/pg/v4/payment/request.json', [
            'merchant_id' => $this->secret('payments.zarinpal.merchant_id'),
            'amount' => $this->rials($attempt),
            'description' => mb_substr($description, 0, 500),
            'callback_url' => $callbackUrl,
            'metadata' => ['order_id' => (string) $attempt->order_id],
        ]);

        $data = is_array($body['data'] ?? null) ? $body['data'] : [];
        $authority = $data['authority'] ?? null;

        if (($data['code'] ?? null) !== 100 || ! is_string($authority) || $authority === '') {
            throw new GatewayException('Zarinpal refused the payment: '.$this->errorOf($body));
        }

        return new GatewayStart($authority, $this->host().'/pg/StartPay/'.$authority);
    }

    public function referenceFrom(array $params): ?string
    {
        return is_string($params['Authority'] ?? null) && $params['Authority'] !== '' ? $params['Authority'] : null;
    }

    public function returnedUnpaid(array $params): bool
    {
        return ($params['Status'] ?? null) === 'NOK';
    }

    public function verify(PaymentAttempt $attempt): GatewayVerification
    {
        $body = $this->post('/pg/v4/payment/verify.json', [
            'merchant_id' => $this->secret('payments.zarinpal.merchant_id'),
            'amount' => $this->rials($attempt),
            'authority' => (string) $attempt->reference,
        ]);

        $data = is_array($body['data'] ?? null) ? $body['data'] : [];
        $code = $data['code'] ?? ($body['errors']['code'] ?? null);

        // 100: verified now. 101: it was verified before, which is just as paid.
        if ($code === 100 || $code === 101) {
            $ref = $data['ref_id'] ?? null;
            $pan = $data['card_pan'] ?? null;

            return GatewayVerification::paid(is_scalar($ref) ? (string) $ref : null, is_string($pan) ? $pan : null);
        }

        // -51: the payment session is not active (still being paid, or abandoned), -54/-55: not known (yet).
        if (in_array($code, [-51, -54, -55], true)) {
            return GatewayVerification::notPaid($this->errorOf($body));
        }

        return GatewayVerification::failed($this->errorOf($body));
    }

    /**
     * @param  array<string, mixed>  $json
     * @return array<string, mixed>
     *
     * @throws GatewayException
     */
    private function post(string $path, array $json): array
    {
        try {
            $response = $this->http()->asJson()->post($this->host().$path, $json);
        } catch (ConnectionException $e) {
            throw new GatewayException('Zarinpal could not be reached.', previous: $e);
        }

        return $this->json($response);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function errorOf(array $body): string
    {
        $errors = $body['errors'] ?? [];
        $code = is_array($errors) ? ($errors['code'] ?? null) : null;
        $message = is_array($errors) ? ($errors['message'] ?? null) : null;
        $data = is_array($body['data'] ?? null) ? $body['data'] : [];

        return trim('code '.($code ?? $data['code'] ?? '?').' '.(is_string($message) ? $message : ($data['message'] ?? '')));
    }
}
