<?php

namespace Modules\Inventory\Livewire\Suppliers;

use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Modules\Dashboard\Services\CountryService;
use Modules\Inventory\Livewire\Forms\SupplierForm;
use Modules\Inventory\Traits\CityPicker;

class SupplierCreate extends Component
{
    use CityPicker;

    public SupplierForm $form;

    public function mount(): void
    {
        $countryService = resolve(CountryService::class);
        $this->countries = $countryService->getCountries(['is_active' => true], paginate: false);
    }

    public function save(): void
    {
        $this->form->save();
        Flux::toast(text: __('inventory::messages.Supplier Created Successfully'), variant: 'success');
        $this->redirectRoute('suppliers.index', navigate: true);
    }

    public function render(): View
    {
        return view('inventory::livewire.suppliers-create', [
            'countries' => $this->countries,
            'provinces' => $this->provinces,
            'cities' => $this->cities,
        ])
            ->layout('dashboard::layouts.app')
            ->layoutData([
                'title' => __('inventory::strings.Supplier Add'),
            ]);
    }
}
