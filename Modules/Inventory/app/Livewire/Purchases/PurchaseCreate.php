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
        $this->applyReorderPrefill();
        $this->form->useFixedWarehouse();
    }

    /**
     * Lines handed over by the reorder list: the supplier, the warehouse and each
     * item with the quantity and last price. Taken once, so a reload starts blank.
     */
    private function applyReorderPrefill(): void
    {
        $prefill = session()->pull('purchase_prefill');

        if (! is_array($prefill) || ! is_array($prefill['lines'] ?? null)) {
            return;
        }

        $this->form->warehouse = (string) ($prefill['warehouse'] ?? '');
        $this->form->supplier = (string) ($prefill['supplier'] ?? '');
        $this->form->selected = array_keys($prefill['lines']);
        $this->form->items = $prefill['lines'];
        $this->form->syncSelectedItems();
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
