<x-table.row>
    <x-table.cell>
        {{ \App\Helpers\Utils::pDigits($row->name) }}
    </x-table.cell>
    <x-table.cell>
        {{ \App\Helpers\Utils::pDigits($row->slug) }}
    </x-table.cell>
    <x-table.cell>
        <x-dashboard::popup-image
            :image="$row->logo"
            :alt="$row->name"
            :initial="mb_substr($row->name, 0, 1)"
            :name="$row->name"
            :label="__('car::attributes.Car Company Name')"
        />
    </x-table.cell>
    <x-table.cell>
        <flux:button
            :tooltip="__('car::strings.Edit Car Company')"
            :href="route('companies.edit', ['company' => $row->slug])"
            icon="pencil-square"
            variant="ghost"
        />
    </x-table.cell>
</x-table.row>
