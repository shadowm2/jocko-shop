<?php

namespace Modules\Order\Livewire\Concerns;

use Livewire\Attributes\On;
use Modules\Order\Livewire\Forms\OrderForm;

/**
 * Wires the Inventory warehouse item picker to the order form's lines.
 */
trait ManagesOrderItems
{
    public OrderForm $form;

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
