<?php

namespace Modules\Inventory\Livewire\Warehouses;

use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Modules\Inventory\Livewire\Forms\WarehouseForm;

class WarehouseCreate extends Component
{
    public WarehouseForm $form;

    public function save(): void
    {
        $this->form->save();
        Flux::toast(text: __('inventory::messages.Warehouse Created Successfully'), variant: 'success');
        $this->redirectRoute('warehouses.index', navigate: true);
    }

    public function render(): View
    {
        return view('inventory::livewire.warehouses-create', [
        ])
            ->layout('dashboard::layouts.app')
            ->layoutData([
                'title' => __('inventory::strings.Warehouse Add'),
            ]);
    }
}
