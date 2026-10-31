<?php

namespace Modules\Car\Livewire;

use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithFileUploads;
use Modules\Car\Livewire\Forms\CarForm;
use Modules\Car\Services\CarCompanyService;

class CarCreate extends Component
{
    use WithFileUploads;

    public CarForm $form;

    public function save(): void
    {
        $this->form->save();
        Flux::toast(text: __('car::messages.Car Created Successfully'), variant: 'success');
        $this->redirectRoute('cars.index', navigate: true);
    }

    public function render(CarCompanyService $companyService): View
    {
        $companies = $companyService->getCompanies([], false);

        return view('car::livewire.car-add', [
            ...compact('companies'),
        ])
            ->layout('dashboard::layouts.app')
            ->layoutData([
                'title' => __('car::strings.Add Car'),
            ]);
    }
}
