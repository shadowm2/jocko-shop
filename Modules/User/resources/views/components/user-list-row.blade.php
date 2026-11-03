@props(['row' => null])
<x-table.row>
    <x-table.cell>
        {{ $row->first_name }}
    </x-table.cell>
    <x-table.cell>
        {{ $row->last_name }}
    </x-table.cell>
    <x-table.cell>
        @if(filter_var($row->email, FILTER_VALIDATE_EMAIL))
            <flux:link :href="'mailto:'.$row->email">
                {{ $row->email }}
            </flux:link>
        @else
            {{ $row->email }}
        @endif
    </x-table.cell>
    <x-table.cell>
        <flux:button
            :tooltip="__('user::strings.User :name Cars', ['name' => $row->fullName()])"
            href="{{ route('users.cars.index', ['user' => $row]) }}"
            variant="ghost"
            icon="car"
        />
        <flux:button
            :tooltip="__('order::strings.Statement')"
            :href="route('users.statement', ['user' => $row])"
            target="_blank"
            variant="ghost"
            icon="document-text"
        />
        <flux:button
            :tooltip="__('user::strings.Edit User')"
            :href="route('users.edit', ['user' => $row])"
            icon="pencil-square"
            variant="ghost"
        />

    </x-table.cell>
</x-table.row>
