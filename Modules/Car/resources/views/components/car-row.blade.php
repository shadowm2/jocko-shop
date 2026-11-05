<x-table.row>
    <x-table.cell>
        {{ \App\Helpers\Utils::pDigits($row->name) }}
    </x-table.cell>
    <x-table.cell>
        <flux:link :href="route('companies.edit', ['company' => $row->company])">
            {{ \App\Helpers\Utils::pDigits($row->company?->name) }}
        </flux:link>
    </x-table.cell>
    <x-table.cell>
        <x-dashboard::popup-image
            :image="$row->images()->first()"
            :alt="$row->name"
            :initial="mb_substr($row->name, 0, 1)"
            :name="$row->name"
            :label="__('car::attributes.Car Name')"
        />
    </x-table.cell>
    <x-table.cell>
        <flux:button
            :tooltip="__('car::strings.Edit Car')"
            :href="route('cars.edit', ['car' => $row->slug])"
            icon="pencil-square"
            variant="ghost"
        />
    </x-table.cell>
</x-table.row>
