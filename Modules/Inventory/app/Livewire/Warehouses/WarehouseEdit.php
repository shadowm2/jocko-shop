<?php

namespace Modules\Inventory\Livewire\Warehouses;

use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Modules\Inventory\Livewire\Forms\WarehouseForm;
use Modules\Inventory\Models\Warehouse;

class WarehouseEdit extends Component
{
    public WarehouseForm $form;

    public Warehouse $warehouse;

    public function mount(): void
    {
        $this->form->setWarehouse($this->warehouse);
    }

    public function save(): void
    {
        $this->form->save();
        Flux::toast(text: __('inventory::messages.Warehouse Updated Successfully'), variant: 'success');
        $this->redirectRoute('warehouses.index', navigate: true);
    }

    public function render(): View
    {

        return view('inventory::livewire.warehouses-edit', [])
            ->layout('dashboard::layouts.app')
            ->layoutData([
                'title' => __('inventory::strings.Edit Warehouse'),
            ]);
    }
}
