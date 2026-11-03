@multiwarehouse
<flux:field>
    <flux:label>
        {{ __('inventory::attributes.Warehouse') }}
    </flux:label>
    <flux:select
        :placeholder="__('inventory::strings.Select Warehouse')"
        wire:model="form.warehouse"
    >
        @foreach ($warehouses as $warehouse)
            <flux:select.option value="{{ $warehouse->slug }}">
                {{ $warehouse->name }}
            </flux:select.option>
        @endforeach
    </flux:select>
    <flux:error name="form.warehouse" />
</flux:field>
@endmultiwarehouse

<flux:field>
    <flux:label>
        {{ __('inventory::attributes.Warehouse Item') }}
    </flux:label>
    <flux:select
        :placeholder="__('inventory::strings.Select Warehouse Item')"
        wire:model="form.item"
        :disabled="request()->routeIs('warehouses.items.edit')"
    >
        @foreach ($items as $item)
            <flux:select.option value="{{ $item->slug }}">
                {{ $item->name }}
            </flux:select.option>
        @endforeach
    </flux:select>
    <flux:error name="form.item" />
</flux:field>

<flux:field>
    <flux:label>
        {{ __('inventory::attributes.Warehouse Item Quantity') }}
    </flux:label>
    <flux:input
        :placeholder="__('inventory::strings.Enter Warehouse Item Quantity')"
        wire:model="form.quantity"
        step="0.001"
        min="0"
        type="number"
    />
    <flux:error name="form.quantity" />
</flux:field>

<flux:field x-data="{ unlimited: @entangle('form.min_quantity_unlimited') }">
    <flux:label>
        {{ __('inventory::attributes.Warehouse Item Min Quantity') }}
    </flux:label>
    <flux:input
        :placeholder="__('inventory::strings.Enter Warehouse Item Min Quantity')"
        wire:model="form.min_quantity"
        type="number"
        step="0.001"
        min="0"
        x-bind:disabled="unlimited"
        x-show="!unlimited"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 -translate-y-2"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 -translate-y-2"
    />
    <div class="mt-2">
        <flux:checkbox
            :label="__('inventory::attributes.Warehouse Item Min Quantity Unlimited')"
            wire:model.live="form.min_quantity_unlimited"
        />
    </div>

    <flux:error name="form.min_quantity" />
</flux:field>

<flux:field x-data="{ unlimited: @entangle('form.max_quantity_unlimited') }">
    <flux:label>
        {{ __('inventory::attributes.Warehouse Item Max Quantity') }}
    </flux:label>
    <flux:input
        :placeholder="__('inventory::strings.Enter Warehouse Item Max Quantity')"
        wire:model="form.max_quantity"
        type="number"
        x-bind:disabled="unlimited"
        x-show="!unlimited"
        step="0.001"
        min="0"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 -translate-y-2"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 -translate-y-2"
        :disabled="boolval($this->form->max_quantity_unlimited)"
    />
    <div class="mt-2">
        <flux:checkbox
            :label="__('inventory::attributes.Warehouse Item Max Quantity Unlimited')"
            wire:model.live="form.max_quantity_unlimited"
        />
    </div>
    <flux:error name="form.max_quantity" />
</flux:field>


