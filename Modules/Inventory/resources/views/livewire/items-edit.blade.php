<div>
    <x-dashboard::edit-actions>
        <flux:button
            :href="route('items.brands.index', ['item' => $item->slug])"
            icon="tag"
            size="sm"
        >
            {{ __('inventory::strings.Item Brands') }}
        </flux:button>
    </x-dashboard::edit-actions>

    <x-inventory::item-form
        :$form
        :$categories
        :$unitGroups
    />
</div>
