@php
    use App\Helpers\Utils;
@endphp
<x-table.row>
    <x-table.cell>
        {{ $row->order_number }}
    </x-table.cell>
    <x-table.cell>
        <flux:link href="{{ route('users.edit', ['user' => $row->user]) }}">{{ $row->user->fullName() }}</flux:link>
    </x-table.cell>
    <x-table.cell>
        {{ $row->userCar->car->name }}
    </x-table.cell>
    @multiwarehouse
        <x-table.cell>
            {{ $row->warehouse->name }}
        </x-table.cell>
    @endmultiwarehouse
    <x-table.cell>
        <x-order::order-amounts
            compact
            :items="$row->items_count"
            :total="(float) ($row->items_sum_total ?? 0)"
            :paid="(float) ($row->payments_sum_amount ?? 0)"
        />
    </x-table.cell>
    <x-table.cell>
        <flux:badge :color="$row->status->color()">
            {{ $row->status->label() }}
        </flux:badge>
    </x-table.cell>
    <x-table.cell>
        <flux:badge :color="$row->paymentStatus()->color()">
            {{ $row->paymentStatus()->label() }}
        </flux:badge>
    </x-table.cell>
    <x-table.cell>
        <flux:button
            href="{{ route('orders.edit', ['order' => $row]) }}"
            :tooltip="__('order::strings.Edit Order')"
            icon="pencil-square"
            variant="ghost"
        />
        @if ($row->status !== \Modules\Order\Enums\OrderStatus::CANCELLED && $row->paymentStatus() !== \Modules\Order\Enums\PaymentStatus::PAID)
            <flux:button
                href="{{ route('orders.pay', ['order' => $row]) }}"
                :tooltip="__('order::strings.Collect Payment')"
                icon="banknotes"
                variant="ghost"
            />
        @endif
        <flux:button
            href="{{ route('orders.invoice', ['order' => $row]) }}"
            target="_blank"
            :tooltip="__('order::strings.Invoice')"
            icon="printer"
            variant="ghost"
        />
    </x-table.cell>
</x-table.row>
