@php
    use App\Helpers\Utils;
@endphp
<x-table.row>
    @if (\Modules\Inventory\Services\WarehouseService::multiple())
        <x-table.cell>
            {{ $row->warehouse->name }}
        </x-table.cell>
    @endif
    <x-table.cell>
        {{ $row->item->name }}
    </x-table.cell>
    <x-table.cell>
        <span class="text-red-600 dark:text-red-400">{{ Utils::pDigits(Utils::formatQuantity($row->quantity, 3, ',')) }}</span>
    </x-table.cell>
    <x-table.cell>
        {{ Utils::pDigits(Utils::formatQuantity($row->min_quantity, 3, ',')) }}
    </x-table.cell>
    <x-table.cell>
        {{ Utils::pDigits(Utils::formatQuantity($row->min_quantity - $row->quantity, 3, ',')) }}
    </x-table.cell>
    <x-table.cell>
        <flux:button
            :tooltip="__('inventory::strings.Edit Warehouse Item')"
            :href="route('warehouses.items.edit', ['warehouse' => $row->warehouse, 'warehouseItem' => $row])"
            icon="pencil-square"
            variant="ghost"
        />
    </x-table.cell>
</x-table.row>
