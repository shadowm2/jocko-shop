<form
    class="w-full max-w-2xl space-y-6"
    wire:submit="save"
>
    <div>
        <flux:heading
            size="xl"
            level="1"
        >{{ __('dashboard::strings.Jocko Control') }}</flux:heading>
        <flux:subheading>{{ __('dashboard::strings.Jocko Control Description') }}</flux:subheading>
    </div>

    <flux:card class="space-y-4">
        <flux:field>
            <flux:label>{{ __('dashboard::strings.Jocko Control URL') }}</flux:label>
            <flux:input
                wire:model="url"
                type="url"
                dir="ltr"
                placeholder="https://control.example.com"
            />
            <flux:error name="url" />
        </flux:field>

        <flux:field x-data="{ visible: false }">
            <div class="flex items-center justify-between gap-2">
                <flux:label>{{ __('dashboard::strings.Jocko Control Token') }}</flux:label>
                <div class="flex items-center gap-2">
                    <flux:badge
                        size="sm"
                        :color="$tokenIsSet ? 'green' : 'zinc'"
                    >
                        {{ $tokenIsSet ? __('dashboard::strings.Set') : __('dashboard::strings.Not Set') }}
                    </flux:badge>
                    @if ($tokenIsSet)
                        <flux:button
                            type="button"
                            size="xs"
                            variant="ghost"
                            wire:click="clearToken"
                            wire:confirm="{{ __('dashboard::strings.Clear this setting?') }}"
                        >
                            {{ __('dashboard::strings.Clear') }}
                        </flux:button>
                    @endif
                </div>
            </div>
            <flux:input
                wire:model="token"
                type="password"
                x-bind:type="visible ? 'text' : 'password'"
                autocomplete="new-password"
                placeholder="{{ $tokenIsSet ? '••••••••••••' : '' }}"
            />
            <div class="mt-1 flex justify-end">
                <flux:button
                    type="button"
                    size="xs"
                    variant="ghost"
                    x-on:click="visible = ! visible"
                    x-text="visible ? @js(__('dashboard::strings.Hide')) : @js(__('dashboard::strings.Show'))"
                    x-bind:aria-label="visible ? @js(__('dashboard::strings.Hide Secret')) : @js(__('dashboard::strings.Show Secret'))"
                />
            </div>
            <flux:error name="token" />
        </flux:field>
    </flux:card>

    <flux:button
        type="submit"
        variant="primary"
    >{{ __('dashboard::strings.Save Settings') }}</flux:button>
</form>
