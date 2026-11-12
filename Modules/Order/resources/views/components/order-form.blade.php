@php
    $order ??= null;
    $quotable ??= false;
@endphp

<form
    class="space-y-8"
    wire:submit.prevent="save"
>
    <flux:heading size="lg">
        {{ __("order::strings.:user User's Order", ['user' => $order?->user->fullName() ?? '']) }}
    </flux:heading>
    @if ($order)
        <flux:subheading>{{ $order->order_number }}</flux:subheading>
    @endif
    <flux:card class="space-y-4">
        <x-order::order-fields
            :$users
            :$userCars
            :$itemCatalog
            :order="$order"
        />
    </flux:card>

    <div class="flex justify-start gap-3">
        <flux:button
            type="submit"
            variant="primary"
        >
            {{ __('order::strings.Save Order') }}
        </flux:button>
        @if ($quotable)
            <flux:button
                type="button"
                icon="document-text"
                wire:click="saveQuote"
                wire:loading.attr="disabled"
                wire:target="saveQuote"
                data-save-quote
            >
                {{ __('order::strings.Save As Quote') }}
            </flux:button>
        @endif
        <flux:button
            variant="ghost"
            href="{{ url()->previous() }}"
        >
            {{ __('strings.Cancel') }}
        </flux:button>
    </div>
</form>
