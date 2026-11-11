<form
    class="space-y-8"
    wire:submit="save"
>
    <flux:card class="space-y-4">
        <x-user::role-fields :role="$role ?? null" />
    </flux:card>

    <div class="flex justify-start gap-3">
        <flux:button
            type="submit"
            variant="primary"
        >
            {{ __('user::strings.Save Role') }}
        </flux:button>
        <flux:button
            variant="ghost"
            href="{{ route('roles.index') }}"
            wire:navigate
        >
            {{ __('strings.Cancel') }}
        </flux:button>
    </div>
</form>
