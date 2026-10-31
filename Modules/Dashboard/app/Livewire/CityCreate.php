<?php

namespace Modules\Dashboard\Livewire;

use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Component;
use Modules\Dashboard\Livewire\Forms\CityForm;
use Modules\Dashboard\Services\CountryService;
use Modules\Dashboard\Services\ProvinceService;

class CityCreate extends Component
{
    public CityForm $form;

    public function save(): void
    {
        $this->form->save();
        Flux::toast(text: __('dashboard::messages.City Created Successfully'), variant: 'success');
        $this->redirectRoute('cities.index', navigate: true);
    }

    public function render(): View
    {
        $countryService = resolve(CountryService::class);
        $provinceService = resolve(ProvinceService::class);

        $countryFilters = [
            'is_active' => true,
        ];
        $countries = $countryService->getCountries(filters: $countryFilters);
        //        if (! $this->form->country) {
        //            $this->form->country = $countries->first()?->code;
        //        }

        $provinceFilters = [
            'is_active' => true,
        ];
        if ($this->form->country) {
            $provinceFilters['country'] = $this->form->country;
            $provinces = $provinceService->getProvinces($provinceFilters, paginate: false);
        } else {
            $provinces = new LengthAwarePaginator([], 0, 15);
        }
        $this->form->province = $provinces->first()->slug ?? '';

        return view('dashboard::livewire.cities-create', [
            ...compact('provinces', 'countries'),
        ])
            ->layout('dashboard::layouts.app')
            ->layoutData([
                'title' => __('dashboard::strings.City Create'),
            ]);
    }
}
