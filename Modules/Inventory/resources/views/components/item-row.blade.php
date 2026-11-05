<x-table.row>
    <x-table.cell>
        <x-dashboard::popup-image
            :image="$row->images->first()"
            :alt="$row->name"
            :name="$row->name"
            :label="__('inventory::attributes.Item Name')"
            :initial="mb_strtoupper(mb_substr($row->name, 0, 3))"
        />
    </x-table.cell>
    <x-table.cell>
        {{ $row->name }}
    </x-table.cell>
    <x-table.cell>
        <flux:link :href="route('categories.edit', ['category' => $row->category])">{{ $row->category->name }}</flux:link>
    </x-table.cell>
    <x-table.cell>
        <flux:link :href="route('units.groups.edit', ['unitGroup' => $row->unitGroup])">{{ $row->unitGroup->name }}</flux:link>
    </x-table.cell>
    <x-table.cell>
        {{ \App\Helpers\Utils::pDigits($row->brands_count) }}
    </x-table.cell>
    <x-table.cell>
        <flux:switch
            :checked="$row->is_active"
            wire:click="toggleIsActive('{{ $row->slug }}')"
        />
    </x-table.cell>
    <x-table.cell>
        <flux:button
            :tooltip="__('inventory::strings.Edit Item')"
            :href="route('items.edit', ['item' => $row->slug])"
            icon="pencil-square"
            variant="ghost"
        />
        <flux:button
            :tooltip="__('inventory::strings.Item Brands')"
            :href="route('items.brands.index', ['item' => $row->slug])"
            icon="tag"
            variant="ghost"
        />
    </x-table.cell>
</x-table.row>
