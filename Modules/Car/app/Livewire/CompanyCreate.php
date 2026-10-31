<?php

namespace Modules\Car\Livewire;

use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithFileUploads;
use Modules\Car\Livewire\Forms\CompanyForm;
use Modules\Car\Services\CarCompanyService;
use Modules\Dashboard\Traits\RemoveFile;

class CompanyCreate extends Component
{
    use RemoveFile;
    use WithFileUploads;

    public CompanyForm $form;

    public function save(): void
    {
        $this->form->save();
        Flux::toast(text: __('car::messages.Car Company Created Successfully'), variant: 'success');
        $this->redirectRoute('companies.index', navigate: true);
    }

    public function render(CarCompanyService $companyService): View
    {
        $companies = $companyService->getCompanies([], false);

        return view('car::livewire.company-add', [
            ...compact('companies'),
        ])
            ->layout('dashboard::layouts.app')
            ->layoutData([
                'title' => __('car::strings.Add Car Company'),
            ]);
    }
}
