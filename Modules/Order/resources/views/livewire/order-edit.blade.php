<div class="space-y-5">
    <x-dashboard::edit-actions>
        @if ($order->status !== \Modules\Order\Enums\OrderStatus::CANCELLED && $order->paymentStatus() !== \Modules\Order\Enums\PaymentStatus::PAID)
            <flux:button
                :href="route('orders.pay', ['order' => $order])"
                icon="banknotes"
                size="sm"
            >
                {{ __('order::strings.Collect Payment') }}
            </flux:button>
        @endif
        <flux:button
            :href="route('orders.invoice', ['order' => $order])"
            target="_blank"
            icon="printer"
            size="sm"
        >
            {{ __('order::strings.Invoice') }}
        </flux:button>
    </x-dashboard::edit-actions>

    <x-order::order-amounts
        :total="$total"
        :paid="$paid"
    />

    <x-order::order-form
        :$userCars
        :$itemCatalog
        :$users
        :$order
    />
</div>
