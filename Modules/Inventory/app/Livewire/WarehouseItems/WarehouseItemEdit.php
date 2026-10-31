<?php

namespace Modules\Inventory\Livewire\WarehouseItems;

use Exception;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Modules\Inventory\Livewire\Forms\WarehouseItemForm;
use Modules\Inventory\Models\Warehouse;
use Modules\Inventory\Models\WarehouseItem;
use Modules\Inventory\Services\ItemService;
use Modules\Inventory\Services\WarehouseService;

class WarehouseItemEdit extends Component
{
    public WarehouseItemForm $form;

    public Warehouse $warehouse;

    public WarehouseItem $warehouseItem;

    /**
     * @throws Exception
     */
    public function mount(): void
    {
        $this->form->setWarehouseItem($this->warehouseItem);
    }

    public function save(): void
    {
        $this->form->save();
        Flux::toast(text: __('inventory::messages.Warehouse Item Updated Successfully'), variant: 'success');
        $this->redirectRoute('warehouses.items.index', navigate: true);
    }

    public function render(): View
    {

        $itemService = resolve(ItemService::class);
        $warehouseService = resolve(WarehouseService::class);
        $items = $itemService->getItems(paginate: false);
        $warehouses = $warehouseService->getWarehouses(paginate: false);
        $currentWarehouse = $warehouseService->getActiveWarehouse();

        return view('inventory::livewire.warehouse-items-edit', [
            ...compact('items', 'currentWarehouse', 'warehouses'),
        ])
            ->layout('dashboard::layouts.app')
            ->layoutData([
                'title' => __('inventory::strings.Edit Warehouse Item'),
            ]);
    }
}
