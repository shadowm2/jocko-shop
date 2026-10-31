<?php

namespace Modules\Inventory\Livewire\Units;

use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Modules\Inventory\Livewire\Forms\UnitForm;
use Modules\Inventory\Services\UnitGroupService;

class UnitCreate extends Component
{
    public UnitForm $form;

    public function mount(): void {}

    public function save(): void
    {
        $this->form->save();
        Flux::toast(text: __('inventory::messages.Unit Created Successfully'), variant: 'success');
        $this->redirectRoute('units.index', navigate: true);
    }

    public function render(UnitGroupService $unitGroupService): View
    {
        $unitGroups = $unitGroupService->all();

        return view('inventory::livewire.units-create', [
            ...compact('unitGroups'),
        ])
            ->layout('dashboard::layouts.app')
            ->layoutData([
                'title' => __('inventory::strings.Unit Add'),
            ]);
    }
}
