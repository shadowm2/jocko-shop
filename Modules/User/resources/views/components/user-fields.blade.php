@props(['user' => null])
<div>
    <flux:heading size="lg">
        {{ __('user::strings.User Information') }}
    </flux:heading>

    <flux:text class="mt-2">
        {{ __('user::strings.Enter User Information') }}
    </flux:text>
</div>

<flux:field>
    <flux:label>
        {{ __('user::attributes.First Name') }}
    </flux:label>
    <flux:input
        required
        wire:model="form.first_name"
        :placeholder="__('user::strings.Enter user first name')"
    />
    <flux:error name="form.first_name" />
</flux:field>

<flux:field>
    <flux:label>
        {{ __('user::attributes.Last Name') }}
    </flux:label>
    <flux:input
        required
        wire:model="form.last_name"
        :placeholder="__('user::strings.Enter user last name')"
    />
    <flux:error name="form.last_name" />
</flux:field>

<flux:field>
    <flux:label>
        {{ __('user::attributes.Email') }}
    </flux:label>
    <flux:input
        wire:model="form.email"
        :placeholder="__('user::strings.Enter user email')"
    />
    <flux:error name="form.email" />
</flux:field>

<flux:field>
    <flux:label>
        {{ __('user::attributes.User Mobile') }}
    </flux:label>
    <flux:input
        required
        wire:model="form.mobile"
        :placeholder="__('user::strings.Enter user mobile number')"
    />
    <flux:error name="form.mobile" />
</flux:field>

<flux:field>
    <flux:label>
        {{ __('user::attributes.New Password') }}
    </flux:label>
    <flux:input
        wire:model="form.password"
        type="password"
        autocomplete="new-password"
        viewable
        :placeholder="__('user::strings.Enter user new password')"
    />
    <flux:error name="form.password" />
</flux:field>

<flux:field>
    <flux:label>
        {{ __('user::attributes.Confirm New Password') }}
    </flux:label>
    <flux:input
        wire:model="form.password_confirmation"
        type="password"
        viewable
        :placeholder="__('user::strings.Enter user new password again')"
    />
    <flux:error name="form.password_confirmation" />
</flux:field>

@if ($user?->type === \Modules\User\Enums\UserType::Admin && auth()->user()?->can('roles.manage'))
    <flux:field>
        <flux:label>{{ __('user::strings.Role') }}</flux:label>

        <flux:select wire:model="form.role">
            <flux:select.option value="">{{ __('user::strings.No role (full access)') }}</flux:select.option>
            @foreach (\Spatie\Permission\Models\Role::query()->orderBy('id')->pluck('name') as $roleName)
                <flux:select.option :value="$roleName">
                    {{ in_array($roleName, ['manager', 'cashier'], true) ? __('user::strings.role.'.$roleName) : $roleName }}
                </flux:select.option>
            @endforeach
        </flux:select>

        <flux:error name="form.role" />
    </flux:field>
@endif
