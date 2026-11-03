@php
    use App\Helpers\Utils;
@endphp
<x-table.row>
    <x-table.cell>

        <flux:link :href="route('suppliers.edit', ['supplier' => $row->supplier])">{{ $row->supplier->user->fullName() }}</flux:link>
    </x-table.cell>
    @multiwarehouse
        <x-table.cell>
            <flux:link :href="route('warehouses.edit', ['warehouse' => $row->warehouse])">{{ $row->warehouse->name }}</flux:link>
        </x-table.cell>
    @endmultiwarehouse
    <x-table.cell>
        {{ $row->order_number }}
    </x-table.cell>
    <x-table.cell>
        <div
            role="button"
            tabindex="0"
            wire:click="editPurchaseItems('{{ $row->slug }}')"
            onkeydown="if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); this.click(); }"
            class="flex cursor-pointer flex-col rounded px-1 py-0.5 hover:bg-zinc-100 focus:outline-none focus:ring-2 focus:ring-accent dark:hover:bg-zinc-800"
            aria-label="{{ __('inventory::attributes.Purchase Items and Total') }}"
        >
            <span>{{ Utils::pDigits((string) $row->items_count) }} {{ __('inventory::strings.Purchase Items') }}</span>
            <span class="text-xs text-zinc-500">{{ Utils::pDigits(number_format((float) ($row->items_sum_total ?? 0), 0, '.', ',')) }} {{ __('inventory::attributes.Purchase Total') }}</span>
        </div>
    </x-table.cell>
    <x-table.cell>
        <flux:badge :color="$row->status->color()">
            {{ $row->status->label() }}
        </flux:badge>
    </x-table.cell>
    <x-table.cell>
        {{ Utils::pDigits($row->ordered_at_jalali?->format('Y/m/d')) }}
    </x-table.cell>
    <x-table.cell>
        {{ Utils::pDigits($row->received_at_jalali?->format('Y/m/d')) }}
    </x-table.cell>
    <x-table.cell>
        <flux:button
            icon="pencil-square"
            variant="ghost"
            :href="route('purchases.edit', ['purchase' => $row])"
        />
    </x-table.cell>
</x-table.row>
