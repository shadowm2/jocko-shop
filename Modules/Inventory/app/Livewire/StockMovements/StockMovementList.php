<?php

namespace Modules\Inventory\Livewire\StockMovements;

use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Inventory\Enums\StockMovementType;
use Modules\Inventory\Livewire\Concerns\InteractsWithTableFilters;
use Modules\Inventory\Services\StockMovementService;
use Modules\Inventory\Services\WarehouseService;

class StockMovementList extends Component
{
    use InteractsWithTableFilters;
    use WithPagination;

    /** @var array<string, mixed> */
    public array $filters = [
        'search' => '',
        'type' => '',
        'warehouse_id' => '',
    ];

    protected function defaultTableFilters(): array
    {
        return [
            'search' => '',
            'type' => '',
            'warehouse_id' => '',
        ];
    }

    public function render(): View
    {
        $multiWarehouse = resolve(WarehouseService::class)->isMultiple();
        $columns = [
            ['label' => __('inventory::attributes.Movement Date')],
            ['label' => __('inventory::attributes.Item Name')],
            ['label' => __('inventory::attributes.Movement Type')],
            ...($multiWarehouse ? [['label' => __('inventory::attributes.Movement Warehouse')]] : []),
            ['label' => __('inventory::attributes.Movement Quantity')],
            ['label' => __('inventory::attributes.Movement Balance After')],
            ['label' => __('inventory::attributes.Movement Reference')],
            ['label' => __('inventory::attributes.Movement User')],
        ];
        $rows = resolve(StockMovementService::class)->getStockMovements($this->filters);

        $filterDefinitions = [
            ['name' => 'search', 'type' => 'search', 'label' => __('inventory::strings.Search'), 'placeholder' => __('inventory::strings.Search Stock Movements')],
            ['name' => 'type', 'type' => 'select', 'label' => __('inventory::attributes.Movement Type'), 'placeholder' => __('strings.All'), 'options' => collect(StockMovementType::cases())
                ->mapWithKeys(fn (StockMovementType $type) => [$type->value => $type->label()])->all()],
            ...($multiWarehouse ? [['name' => 'warehouse_id', 'type' => 'select', 'label' => __('inventory::attributes.Movement Warehouse'), 'placeholder' => __('strings.All'), 'options' => resolve(WarehouseService::class)
                ->getWarehouses(paginate: false)->pluck('name', 'id')->all()]] : []),
        ];

        return view('inventory::livewire.stock-movements-list', compact('columns', 'rows', 'filterDefinitions'))
            ->layout('dashboard::layouts.app')
            ->layoutData([
                'title' => __('inventory::strings.Stock Movements'),
            ]);
    }
}
