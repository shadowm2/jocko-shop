<div>
    <x-dashboard::edit-actions>
        <flux:button
            :href="route('users.cars.history', ['user' => $user, 'userCar' => $userCar])"
            icon="clipboard-document-list"
            size="sm"
        >
            {{ __('order::strings.Vehicle History') }}
        </flux:button>
    </x-dashboard::edit-actions>

    <x-user::user-car-form
        :companies="$companies"
        :cars="$cars"
    />
</div>
