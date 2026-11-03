<?php

namespace Modules\Order\Payments;

use Illuminate\Http\Client\ConnectionException;
use Modules\Order\Models\PaymentAttempt;

/**
 * Zibal. The request call follows Zibal's published sample; the verify call and
 * the `/start/{trackId}` page follow community clients and have not been checked
 * against Zibal's own reference, which could not be read (see the README). With the
 * test merchant "zibal" payments are simulated, so this can be tried without an
 * account. Amounts are rials. The customer comes back with `trackId`, `success`
 * and `status`.
 */
class ZibalGateway extends Gateway
{
    private const BASE = 'https://gateway.zibal.ir';

    public function key(): string
    {
        return 'zibal';
    }

    public function label(): string
    {
        return 'Zibal';
    }

    public function isConfigured(): bool
    {
        return $this->secret('payments.zibal.merchant') !== '';
    }

    public function start(PaymentAttempt $attempt, string $callbackUrl, string $description): GatewayStart
    {
        $body = $this->post('/v1/request', [
            'merchant' => $this->secret('payments.zibal.merchant'),
            'amount' => $this->rials($attempt),
            'callbackUrl' => $callbackUrl,
            'orderId' => $attempt->public_id,
            'description' => mb_substr($description, 0, 200),
        ]);

        $trackId = $body['trackId'] ?? null;

        if (($body['result'] ?? null) !== 100 || ! is_scalar($trackId) || (string) $trackId === '') {
            throw new GatewayException('Zibal refused the payment: '.(is_string($body['message'] ?? null) ? $body['message'] : 'code '.($body['result'] ?? '?')));
        }

        return new GatewayStart((string) $trackId, self::BASE.'/start/'.$trackId);
    }

    public function referenceFrom(array $params): ?string
    {
        $track = $params['trackId'] ?? null;

        return is_scalar($track) && (string) $track !== '' ? (string) $track : null;
    }

    public function returnedUnpaid(array $params): bool
    {
        return ($params['success'] ?? null) === '0';
    }

    public function verify(PaymentAttempt $attempt): GatewayVerification
    {
        $body = $this->post('/v1/verify', [
            'merchant' => $this->secret('payments.zibal.merchant'),
            'trackId' => (int) $attempt->reference,
        ]);

        $result = $body['result'] ?? null;

        // 100: verified now. 201: verified before, which is just as paid.
        if ($result === 100 || $result === 201) {
            // Never trust a paid answer for another amount than the one asked.
            if (isset($body['amount']) && (int) $body['amount'] !== $this->rials($attempt)) {
                return GatewayVerification::failed('The paid amount does not match.');
            }

            $ref = $body['refNumber'] ?? null;
            $card = $body['cardNumber'] ?? null;

            return GatewayVerification::paid(is_scalar($ref) ? (string) $ref : null, is_string($card) ? $card : null);
        }

        // 202: not paid or failed. Until the customer returns, that still can change.
        if ($result === 202) {
            return GatewayVerification::notPaid('code 202');
        }

        return GatewayVerification::failed(is_string($body['message'] ?? null) ? $body['message'] : 'code '.($result ?? '?'));
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
            $response = $this->http()->asJson()->post(self::BASE.$path, $json);
        } catch (ConnectionException $e) {
            throw new GatewayException('Zibal could not be reached.', previous: $e);
        }

        return $this->json($response);
    }
}
