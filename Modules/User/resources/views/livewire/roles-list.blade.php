<div>
    <x-table.data-table
        :$columns
        :$rows
        :title="__('user::strings.Roles')"
        row-component="user::components.role-row"
    >
        <x-slot name="toolbar">
            <x-table.filter-drawer :values="$filters" :filters="$filterDefinitions">
                <x-table.filters :filters="$filterDefinitions" clear-action="clearFilters" panel />
            </x-table.filter-drawer>
        </x-slot>

        <x-slot name="action">
            <flux:button
                icon:trailing="plus"
                :href="route('roles.create')"
                variant="primary"
            >
                {{ __('user::strings.Add Role') }}
            </flux:button>
        </x-slot>
    </x-table.data-table>
</div>
