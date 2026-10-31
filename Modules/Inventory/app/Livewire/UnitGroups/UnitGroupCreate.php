<?php

namespace Modules\Inventory\Livewire\UnitGroups;

use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Modules\Inventory\Livewire\Forms\UnitGroupForm;
use Modules\Inventory\Services\UnitService;

class UnitGroupCreate extends Component
{
    public UnitGroupForm $form;

    public function save(): void
    {
        $this->form->save();
        Flux::toast(text: __('inventory::messages.Unit Group Created Successfully'), variant: 'success');
        $this->redirectRoute('units.groups.index', navigate: true);
    }

    public function render(UnitService $unitService): View
    {
        $columns = [
            [
                'label' => __('inventory::strings.Unit Name'),
            ],
        ];
        $rows = $unitService->getUnits();

        return view('inventory::livewire.unit-group-create', [
            ...compact('columns', 'rows'),
        ])
            ->layout('dashboard::layouts.app')
            ->layoutData([
                'title' => __('inventory::strings.Unit Group Add'),
            ]);
    }
}
