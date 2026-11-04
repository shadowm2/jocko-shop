<?php

namespace Modules\Order\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\Order\Models\Payment;

/**
 * A payment's receipt, narrow enough for a 58 mm or 80 mm thermal printer.
 */
class PaymentReceipt extends Component
{
    public const WIDTHS = [58, 80];

    public Payment $payment;

    /** Paper width in millimetres: 58 or 80. */
    #[Url]
    public int $width = 80;

    public function mount(Payment $payment): void
    {
        $this->payment = $payment->load(['order.user', 'order.userCar.car']);

        if (! in_array($this->width, self::WIDTHS, true)) {
            $this->width = (int) config('payments.receipt_width');
        }
    }

    public function render(): View
    {
        $order = $this->payment->order;

        return view('order::livewire.payment-receipt', [
            'order' => $order,
            'total' => (float) $order->items()->sum('total'),
            'paid' => (float) $order->payments()->sum('amount'),
        ])
            ->layout('order::components.layouts.invoice')
            ->layoutData(['title' => __('order::strings.Receipt').' '.$order->order_number]);
    }
}
