<?php

namespace Modules\Inventory\Livewire\UnitGroups;

use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Modules\Inventory\Livewire\Forms\UnitGroupForm;
use Modules\Inventory\Models\UnitGroup;
use Modules\Inventory\Services\UnitService;

class UnitGroupEdit extends Component
{
    public UnitGroupForm $form;

    public UnitGroup $unitGroup;

    public function mount(): void
    {
        $this->form->setUnitGroup($this->unitGroup);
    }

    public function save(): void
    {
        $this->form->save();
        Flux::toast(text: __('inventory::messages.Unit Group Updated Successfully'), variant: 'success');
        $this->redirectRoute('units.groups.index', navigate: true);
    }

    public function render(UnitService $unitService): View
    {
        return view('inventory::livewire.unit-group-edit', [
            'unitGroup' => $this->unitGroup,
        ])
            ->layout('dashboard::layouts.app')
            ->layoutData([
                'title' => __('inventory::strings.Edit Unit Group'),
            ]);
    }
}
