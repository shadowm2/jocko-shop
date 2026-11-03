<?php

namespace Modules\Inventory\Livewire\Purchases;

use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Modules\Inventory\Livewire\Purchases\Concerns\RemovesPurchaseItems;
use Modules\Inventory\Livewire\Purchases\Concerns\UsesWarehouseItemPicker;
use Modules\Inventory\Services\SupplierService;

class PurchaseCreate extends Component
{
    use RemovesPurchaseItems;
    use UsesWarehouseItemPicker;

    public function mount(): void
    {
        $this->form->useFixedWarehouse();
    }

    public function save(): void
    {
        if (! $this->form->save()) {
            return;
        }

        Flux::toast(text: __('inventory::messages.Purchase Order Created Successfully'), variant: 'success');
        $this->redirectRoute('purchases.index', navigate: true);
    }

    public function render(): View
    {
        $supplierService = resolve(SupplierService::class);
        $suppliers = $supplierService->getSuppliers(paginate: false);

        return view('inventory::livewire.purchases-create', [
            ...compact('suppliers'),
            'itemCatalog' => $this->form->selectedCatalog(),
        ])
            ->layout('dashboard::layouts.app')
            ->layoutData([
                'title' => __('inventory::strings.Purchase Add'),
            ]);
    }
}
