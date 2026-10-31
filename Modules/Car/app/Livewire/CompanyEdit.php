<?php

namespace Modules\Car\Livewire;

use App\Livewire\BaseComponent;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\WithFileUploads;
use Modules\Car\Livewire\Forms\CompanyForm;
use Modules\Car\Models\CarCompany;
use Modules\Car\Services\CarCompanyService;
use Modules\Dashboard\Traits\RemoveFile;

class CompanyEdit extends BaseComponent
{
    use RemoveFile;
    use WithFileUploads;

    public CompanyForm $form;

    public CarCompany $company;

    public function mount(CarCompany $company): void
    {
        $this->company = $company;
        $this->form->setCompany($company);
    }

    public function save(): void
    {
        $this->form->save();
        Flux::toast(__('car::messages.Car Company Updated Successfully'), variant: 'success');

        $this->redirectRoute('companies.index', navigate: true);
    }

    public function render(CarCompanyService $companyService): View
    {
        $companies = $companyService->getCompanies([], false);

        return view('car::livewire.company-edit', [
            ...compact('companies'),
        ])
            ->layout('dashboard::layouts.app')
            ->layoutData([
                'title' => __('car::strings.Edit Car Company'),
            ]);
    }
}
