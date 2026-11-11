<form
    wire:submit="save"
    class="space-y-8"
>

    <flux:card class="space-y-6">
        <x-user::user-fields :user="$user ?? null" />
    </flux:card>

    <div class="flex justify-start gap-3">
        <flux:button
            type="submit"
            variant="primary"
        >
            {{ __('user::strings.Save User') }}
        </flux:button>
        <flux:button
            variant="ghost"
            href="{{ url()->previous() }}"
        >
            {{ __('strings.Cancel') }}
        </flux:button>
    </div>

</form>
