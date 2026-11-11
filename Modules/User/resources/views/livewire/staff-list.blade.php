<div>
    <x-table.data-table
        :$columns
        :$rows
        :title="__('user::strings.Staff')"
        row-component="user::components.staff-row"
        :row-props="['roles' => $roles, 'canAssign' => $canAssign]"
    >
        <x-slot name="toolbar">
            <x-table.filter-drawer :values="$filters" :filters="$filterDefinitions">
                <x-table.filters :filters="$filterDefinitions" clear-action="clearFilters" panel />
            </x-table.filter-drawer>
        </x-slot>
    </x-table.data-table>
</div>
