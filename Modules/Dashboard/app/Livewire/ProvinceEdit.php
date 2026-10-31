<?php

namespace Modules\Dashboard\Livewire;

use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Modules\Dashboard\Livewire\Forms\ProvinceForm;
use Modules\Dashboard\Models\Province;
use Modules\Dashboard\Services\CountryService;

class ProvinceEdit extends Component
{
    public ProvinceForm $form;

    public Province $province;

    public function mount(): void
    {
        $this->form->setProvince($this->province);
    }

    public function save(): void
    {
        $this->form->save();
        Flux::toast(text: __('dashboard::messages.Province Updated Successfully'), variant: 'success');
        $this->redirectRoute('provinces.index', navigate: true);
    }

    public function render(): View
    {
        $countryService = resolve(CountryService::class);
        $countries = $countryService->getCountries([
            'is_active' => true,
        ], paginate: false);

        return view('dashboard::livewire.provinces-edit', [
            ...compact('countries'),
        ])
            ->layout('dashboard::layouts.app')
            ->layoutData([
                'title' => __('dashboard::strings.Edit Province'),
            ]);
    }
}
