@php
    $builtIn = in_array($row->name, ['manager', 'cashier'], true);
@endphp
<x-table.row>
    <x-table.cell>
        {{ $builtIn ? __('user::strings.role.'.$row->name) : $row->name }}
    </x-table.cell>
    <x-table.cell>
        {{ $row->name === 'manager' ? __('user::strings.All permissions') : $row->permissions->count() }}
    </x-table.cell>
    <x-table.cell>
        {{ $row->users_count }}
    </x-table.cell>
    <x-table.cell>
        <div class="flex flex-row items-center gap-1">
            <flux:button
                :tooltip="__('user::strings.Edit Role')"
                :href="route('roles.edit', ['role' => $row->id])"
                icon="pencil-square"
                variant="ghost"
            />
            @unless ($builtIn)
                <flux:button
                    :tooltip="__('user::strings.Delete Role')"
                    wire:click="delete({{ $row->id }})"
                    wire:confirm="{{ __('user::strings.Delete this role?') }}"
                    icon="trash"
                    variant="ghost"
                />
            @endunless
        </div>
    </x-table.cell>
</x-table.row>
