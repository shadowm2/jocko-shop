<?php

namespace Modules\Dashboard\Livewire;

use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Modules\Dashboard\Livewire\Forms\ProvinceForm;
use Modules\Dashboard\Services\CountryService;

class ProvinceCreate extends Component
{
    public ProvinceForm $form;

    public function mount(): void {}

    public function save(): void
    {
        $this->form->save();
        Flux::toast(text: __('dashboard::messages.Province Created Successfully'), variant: 'success');
        $this->redirectRoute('provinces.index', navigate: true);
    }

    public function render(): View
    {
        $countryService = resolve(CountryService::class);
        $countries = $countryService->getCountries(filters: [
            'is_active' => true,
        ], paginate: false);

        return view('dashboard::livewire.provinces-create', [
            ...compact('countries'),
        ])
            ->layout('dashboard::layouts.app')
            ->layoutData([
                'title' => __('dashboard::strings.Province Add'),
            ]);
    }
}
