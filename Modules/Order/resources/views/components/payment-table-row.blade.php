@php
    use App\Helpers\Utils;
@endphp
<x-table.row>
    <x-table.cell>
        <flux:link href="{{ route('orders.edit', ['order' => $row->order]) }}">{{ $row->order->order_number }}</flux:link>
    </x-table.cell>
    <x-table.cell>
        {{ $row->order->user->fullName() }}
    </x-table.cell>
    <x-table.cell>
        {{ Utils::formatQuantity($row->amount, 2, ',') }}
    </x-table.cell>
    <x-table.cell>
        {{ $row->method->label() }}
    </x-table.cell>
    <x-table.cell>
        {{ Utils::pDigits($row->paid_at_jalali?->format('Y/m/d')) }}
    </x-table.cell>
    <x-table.cell>
        <flux:button
            href="{{ route('payments.receipt', ['payment' => $row]) }}"
            target="_blank"
            :tooltip="__('order::strings.Receipt')"
            icon="printer"
            variant="ghost"
        />
        <flux:button
            href="{{ route('payments.edit', ['payment' => $row]) }}"
            :tooltip="__('order::strings.Edit Payment')"
            icon="pencil-square"
            variant="ghost"
        />
    </x-table.cell>
</x-table.row>
