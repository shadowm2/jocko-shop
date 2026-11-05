<div>
    <x-dashboard::edit-actions>
        <flux:button
            :href="route('users.cars.index', ['user' => $user])"
            icon="car"
            size="sm"
        >
            {{ __('user::strings.User :name Cars', ['name' => $user->fullName()]) }}
        </flux:button>
        <flux:button
            :href="route('users.statement', ['user' => $user])"
            target="_blank"
            icon="document-text"
            size="sm"
        >
            {{ __('order::strings.Statement') }}
        </flux:button>
    </x-dashboard::edit-actions>

    <x-user::user-form />
</div>
