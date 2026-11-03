<?php

namespace Modules\Order\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Modules\Order\Models\PaymentAttempt;
use Modules\Order\Services\PaymentAttemptService;

/**
 * Where a customer's browser lands after the gateway's page. It is public, so it
 * only ever shows the outcome of the one attempt in the address, and the outcome
 * comes from asking the gateway, never from the parameters in the request.
 */
class GatewayReturnController extends Controller
{
    private const MAX_VALUE_LENGTH = 200;

    public function __invoke(Request $request, PaymentAttempt $attempt, PaymentAttemptService $attempts): View
    {
        $params = [];

        foreach ([...$request->query(), ...$request->post()] as $key => $value) {
            if (is_string($key) && is_scalar($value)) {
                $params[$key] = mb_substr((string) $value, 0, self::MAX_VALUE_LENGTH);
            }
        }

        $attempt = $attempts->applyReturn($attempt, $params);

        return view('order::gateway-return', [
            'attempt' => $attempt->loadMissing('order'),
            'refresh' => $attempt->isOpen() && $attempt->expires_at->isFuture(),
        ]);
    }
}
