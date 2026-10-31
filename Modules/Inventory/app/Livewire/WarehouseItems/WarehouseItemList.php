<?php

namespace Modules\Inventory\Livewire\WarehouseItems;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Inventory\Livewire\Concerns\InteractsWithTableFilters;
use Modules\Inventory\Livewire\Concerns\InteractsWithTableSorting;
use Modules\Inventory\Services\WarehouseItemService;
use Modules\Inventory\Services\WarehouseService;

class WarehouseItemList extends Component
{
    use InteractsWithTableFilters;
    use InteractsWithTableSorting;
    use WithPagination;

    private const FILTERS = [
        'search' => '',
    ];

    /** @var array<string, mixed> */
    public array $filters = self::FILTERS;

    public string $sortBy = 'item';

    public string $sortDirection = 'asc';

    protected function defaultTableFilters(): array
    {
        return self::FILTERS;
    }

    /** @return array<int, array<string, mixed>> */
    private function filterDefinitions(): array
    {
        return [
            ['name' => 'search', 'type' => 'search', 'label' => __('inventory::strings.Search'), 'placeholder' => __('inventory::strings.Search Warehouse Items')],
        ];
    }

    /** @return array<string, string> */
    private function sortOptions(): array
    {
        return ['item' => __('inventory::attributes.Item Name'), 'quantity' => __('inventory::attributes.Warehouse Item Quantity')];
    }

    #[On('active-warehouse-changed')]
    public function onWarehouseSelect(string $warehouse): void {}

    public function render(): View
    {
        $warehouseService = resolve(WarehouseService::class);
        $warehouseItemService = resolve(WarehouseItemService::class);
        $warehouse = $warehouseService->getActiveWarehouse();
        $rows = $warehouseItemService->getItemsInWarehouse($warehouse, [...$this->filters, 'sort_by' => $this->sortBy, 'sort_direction' => $this->sortDirection]);

        $columns = [
            [
                'label' => __('inventory::attributes.Item Name'),
            ],
            [
                'label' => __('inventory::attributes.Warehouse Item Quantity'),
            ],
            [
                'label' => __('inventory::attributes.Warehouse Item Min Quantity'),
            ],
            [
                'label' => __('inventory::attributes.Warehouse Item Max Quantity'),
            ],
            [
                'label' => __('inventory::attributes.Stock Status'),
            ],
            [
                'label' => '',
            ],
        ];

        return view('inventory::livewire.warehouse-items-list', [
            ...compact('columns', 'rows', 'warehouse'),
            'filterDefinitions' => $this->filterDefinitions(),
            'sortOptions' => $this->sortOptions(),
        ])
            ->layout('dashboard::layouts.app')
            ->layoutData([
                'title' => __('inventory::strings.Warehouse Items List'),
            ]);
    }
}
