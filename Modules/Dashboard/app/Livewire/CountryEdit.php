<?php

namespace Modules\Dashboard\Livewire;

use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Modules\Dashboard\Livewire\Forms\CountryForm;
use Modules\Dashboard\Models\Country;

class CountryEdit extends Component
{
    public CountryForm $form;

    public Country $country;

    public function mount(): void
    {
        $this->form->setCountry($this->country);
    }

    public function save(): void
    {
        $this->form->save();
        Flux::toast(text: __('dashboard::messages.Country Updated Successfully'), variant: 'success');
        $this->redirectRoute('countries.index', navigate: true);
    }

    public function render(): View
    {
        return view('dashboard::livewire.countries-edit', [
            'unit' => $this->country,
        ])
            ->layout('dashboard::layouts.app')
            ->layoutData([
                'title' => __('dashboard::strings.Edit Country'),
            ]);
    }
}
