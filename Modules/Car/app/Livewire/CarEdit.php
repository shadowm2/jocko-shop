<?php

namespace Modules\Car\Livewire;

use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithFileUploads;
use Modules\Car\Livewire\Forms\CarForm;
use Modules\Car\Models\Car;
use Modules\Car\Services\CarCompanyService;
use Modules\Dashboard\Traits\RemoveFile;

class CarEdit extends Component
{
    use RemoveFile;
    use WithFileUploads;

    public CarForm $form;

    public Car $car;

    public function mount(Car $car): void
    {
        $this->car = $car;
        $this->form->setCar($car);
    }

    public function save(): void
    {
        $this->form->save();
        Flux::toast(__('car::messages.Car Updated Successfully'), variant: 'success');

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
                'title' => __('car::strings.Edit Car'),
            ]);
    }
}
