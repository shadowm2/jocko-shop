<?php

namespace Modules\Order\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Order\Enums\OrderStatus;
use Modules\Order\Enums\PaymentAttemptStatus;
use Modules\Order\Enums\PaymentMethod;
use Modules\Order\Models\Order;
use Modules\Order\Models\PaymentAttempt;
use Modules\Order\Payments\GatewayException;
use Modules\Order\Payments\GatewayVerification;
use Modules\Order\Payments\PaymentDrivers;
use Modules\Order\Payments\PaymentGateway;
use Modules\Order\Payments\VerifyOutcome;

/**
 * Collects money for an order through an online gateway or a card terminal.
 *
 * An attempt only becomes a payment once the gateway (or the cashier, for a
 * terminal) says it was paid, and only once: completing is idempotent, so a
 * customer reloading the return page or two checks racing each other cannot
 * record the money twice. What the customer's browser says is never believed;
 * the gateway is always asked.
 */
class PaymentAttemptService
{
    public function __construct(
        private readonly PaymentDrivers $drivers,
        private readonly PaymentService $payments,
    ) {}

    /**
     * What the order still owes, not counting payments that are still open.
     */
    public function balance(Order $order): float
    {
        return round((float) $order->items()->sum('total') - (float) $order->payments()->sum('amount'), 2);
    }

    /**
     * Ask a gateway for a payment of $amount and give back the attempt, whose
     * `pay_url` is where the customer pays.
     *
     * @throws InvalidArgumentException when the order or amount cannot be paid
     * @throws GatewayException when the gateway refuses or cannot be reached
     */
    public function startOnline(Order $order, string $gatewayKey, float $amount, ?int $userId = null): PaymentAttempt
    {
        $gateway = $this->drivers->gateway($gatewayKey);

        if ($gateway === null || ! $gateway->isConfigured()) {
            throw new InvalidArgumentException('That payment gateway is not set up.');
        }

        $attempt = $this->create($order, 'gateway', $gateway->key(), $amount, $userId);

        try {
            $started = $gateway->start($attempt, $this->callbackUrl($attempt), $this->description($order));
        } catch (GatewayException $e) {
            $this->finish($attempt, PaymentAttemptStatus::FAILED, $e->getMessage());

            throw $e;
        }

        $attempt->update(['reference' => $started->reference, 'pay_url' => $started->url]);

        return $attempt;
    }

    /**
     * Start a payment on a card terminal. A manual terminal waits for the cashier
     * to confirm it ({@see confirmPos()}).
     *
     * @throws InvalidArgumentException
     * @throws GatewayException
     */
    public function startPos(Order $order, string $terminalKey, float $amount, ?int $userId = null): PaymentAttempt
    {
        $terminal = $this->drivers->terminal($terminalKey) ?? throw new InvalidArgumentException('That card terminal is not available.');

        $attempt = $this->create($order, 'pos', $terminal->key(), $amount, $userId);

        try {
            $terminal->start($attempt);
        } catch (GatewayException $e) {
            $this->finish($attempt, PaymentAttemptStatus::FAILED, $e->getMessage());

            throw $e;
        }

        return $attempt;
    }

    /**
     * The customer's browser came back from the gateway. Look at the gateway, not at it.
     *
     * @param  array<string, mixed>  $params  the query/form fields of the return
     */
    public function applyReturn(PaymentAttempt $attempt, array $params): PaymentAttempt
    {
        $gateway = $this->gatewayOf($attempt);

        if ($gateway === null) {
            return $attempt;
        }

        $reference = $gateway->referenceFrom($params);

        // A return for some other payment must not touch this one.
        if ($reference !== null && $attempt->reference !== null && ! hash_equals($attempt->reference, $reference)) {
            Log::warning("A payment return for attempt {$attempt->public_id} carried another reference.");

            return $attempt;
        }

        if ($gateway->returnedUnpaid($params) && $attempt->isOpen()) {
            return $this->finish($attempt, PaymentAttemptStatus::CANCELLED, 'The customer left the payment page without paying.');
        }

        // Even an attempt given up on is looked at again: the customer may have paid late.
        return $this->reconcile($attempt, force: true);
    }

    /**
     * Ask the gateway (or an automatic terminal) what became of an attempt.
     *
     * @param  bool  $force  also check an attempt that is no longer open
     */
    public function reconcile(PaymentAttempt $attempt, bool $force = false): PaymentAttempt
    {
        $attempt->refresh();

        if ($attempt->status === PaymentAttemptStatus::PAID || (! $attempt->isOpen() && ! $force)) {
            return $attempt;
        }

        $verification = $this->ask($attempt);
        $attempt->update(['last_checked_at' => now()]);

        if ($verification === null) {
            return $attempt;
        }

        return match ($verification->outcome) {
            VerifyOutcome::PAID => $this->complete($attempt, $verification->reference, $verification->cardPan),
            VerifyOutcome::FAILED => $attempt->isOpen() ? $this->finish($attempt, PaymentAttemptStatus::FAILED, $verification->message) : $attempt,
            VerifyOutcome::NOT_PAID => $attempt->isOpen() && $attempt->expires_at->isPast()
                ? $this->finish($attempt, PaymentAttemptStatus::EXPIRED, 'Not paid in time.')
                : $attempt,
        };
    }

    /**
     * The cashier took the payment on a manual terminal and confirms it.
     *
     * @throws InvalidArgumentException
     */
    public function confirmPos(PaymentAttempt $attempt, string $terminalReference, ?string $cardLastDigits = null): PaymentAttempt
    {
        $terminalReference = trim($terminalReference);

        if ($attempt->kind !== 'pos' || ! $attempt->isOpen()) {
            throw new InvalidArgumentException('This payment is not waiting for a card terminal.');
        }

        if ($terminalReference === '' || mb_strlen($terminalReference) > 50) {
            throw new InvalidArgumentException('Enter the reference number the terminal printed.');
        }

        $digits = preg_replace('/\D/', '', (string) $cardLastDigits) ?? '';
        $pan = $digits === '' ? null : '**** '.substr($digits, -4);

        return $this->complete($attempt, $terminalReference, $pan);
    }

    /**
     * The terminal declined, or the cashier stopped.
     */
    public function declinePos(PaymentAttempt $attempt, ?string $reason = null): PaymentAttempt
    {
        return $attempt->kind === 'pos' && $attempt->isOpen()
            ? $this->finish($attempt, PaymentAttemptStatus::FAILED, $reason ?? 'Declined at the terminal.')
            : $attempt;
    }

    /**
     * Stop waiting. An online payment is checked first: if the customer did pay,
     * that wins and the money is recorded.
     */
    public function cancel(PaymentAttempt $attempt): PaymentAttempt
    {
        if ($attempt->isOnline() && $attempt->isOpen()) {
            $attempt = $this->reconcile($attempt, force: true);
        }

        return $attempt->isOpen() ? $this->finish($attempt, PaymentAttemptStatus::CANCELLED, 'Cancelled by the cashier.') : $attempt;
    }

    /**
     * Look at every open attempt once, and give up on the ones that ran out of time.
     *
     * @return array{checked: int, completed: int, expired: int}
     */
    public function reconcileOpen(): array
    {
        $checked = $completed = $expired = 0;

        PaymentAttempt::query()
            ->where('status', PaymentAttemptStatus::PENDING->value)
            ->orderBy('id')
            ->each(function (PaymentAttempt $attempt) use (&$checked, &$completed, &$expired): void {
                $checked++;
                $before = $attempt->status;

                // Online payments are asked about; a manual terminal can only time out.
                $attempt = $attempt->isOnline() || $this->terminalIsAutomatic($attempt)
                    ? $this->reconcile($attempt)
                    : $this->expireIfDue($attempt);

                if ($attempt->status === PaymentAttemptStatus::PAID && $before !== PaymentAttemptStatus::PAID) {
                    $completed++;
                } elseif ($attempt->status === PaymentAttemptStatus::EXPIRED) {
                    $expired++;
                }
            });

        return compact('checked', 'completed', 'expired');
    }

    /**
     * @throws InvalidArgumentException
     */
    private function create(Order $order, string $kind, string $driver, float $amount, ?int $userId): PaymentAttempt
    {
        if ($order->status === OrderStatus::CANCELLED) {
            throw new InvalidArgumentException('A cancelled order cannot be paid.');
        }

        $amount = round($amount, 2);

        if ($amount <= 0 || $amount > $this->balance($order)) {
            throw new InvalidArgumentException('The amount must be more than zero and no more than what the order still owes.');
        }

        if ($order->paymentAttempts()->where('status', PaymentAttemptStatus::PENDING->value)->where('kind', $kind)->exists()) {
            throw new InvalidArgumentException('This order already has a payment in progress. Finish or cancel it first.');
        }

        return PaymentAttempt::query()->create([
            'public_id' => (string) Str::uuid(),
            'order_id' => $order->getKey(),
            'user_id' => $userId,
            'kind' => $kind,
            'driver' => $driver,
            'amount' => $amount,
            'currency' => $this->currency(),
            'status' => PaymentAttemptStatus::PENDING,
            'expires_at' => now()->addMinutes(max(1, (int) config('payments.attempt_ttl_minutes'))),
        ]);
    }

    private function ask(PaymentAttempt $attempt): ?GatewayVerification
    {
        try {
            if ($attempt->isOnline()) {
                $gateway = $this->gatewayOf($attempt);

                // A payment the gateway never accepted has nothing to ask about.
                return $gateway === null || $attempt->reference === null ? null : $gateway->verify($attempt);
            }

            $terminal = $this->drivers->terminal($attempt->driver);

            return $terminal !== null && $terminal->isAutomatic() ? $terminal->check($attempt) : null;
        } catch (GatewayException $e) {
            // Unreachable or confused: try again at the next check, never guess.
            Log::warning("Payment attempt {$attempt->public_id}: {$e->getMessage()}");

            return null;
        }
    }

    /**
     * Record the payment once. Safe to call again and from two places at once.
     */
    private function complete(PaymentAttempt $attempt, ?string $reference, ?string $cardPan): PaymentAttempt
    {
        return DB::transaction(function () use ($attempt, $reference, $cardPan): PaymentAttempt {
            $locked = PaymentAttempt::query()->whereKey($attempt->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status === PaymentAttemptStatus::PAID) {
                return $locked;
            }

            $order = $locked->order;

            $payment = $this->payments->create([
                'order' => $order->slug,
                'amount' => (float) $locked->amount,
                'method' => $locked->kind === 'pos' ? PaymentMethod::POS->value : PaymentMethod::ONLINE->value,
                'paid_at' => now()->toDateString(),
                'description' => trim(($this->label($locked)).' '.($reference !== null ? '#'.$reference : '')),
            ]);

            $locked->update([
                'status' => PaymentAttemptStatus::PAID,
                'payment_id' => $payment->getKey(),
                'result_reference' => $reference,
                'card_pan' => $cardPan,
                'failure' => null,
                'completed_at' => now(),
            ]);

            return $locked;
        });
    }

    private function finish(PaymentAttempt $attempt, PaymentAttemptStatus $status, ?string $failure): PaymentAttempt
    {
        $attempt->update(['status' => $status, 'failure' => $failure !== null ? Str::limit($failure, 500, '') : null, 'completed_at' => now()]);

        return $attempt;
    }

    private function expireIfDue(PaymentAttempt $attempt): PaymentAttempt
    {
        return $attempt->isOpen() && $attempt->expires_at->isPast()
            ? $this->finish($attempt, PaymentAttemptStatus::EXPIRED, 'Not confirmed in time.')
            : $attempt;
    }

    private function terminalIsAutomatic(PaymentAttempt $attempt): bool
    {
        return $attempt->kind === 'pos' && ($this->drivers->terminal($attempt->driver)?->isAutomatic() ?? false);
    }

    private function gatewayOf(PaymentAttempt $attempt): ?PaymentGateway
    {
        return $attempt->isOnline() ? $this->drivers->gateway($attempt->driver) : null;
    }

    private function label(PaymentAttempt $attempt): string
    {
        return $attempt->kind === 'pos'
            ? (string) __('order::strings.Card terminal')
            : ($this->drivers->gateway($attempt->driver)?->label() ?? $attempt->driver);
    }

    private function description(Order $order): string
    {
        return config('app.name').' - '.$order->order_number;
    }

    private function currency(): string
    {
        return config('payments.currency') === 'IRR' ? 'IRR' : 'IRT';
    }

    /**
     * Where the gateway sends the customer's browser back to: the shop's address
     * as the customer can reach it, not necessarily APP_URL.
     */
    public function callbackUrl(PaymentAttempt $attempt): string
    {
        $base = rtrim((string) (config('payments.callback_base_url') ?: config('app.url')), '/');

        return $base.route('payments.online.return', ['attempt' => $attempt->public_id], absolute: false);
    }
}
