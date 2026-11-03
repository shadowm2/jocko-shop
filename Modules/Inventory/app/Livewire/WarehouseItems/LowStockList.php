<?php

namespace Modules\Inventory\Livewire\WarehouseItems;

use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Inventory\Livewire\Concerns\InteractsWithTableFilters;
use Modules\Inventory\Services\WarehouseItemService;
use Modules\Inventory\Services\WarehouseService;

class LowStockList extends Component
{
    use InteractsWithTableFilters;
    use WithPagination;

    /** @var array<string, mixed> */
    public array $filters = [
        'search' => '',
        'warehouse_id' => '',
    ];

    protected function defaultTableFilters(): array
    {
        return [
            'search' => '',
            'warehouse_id' => '',
        ];
    }

    public function render(): View
    {
        $multiWarehouse = resolve(WarehouseService::class)->isMultiple();
        $columns = [
            ...($multiWarehouse ? [['label' => __('inventory::attributes.Warehouse')]] : []),
            ['label' => __('inventory::attributes.Item Name')],
            ['label' => __('inventory::attributes.Warehouse Item Quantity')],
            ['label' => __('inventory::attributes.Warehouse Item Min Quantity')],
            ['label' => __('inventory::attributes.Stock Shortfall')],
            ['label' => ''],
        ];
        $rows = resolve(WarehouseItemService::class)->getWarehouseItems([
            // The filter applies every key it is given, so leave out the empty ones.
            ...array_filter($this->filters, fn (string $value): bool => $value !== ''),
            'low_stock' => true,
        ]);

        $filterDefinitions = [
            ['name' => 'search', 'type' => 'search', 'label' => __('inventory::strings.Search'), 'placeholder' => __('inventory::strings.Search Low Stock')],
            ...($multiWarehouse ? [['name' => 'warehouse_id', 'type' => 'select', 'label' => __('inventory::attributes.Warehouse'), 'placeholder' => __('strings.All'), 'options' => resolve(WarehouseService::class)
                ->getWarehouses(paginate: false)->pluck('name', 'id')->all()]] : []),
        ];

        return view('inventory::livewire.low-stock-list', compact('columns', 'rows', 'filterDefinitions'))
            ->layout('dashboard::layouts.app')
            ->layoutData([
                'title' => __('inventory::strings.Low Stock'),
            ]);
    }
}
