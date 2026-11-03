<div>
    <x-table.data-table
        :$columns
        :$rows
        :title="__('inventory::strings.Stock Movements List')"
        row-component="inventory::components.stock-movement-row"
    >
        <x-slot name="toolbar">
            <x-table.filter-drawer :values="$filters" :filters="$filterDefinitions">
                <x-table.filters
                    :filters="$filterDefinitions"
                    clear-action="clearFilters"
                    panel
                />
            </x-table.filter-drawer>
        </x-slot>

        <x-slot name="action">
            <div class="flex gap-2">
                @multiwarehouse
                    <flux:button
                        icon:trailing="arrows-right-left"
                        :href="route('stock-movements.transfer')"
                    >
                        {{ __('inventory::strings.Stock Transfer Add') }}
                    </flux:button>
                @endmultiwarehouse
                <flux:button
                    icon:trailing="plus"
                    :href="route('stock-movements.create')"
                    variant="primary"
                >
                    {{ __('inventory::strings.Stock Adjustment Add') }}
                </flux:button>
            </div>
        </x-slot>
    </x-table.data-table>
</div>
