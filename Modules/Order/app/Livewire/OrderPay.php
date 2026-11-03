<?php

namespace Modules\Order\Livewire;

use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Modules\Order\Enums\OrderStatus;
use Modules\Order\Models\Order;
use Modules\Order\Models\PaymentAttempt;
use Modules\Order\Payments\GatewayException;
use Modules\Order\Payments\PaymentDrivers;
use Modules\Order\Services\PaymentAttemptService;

/**
 * The cashier's screen for collecting what an order still owes: online, through
 * a gateway the customer pays on their phone, or on a card terminal.
 */
class OrderPay extends Component
{
    #[Locked]
    public Order $order;

    public string $amount = '';

    /** The attempt being followed. */
    #[Locked]
    public ?string $attemptId = null;

    public string $terminalReference = '';

    public string $cardLastDigits = '';

    public function mount(Order $order, PaymentAttemptService $attempts): void
    {
        abort_if($order->status === OrderStatus::CANCELLED, 404);

        $this->order = $order;
        $balance = $attempts->balance($order);
        $this->amount = $balance > 0 ? (string) $balance : '';

        // Pick up where the cashier left off: an attempt still open for this order.
        $this->attemptId = $order->paymentAttempts()->where('status', 'pending')->latest('id')->value('public_id');
    }

    public function payOnline(string $gateway, PaymentAttemptService $attempts): void
    {
        $this->start(fn (float $amount) => $attempts->startOnline($this->order, $gateway, $amount, Auth::user()?->getKey()));
    }

    public function payByCard(string $terminal, PaymentAttemptService $attempts): void
    {
        $this->start(fn (float $amount) => $attempts->startPos($this->order, $terminal, $amount, Auth::user()?->getKey()));
    }

    /**
     * @param  callable(float): PaymentAttempt  $start
     */
    private function start(callable $start): void
    {
        $this->validate(['amount' => ['required', 'numeric', 'gt:0']]);

        try {
            $this->attemptId = $start((float) $this->amount)->public_id;
        } catch (InvalidArgumentException|GatewayException $e) {
            Flux::toast($e->getMessage(), variant: 'danger');
        }
    }

    /**
     * Runs on a timer while a payment is open, so the screen turns green as soon as
     * the customer has paid.
     */
    public function refreshAttempt(PaymentAttemptService $attempts): void
    {
        $attempt = $this->attempt();

        // Only an online payment can change by itself; a terminal waits for the cashier.
        if ($attempt === null || ! $attempt->isOpen() || ! $attempt->isOnline()) {
            return;
        }

        $attempt = $attempts->reconcile($attempt);

        if ($attempt->status->value === 'paid') {
            $this->announcePaid($attempts);
        }
    }

    public function confirmCard(PaymentAttemptService $attempts): void
    {
        $attempt = $this->attempt();

        if ($attempt === null) {
            return;
        }

        try {
            $attempts->confirmPos($attempt, $this->terminalReference, $this->cardLastDigits);
        } catch (InvalidArgumentException $e) {
            Flux::toast($e->getMessage(), variant: 'danger');

            return;
        }

        $this->announcePaid($attempts);
    }

    public function declineCard(PaymentAttemptService $attempts): void
    {
        if ($attempt = $this->attempt()) {
            $attempts->declinePos($attempt);
        }

        $this->forgetAttempt();
    }

    public function cancel(PaymentAttemptService $attempts): void
    {
        if ($attempt = $this->attempt()) {
            $attempt = $attempts->cancel($attempt);

            // The customer had paid after all.
            if ($attempt->status->value === 'paid') {
                $this->announcePaid($attempts);

                return;
            }
        }

        $this->forgetAttempt();
    }

    public function dismiss(PaymentAttemptService $attempts): void
    {
        $this->forgetAttempt();
        $balance = $attempts->balance($this->order->refresh());
        $this->amount = $balance > 0 ? (string) $balance : '';
    }

    private function announcePaid(PaymentAttemptService $attempts): void
    {
        Flux::toast(__('order::messages.Payment Created Successfully'), variant: 'success');
        $this->forgetAttempt();
        $balance = $attempts->balance($this->order->refresh());
        $this->amount = $balance > 0 ? (string) $balance : '';
    }

    private function forgetAttempt(): void
    {
        $this->attemptId = null;
        $this->terminalReference = '';
        $this->cardLastDigits = '';
    }

    private function attempt(): ?PaymentAttempt
    {
        return $this->attemptId === null
            ? null
            : $this->order->paymentAttempts()->where('public_id', $this->attemptId)->first();
    }

    public function render(PaymentAttemptService $attempts, PaymentDrivers $drivers): View
    {
        $order = $this->order->refresh();

        return view('order::livewire.order-pay', [
            'total' => (float) $order->items()->sum('total'),
            'paid' => (float) $order->payments()->sum('amount'),
            'balance' => $attempts->balance($order),
            'attempt' => $this->attempt(),
            'history' => $order->paymentAttempts()->latest('id')->limit(8)->get(),
            'gateways' => $drivers->configuredGateways(),
            'terminals' => $drivers->terminals(),
            'callbackBase' => rtrim((string) (config('payments.callback_base_url') ?: config('app.url')), '/'),
        ])
            ->layout('dashboard::layouts.app')
            ->layoutData(['title' => __('order::strings.Collect Payment').' '.$order->order_number]);
    }
}
