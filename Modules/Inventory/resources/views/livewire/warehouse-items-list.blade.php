<div class="flex flex-col gap-5">
    @multiwarehouse
        <livewire:inventory-warehouse-picker />
    @endmultiwarehouse
    <x-table.data-table
        :$columns
        :$rows
        :title="__('inventory::strings.Warehouse Items List')"
        row-component="inventory::components.warehouse-item-row"
    >
        <x-slot name="toolbar">
            <x-table.filter-drawer :values="$filters" :filters="$filterDefinitions">
                <x-table.filters
                    :filters="$filterDefinitions"
                    :sort-options="$sortOptions"
                    :sort-by="$sortBy"
                    :sort-direction="$sortDirection"
                    clear-action="clearFilters"
                    panel
                />
            </x-table.filter-drawer>
        </x-slot>

        @if ($warehouse instanceof \Illuminate\Database\Eloquent\Model)
            <x-slot name="action">
                <flux:button
                    icon:trailing="plus"
                    :href="route('warehouses.items.create', ['warehouse' => $warehouse->slug])"
                    variant="primary"
                >
                    {{ __('inventory::strings.Warehouse Item Add') }}
                </flux:button>
            </x-slot>
        @endif
    </x-table.data-table>
</div>
