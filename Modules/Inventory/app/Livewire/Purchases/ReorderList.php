<?php

namespace Modules\Inventory\Livewire\Purchases;

use Illuminate\Contracts\View\View;
use Livewire\Component;
use Modules\Inventory\Services\ReorderSuggestions;
use Modules\Inventory\Services\WarehouseService;

/**
 * Items running low, grouped by warehouse and usual supplier, each group a click
 * away from a purchase order with the lines already filled in.
 */
class ReorderList extends Component
{
    /**
     * Quantity to buy per warehouse item slug; starts at the suggestion and can be changed.
     *
     * @var array<string, float|string>
     */
    public array $quantities = [];

    public function mount(ReorderSuggestions $suggestions): void
    {
        foreach ($suggestions->groups() as $group) {
            foreach ($group['lines'] as $line) {
                $this->quantities[$line['warehouse_item']->slug] = $line['quantity'];
            }
        }
    }

    /**
     * Open the purchase order form with this group's lines filled in.
     */
    public function startPurchase(string $key, ReorderSuggestions $suggestions): void
    {
        $group = collect($suggestions->groups())->firstWhere('key', $key);

        if ($group === null) {
            return;
        }

        $lines = [];

        foreach ($group['lines'] as $line) {
            $slug = $line['warehouse_item']->slug;
            $quantity = $this->quantities[$slug] ?? $line['quantity'];
            $quantity = is_numeric($quantity) && (float) $quantity >= 1 ? (float) $quantity : $line['quantity'];

            $lines[$slug] = ['quantity' => $quantity, 'unit_price' => $line['unit_price']];
        }

        session()->put('purchase_prefill', [
            'warehouse' => $group['warehouse']->slug,
            'supplier' => $group['supplier']->slug ?? '',
            'lines' => $lines,
        ]);

        $this->redirectRoute('purchases.create', navigate: true);
    }

    public function render(ReorderSuggestions $suggestions): View
    {
        return view('inventory::livewire.reorder', [
            'groups' => $suggestions->groups(),
            'multipleWarehouses' => resolve(WarehouseService::class)->isMultiple(),
        ])
            ->layout('dashboard::layouts.app')
            ->layoutData(['title' => __('inventory::strings.Reorder List')]);
    }
}
