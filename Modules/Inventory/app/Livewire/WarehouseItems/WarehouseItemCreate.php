<?php

namespace Modules\Inventory\Livewire\WarehouseItems;

use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Modules\Inventory\Livewire\Forms\WarehouseItemForm;
use Modules\Inventory\Services\ItemService;
use Modules\Inventory\Services\WarehouseService;

class WarehouseItemCreate extends Component
{
    public WarehouseItemForm $form;

    public function mount(): void
    {
        $warehouseService = resolve(WarehouseService::class);
        $warehouse = $warehouseService->getActiveWarehouse();

        if ($warehouse) {
            $this->form->setActiveWarehouse($warehouse);
        }
    }

    /**
     * @throws \Exception
     */
    public function save(): void
    {
        $this->form->save();
        Flux::toast(text: __('inventory::messages.Warehouse Item Created Successfully'), variant: 'success');
        $this->redirectRoute('warehouses.items.index', navigate: true);
    }

    public function render(): View
    {
        $itemService = resolve(ItemService::class);
        $warehouseService = resolve(WarehouseService::class);
        $items = $itemService->getItems(paginate: false);
        $currentWarehouse = $warehouseService->getActiveWarehouse();
        $warehouses = $warehouseService->getWarehouses(paginate: false);

        return view('inventory::livewire.warehouse-items-create', [
            ...compact('items', 'currentWarehouse', 'warehouses'),
        ])
            ->layout('dashboard::layouts.app')
            ->layoutData([
                'title' => __('inventory::strings.Warehouse Item Add'),
            ]);
    }
}
