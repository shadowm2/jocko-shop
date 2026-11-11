@props(['role' => null])
@php
    $builtIn = in_array($role?->name, ['manager', 'cashier'], true);
    $isManager = $role?->name === 'manager';
@endphp
<flux:field>
    <flux:label>{{ __('user::strings.Role') }}</flux:label>

    <flux:input
        wire:model="form.name"
        :disabled="$builtIn"
        :placeholder="__('user::strings.Enter role name')"
    />

    <flux:error name="form.name" />
</flux:field>

<flux:field>
    <flux:label>{{ __('user::strings.Permissions') }}</flux:label>

    @if ($isManager)
        <flux:text>{{ __('user::strings.The manager always has every permission') }}</flux:text>
    @else
        <flux:text>{{ __('user::strings.Orders, payments, customers and the daily close are open to all staff') }}</flux:text>

        <div class="grid gap-3 sm:grid-cols-2">
            @foreach (\Modules\User\Enums\UserPermission::cases() as $permission)
                <flux:checkbox
                    wire:model="form.permissions"
                    :value="$permission->value"
                    :label="__('user::strings.permission.'.$permission->value)"
                />
            @endforeach
        </div>
    @endif

    <flux:error name="form.permissions" />
</flux:field>
