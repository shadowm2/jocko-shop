<?php

namespace Modules\Inventory\Livewire\StockMovements;

use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Modules\Inventory\Livewire\Forms\StockTransferForm;
use Modules\Inventory\Services\WarehouseItemService;
use Modules\Inventory\Services\WarehouseService;

class StockTransferCreate extends Component
{
    public StockTransferForm $form;

    public function mount(): void
    {
        // A transfer is between two warehouses; with one in use there is nothing to transfer between.
        abort_unless(resolve(WarehouseService::class)->isMultiple(), 404);
    }

    public function save(): void
    {
        $this->form->save();
        Flux::toast(text: __('inventory::messages.Stock Transfer Recorded Successfully'), variant: 'success');
        $this->redirectRoute('stock-movements.index', navigate: true);
    }

    public function render(): View
    {
        $warehouseItems = resolve(WarehouseItemService::class)->getWarehouseItems(paginate: false);
        $sourceWarehouseId = $warehouseItems->firstWhere('slug', $this->form->warehouse_item)?->warehouse_id;
        $warehouses = resolve(WarehouseService::class)->getWarehouses(paginate: false)
            ->reject(fn ($warehouse) => $warehouse->id === $sourceWarehouseId);

        return view('inventory::livewire.stock-transfer-create', compact('warehouseItems', 'warehouses'))
            ->layout('dashboard::layouts.app')
            ->layoutData([
                'title' => __('inventory::strings.Stock Transfer'),
            ]);
    }
}
