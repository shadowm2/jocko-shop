@php
    use App\Helpers\Utils;
@endphp
<div class="flex w-full max-w-4xl flex-col gap-5">
    <div>
        <flux:heading size="xl" level="1">{{ __('inventory::strings.Reorder List') }}</flux:heading>
        <flux:subheading>{{ __('inventory::strings.Reorder List Description') }}</flux:subheading>
    </div>

    @forelse ($groups as $group)
        <flux:card class="space-y-3" wire:key="reorder-{{ $group['key'] }}" data-reorder-group="{{ $group['key'] }}">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <flux:heading size="lg">
                        {{ $group['supplier'] ? $group['supplier']->user->fullName() : __('inventory::strings.Reorder No Usual Supplier') }}
                    </flux:heading>
                    @if ($multipleWarehouses)
                        <flux:text size="sm" class="text-zinc-500">{{ $group['warehouse']->name }}</flux:text>
                    @endif
                </div>
                <flux:button variant="primary" icon="shopping-cart" wire:click="startPurchase('{{ $group['key'] }}')" data-start-purchase>{{ __('inventory::strings.Reorder Start Purchase') }}</flux:button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-zinc-500">
                            <th class="py-1 text-start font-normal">{{ __('inventory::attributes.Item Name') }}</th>
                            <th class="py-1 text-start font-normal">{{ __('inventory::attributes.Warehouse Item Quantity') }}</th>
                            <th class="py-1 text-start font-normal">{{ __('inventory::attributes.Warehouse Item Min Quantity') }}</th>
                            <th class="py-1 text-start font-normal">{{ __('inventory::strings.Reorder Quantity To Buy') }}</th>
                            <th class="py-1 text-start font-normal">{{ __('inventory::strings.Reorder Last Price') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($group['lines'] as $line)
                            @php($warehouseItem = $line['warehouse_item'])
                            <tr class="border-t border-zinc-100 dark:border-zinc-800" wire:key="reorder-line-{{ $warehouseItem->slug }}" data-reorder-line="{{ $warehouseItem->slug }}">
                                <td class="py-1.5 font-medium">{{ $warehouseItem->item->name }}</td>
                                <td class="py-1.5 text-red-600 dark:text-red-400">{{ Utils::pDigits(Utils::formatQuantity($warehouseItem->quantity, 3, ',')) }}</td>
                                <td class="py-1.5">{{ Utils::pDigits(Utils::formatQuantity($warehouseItem->min_quantity, 3, ',')) }}</td>
                                <td class="w-32 py-1.5">
                                    <flux:input size="sm" type="number" min="1" step="any" dir="ltr" wire:model="quantities.{{ $warehouseItem->slug }}" />
                                </td>
                                <td class="py-1.5">{{ $line['unit_price'] > 0 ? Utils::pDigits(number_format($line['unit_price'])) : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </flux:card>
    @empty
        <flux:card>
            <flux:text class="py-8 text-center text-zinc-500" data-reorder-empty>{{ __('inventory::strings.Reorder Nothing Low') }}</flux:text>
        </flux:card>
    @endforelse

    <flux:text size="sm" class="text-zinc-500">{{ __('inventory::strings.Reorder Hint') }}</flux:text>
</div>
