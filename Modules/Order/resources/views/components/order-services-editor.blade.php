@props(['form'])
@php
    use App\Helpers\Utils;

    $options = $form->serviceOptions();
    $mechanics = $form->mechanicOptions();
    $money = fn ($value) => Utils::pDigits(Utils::formatQuantity($value, 2, ','));
@endphp
{{-- Labour and other services: no stock, so they come from their own list, not from a warehouse. --}}
<flux:field data-services-editor>
    <flux:label>{{ __('order::strings.Services') }}</flux:label>

    @if ($options === [])
        <flux:text size="sm" class="text-zinc-500">{{ __('order::strings.No Services Defined') }}</flux:text>
    @else
        <div class="flex gap-2">
            <div class="min-w-0 flex-1">
                <flux:select
                    wire:model="form.serviceToAdd"
                    :placeholder="__('order::strings.Select Service')"
                    data-service-select
                >
                    @foreach ($options as $slug => $name)
                        @unless (isset($form->services[$slug]))
                            <flux:select.option :value="$slug">{{ $name }}</flux:select.option>
                        @endunless
                    @endforeach
                </flux:select>
            </div>
            <flux:button type="button" wire:click="addService" icon="plus" data-add-service>{{ __('order::strings.Add Service') }}</flux:button>
        </div>
    @endif

    @foreach ($form->services as $slug => $line)
        <div
            wire:key="order-service-{{ $slug }}"
            class="grid grid-cols-2 items-center gap-2 rounded-lg border border-zinc-200 p-2 dark:border-white/10 sm:grid-cols-[minmax(10rem,1fr)_6rem_8rem_8rem_10rem_2rem]"
            data-service-line="{{ $slug }}"
        >
            <div class="truncate text-sm font-medium">{{ $options[$slug] ?? $slug }}</div>
            <flux:input size="sm" wire:model.live.debounce.400ms="form.services.{{ $slug }}.quantity" :aria-label="__('order::attributes.Quantity')" inputmode="decimal" dir="ltr" />
            <flux:input size="sm" wire:model.live.debounce.400ms="form.services.{{ $slug }}.unit_price" :aria-label="__('order::attributes.Unit Price')" inputmode="decimal" dir="ltr" />
            <div class="text-sm tabular-nums" data-service-total="{{ $form->serviceTotal($slug) }}">{{ $money($form->serviceTotal($slug)) }}</div>
            <flux:select size="sm" wire:model="form.services.{{ $slug }}.mechanic_id" :placeholder="__('order::strings.Mechanic')">
                @foreach ($mechanics as $id => $name)
                    <flux:select.option :value="$id">{{ $name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:button type="button" size="sm" variant="ghost" icon="trash" wire:click="removeService('{{ $slug }}')" :aria-label="__('order::strings.Remove')" />
            <flux:error name="form.services.{{ $slug }}.quantity" />
            <flux:error name="form.services.{{ $slug }}.unit_price" />
        </div>
    @endforeach
</flux:field>
