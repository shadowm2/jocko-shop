<x-table.row>
    <x-table.cell>
        {{ $row->name }}
    </x-table.cell>
    <x-table.cell>
        <x-dashboard::popup-image
            :image="$row->logo"
            :alt="$row->name"
            :name="$row->name"
            :label="__('inventory::attributes.Brand Name')"
            :initial="mb_strtoupper(mb_substr($row->name, 0, 3))"
        />
    </x-table.cell>
    <x-table.cell>
        <flux:switch
            :checked="$row->is_active"
            wire:click="toggleIsActive('{{ $row->slug }}')"
        />

    </x-table.cell>
    <x-table.cell>
        <flux:button
            :tooltip="__('inventory::strings.Edit Brand')"
            :href="route('brands.edit', ['brand' => $row->slug])"
            icon="pencil-square"
            variant="ghost"
        />
    </x-table.cell>
</x-table.row>
