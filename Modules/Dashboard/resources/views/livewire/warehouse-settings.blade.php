<form
    class="w-full max-w-2xl space-y-6"
    wire:submit="save"
>
    <div>
        <flux:heading
            size="xl"
            level="1"
        >{{ __('dashboard::strings.Warehouse Settings') }}</flux:heading>
        <flux:subheading>{{ __('dashboard::strings.Warehouse Settings Description') }}</flux:subheading>
    </div>

    <flux:card class="space-y-6">
        <flux:field variant="inline">
            <flux:switch wire:model.live="multiple" />
            <flux:label>{{ __('dashboard::strings.Multiple Warehouses') }}</flux:label>
            <flux:description>{{ __('dashboard::strings.Multiple Warehouses Description') }}</flux:description>
            <flux:error name="multiple" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('dashboard::strings.Default Warehouse') }}</flux:label>
            <flux:select
                wire:model="default"
                :placeholder="__('dashboard::strings.Select Default Warehouse')"
            >
                @foreach ($warehouseOptions as $warehouse)
                    <flux:select.option :value="(string) $warehouse->id">{{ $warehouse->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:description>
                {{ $multiple ? __('dashboard::strings.Default Warehouse Description Multiple') : __('dashboard::strings.Default Warehouse Description Single') }}
            </flux:description>
            <flux:error name="default" />
        </flux:field>

        @if ($warehouseOptions->isEmpty())
            <flux:callout
                icon="exclamation-triangle"
                variant="warning"
            >
                <flux:callout.text>
                    {{ __('dashboard::strings.No Warehouses Yet') }}
                    <flux:link :href="route('warehouses.create')">{{ __('inventory::strings.Warehouse Add') }}</flux:link>
                </flux:callout.text>
            </flux:callout>
        @endif
    </flux:card>

    <flux:button
        type="submit"
        variant="primary"
    >{{ __('dashboard::strings.Save Settings') }}</flux:button>
</form>
