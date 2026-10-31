<?php

namespace Modules\Inventory\Livewire\Suppliers;

use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Modules\Dashboard\Services\CountryService;
use Modules\Inventory\Livewire\Forms\SupplierForm;
use Modules\Inventory\Models\Supplier;
use Modules\Inventory\Traits\CityPicker;

class SupplierEdit extends Component
{
    use CityPicker;

    public SupplierForm $form;

    public Supplier $supplier;

    public function mount(): void
    {
        $this->form->setSupplier($this->supplier);
        $countryService = resolve(CountryService::class);
        $this->countries = $countryService->getCountries(['is_active' => true], paginate: false);

        $city = $this->supplier->city;
        if ($city) {
            $this->setCountry($city->province->country->code);
            $this->setProvince($city->province->slug);
            $this->setCity($city->slug);
        }
    }

    public function save(): void
    {
        $this->form->save();
        Flux::toast(text: __('inventory::messages.Supplier Updated Successfully'), variant: 'success');
        $this->redirectRoute('suppliers.index', navigate: true);
    }

    public function render(): View
    {
        return view('inventory::livewire.suppliers-edit', [
            'countries' => $this->countries,
            'provinces' => $this->provinces,
            'cities' => $this->cities,
        ])
            ->layout('dashboard::layouts.app')
            ->layoutData([
                'title' => __('inventory::strings.Edit Supplier'),
            ]);
    }
}
