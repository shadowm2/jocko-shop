<x-table.row>
    <x-table.cell>
        {{ \App\Helpers\Utils::pDigits($row->car?->company?->name) }}
    </x-table.cell>
    <x-table.cell>
        {{ \App\Helpers\Utils::pDigits($row->car?->name) }}
    </x-table.cell>
    <x-table.cell>
        {{ \App\Helpers\Utils::pDigits($row->color?->name) }}
    </x-table.cell>
    <x-table.cell>
        <flux:button
            :tooltip="__('order::strings.Vehicle History')"
            :href="route('users.cars.history', ['user' => $this->user, 'userCar' => $row])"
            icon="clipboard-document-list"
            variant="ghost"
        />
        <flux:button
            :tooltip="__('car::strings.Edit Car :car', ['car' => $row->car->name])"
            :href="route('users.cars.edit', ['user' => $this->user, 'userCar' => $row])"
            icon="pencil-square"
            variant="ghost"
        />
    </x-table.cell>
</x-table.row>
