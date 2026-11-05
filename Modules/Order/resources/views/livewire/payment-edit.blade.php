<div>
    <x-dashboard::edit-actions>
        <flux:button
            :href="route('payments.receipt', ['payment' => $payment])"
            target="_blank"
            icon="printer"
            size="sm"
        >
            {{ __('order::strings.Receipt') }}
        </flux:button>
    </x-dashboard::edit-actions>

    <x-order::payment-form :$payment />
</div>
