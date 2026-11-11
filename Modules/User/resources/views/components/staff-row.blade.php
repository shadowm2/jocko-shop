@props(['row' => null, 'roles' => [], 'canAssign' => false])
@php
    $current = $row->roles->first()?->name ?? '';
    $label = fn (string $name) => in_array($name, ['manager', 'cashier'], true) ? __('user::strings.role.'.$name) : $name;
@endphp
<x-table.row>
    <x-table.cell>{{ $row->first_name }}</x-table.cell>
    <x-table.cell>{{ $row->last_name }}</x-table.cell>
    <x-table.cell>{{ $row->mobile }}</x-table.cell>
    <x-table.cell>
        @if ($canAssign)
            <flux:select
                size="sm"
                wire:change="setRole({{ $row->id }}, $event.target.value)"
                wire:key="role-{{ $row->id }}-{{ $current }}"
            >
                <flux:select.option value="" :selected="$current === ''">{{ __('user::strings.No role (full access)') }}</flux:select.option>
                @foreach ($roles as $roleName)
                    <flux:select.option :value="$roleName" :selected="$roleName === $current">{{ $label($roleName) }}</flux:select.option>
                @endforeach
            </flux:select>
        @else
            {{ $current === '' ? __('user::strings.No role (full access)') : $label($current) }}
        @endif
    </x-table.cell>
    <x-table.cell>
        <flux:button
            :tooltip="__('user::strings.Edit User')"
            :href="route('users.edit', ['user' => $row])"
            icon="pencil-square"
            variant="ghost"
        />
    </x-table.cell>
</x-table.row>
