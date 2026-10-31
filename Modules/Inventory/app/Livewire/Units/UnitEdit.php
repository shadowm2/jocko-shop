<?php

namespace Modules\Inventory\Livewire\Units;

use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Modules\Inventory\Livewire\Forms\UnitForm;
use Modules\Inventory\Models\Unit;
use Modules\Inventory\Services\UnitGroupService;

class UnitEdit extends Component
{
    public UnitForm $form;

    public Unit $unit;

    public function mount(): void
    {
        $this->form->setUnit($this->unit);
    }

    public function save(): void
    {
        $this->form->save();
        Flux::toast(text: __('inventory::messages.Unit Updated Successfully'), variant: 'success');
        $this->redirectRoute('units.index', navigate: true);
    }

    public function render(UnitGroupService $unitGroupService): View
    {
        $unitGroups = $unitGroupService->getUnitGroups();

        return view('inventory::livewire.units-edit', [
            'unit' => $this->unit,
            'unitGroups' => $unitGroups,
        ])
            ->layout('dashboard::layouts.app')
            ->layoutData([
                'title' => __('inventory::strings.Edit Unit'),
            ]);
    }
}
