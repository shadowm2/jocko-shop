<?php

namespace Modules\Dashboard\Livewire;

use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Modules\Dashboard\Livewire\Forms\CityForm;
use Modules\Dashboard\Models\City;
use Modules\Dashboard\Services\CountryService;
use Modules\Dashboard\Services\ProvinceService;

class CityEdit extends Component
{
    public CityForm $form;

    public City $city;

    public function mount(): void
    {
        $this->form->setCity($this->city);
    }

    public function save(): void
    {
        $this->form->save();
        Flux::toast(text: __('dashboard::messages.City Updated Successfully'), variant: 'success');
        $this->redirectRoute('cities.index', navigate: true);
    }

    public function render(): View
    {
        $countryService = resolve(CountryService::class);
        $provinceService = resolve(ProvinceService::class);
        $countries = $countryService->getCountries(paginate: false);
        $provinceFilters = [];
        if ($this->form->country) {
            $provinceFilters['country'] = $this->form->country;
        }
        $provinces = $provinceService->getProvinces(filters: $provinceFilters, paginate: false);

        return view('dashboard::livewire.cities-edit', [
            ...compact('countries', 'provinces'),
        ])
            ->layout('dashboard::layouts.app')
            ->layoutData([
                'title' => __('dashboard::strings.Edit City'),
            ]);
    }
}
