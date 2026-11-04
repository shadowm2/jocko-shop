@props(['form', 'itemCatalog' => []])
<flux:field>
    <flux:label>{{ __('order::strings.Scan Barcode') }}</flux:label>
    <flux:input
        wire:model="barcode"
        wire:keydown.enter.prevent="scanBarcode"
        icon="qr-code"
        dir="ltr"
        autocomplete="off"
        :placeholder="__('order::strings.Scan Barcode Hint')"
        data-scan-barcode
    />
</flux:field>

<livewire:inventory-warehouse-item-picker
    :warehouse="$form->warehouse"
    :show-warehouse-picker="$form->showsWarehousePicker()"
    wire:model="form.selected"
    :label="__('order::attributes.Warehouse Item')"
    :placeholder="__('inventory::strings.Select Warehouse Item')"
    wire:key="order-item-picker"
>
    <x-slot:warehouse-error>
        <flux:error name="form.warehouse" />
    </x-slot:warehouse-error>
</livewire:inventory-warehouse-item-picker>

<flux:error name="form.selected" />

@if (count($itemCatalog))
    <flux:field>
        <flux:label>{{ __('order::strings.Order Items') }}</flux:label>

        <div class="max-h-[55vh] space-y-1.5 overflow-auto pe-1">
            <div
                class="w-fit min-w-full grid-cols-[minmax(10rem,1fr)_10rem_7rem_9rem_8rem_2rem] items-center gap-2 rounded-lg
                border border-zinc-200 bg-zinc-50 px-2 py-1 text-xs font-medium text-zinc-500 dark:border-white/10
                dark:bg-white/5 dark:text-zinc-400 sm:grid">
                <span>{{ __('order::attributes.Warehouse Item') }}</span>
                <span>{{ __('order::attributes.Unit') }}</span>
                <span>{{ __('order::attributes.Quantity') }}</span>
                <span>{{ __('order::attributes.Unit Price') }}</span>
                <span>{{ __('order::attributes.Total') }}</span>
                <span></span>
            </div>

            @foreach ($itemCatalog as $slug => $details)
                <div
                    wire:key="order-line-{{ $slug }}"
                    x-data="numericLine"
                    @localized-digits-input="setValue($event)"
                    class="w-fit min-w-full grid grid-cols-2 gap-x-2 gap-y-1.5 rounded-lg border border-zinc-200 p-2
                    dark:border-white/10 sm:grid-cols-[minmax(10rem,1fr)_10rem_7rem_9rem_8rem_2rem] sm:items-center"
                >
                    <div>
                        <div class="truncate text-sm font-medium">{{ $details['name'] }}</div>
                        <div class="text-xs text-zinc-500">
                            {{ __('order::attributes.In Stock') }}:
                            {{ \App\Helpers\Utils::formatQuantity($details['quantity'], thousandsSeparator: ',') }}
                        </div>
                    </div>

                    <div class="min-w-0">
                        <flux:select
                            size="sm"
                            wire:model="form.items.{{ $slug }}.unit_id"
                            :aria-label="__('order::attributes.Unit') . ' — ' . $details['name']"
                            :placeholder="__('order::strings.Select Unit')"
                        >
                            @foreach ($details['units'] as $unit)
                                <flux:select.option :value="$unit['id']">
                                    {{ $unit['name'] }} ({{ $unit['symbol'] }})
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>

                    <div class="min-w-0">
                        <x-dashboard::numeric-input
                            size="sm"
                            data-numeric-field="quantity"
                            wire:model.number="form.items.{{ $slug }}.quantity"
                            :error="'form.items.' . $slug . '.quantity'"
                            :placeholder="__('order::attributes.Quantity')"
                            :aria-label="__('order::attributes.Quantity') . ' — ' . $details['name']"
                        />
                    </div>

                    <div class="min-w-0">
                        <x-dashboard::numeric-input
                            size="sm"
                            data-numeric-field="unit_price"
                            wire:model.number="form.items.{{ $slug }}.unit_price"
                            :error="'form.items.' . $slug . '.unit_price'"
                            :placeholder="__('order::attributes.Unit Price')"
                            :aria-label="__('order::attributes.Unit Price') . ' — ' . $details['name']"
                        />
                    </div>

                    <div
                        class="flex items-center justify-between gap-2 text-sm sm:block sm:text-right bg-gray-600/50 rounded-sm p-1 px-2">
                        <span class="text-xs text-zinc-500 sm:hidden">{{ __('order::attributes.Total') }}</span>
                        <span
                            class="tabular-nums"
                            aria-live="polite"
                            x-text="total"
                        >
                            {{ \App\Helpers\Utils::formatQuantity($form->lineTotal($slug), 2, thousandsSeparator: ',') }}
                        </span>
                    </div>

                    <flux:button
                        type="button"
                        wire:click="removeOrderItem('{{ $slug }}')"
                        variant="ghost"
                        size="sm"
                        icon="x-mark"
                        class="justify-self-center"
                        :aria-label="__('order::strings.Remove') . ' ' . $details['name']"
                    />
                </div>
            @endforeach
        </div>
    </flux:field>
@else
    <p class="text-sm text-zinc-500">{{ __('order::strings.No items found.') }}</p>
@endif
