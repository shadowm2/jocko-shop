<?php

namespace Modules\Order\Livewire\Concerns;

use Flux\Flux;
use Livewire\Attributes\On;
use Modules\Inventory\Services\BarcodeService;
use Modules\Inventory\Services\WarehouseService;
use Modules\Order\Livewire\Forms\OrderForm;

/**
 * Wires the Inventory warehouse item picker to the order form's lines.
 */
trait ManagesOrderItems
{
    public OrderForm $form;

    /** What a barcode scanner (or the keyboard) typed into the scan box. */
    public string $barcode = '';

    #[On('warehouse-selected')]
    public function handleWarehouseSelected(?string $warehouse): void
    {
        $this->form->warehouse = $warehouse ?? '';
    }

    /** @param array<int, string> $slugs */
    #[On('warehouse-items-selected')]
    public function handleItemsSelected(array $slugs): void
    {
        $this->form->selected = array_values($slugs);
        $this->form->syncSelectedItems();
    }

    #[On('warehouse-item-picker-updated')]
    public function itemPickerUpdated(): void
    {
        $this->form->syncSelectedItems();
    }

    /**
     * Add the item a scanned code stands for. A scanner types the code and presses Enter.
     */
    public function scanBarcode(BarcodeService $barcodes): void
    {
        $code = trim($this->barcode);
        $this->barcode = '';

        if ($code === '') {
            return;
        }

        $warehouse = filled($this->form->warehouse) ? $this->form->warehouse : resolve(WarehouseService::class)->fixedWarehouse()?->slug;
        $result = $barcodes->lookup($code, (string) $warehouse);

        if ($result['status'] !== 'found') {
            Flux::toast(__($result['status'] === 'unknown' ? 'order::strings.Barcode Unknown' : 'order::strings.Barcode Not Stocked', ['code' => $code]), variant: 'warning');

            return;
        }

        $this->form->addScanned($result['warehouse_item']->slug, $result['sale_price']);
        Flux::toast(__('order::strings.Barcode Added', ['item' => $result['warehouse_item']->item->name]), variant: 'success');
    }

    public function addService(): void
    {
        $this->form->addService();
    }

    public function removeService(string $slug): void
    {
        $this->form->removeService($slug);
    }

    public function removeOrderItem(string $slug): void
    {
        $this->form->removeOrderItem($slug);
    }
}
