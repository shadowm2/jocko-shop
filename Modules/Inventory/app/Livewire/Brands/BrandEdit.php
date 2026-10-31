<?php

namespace Modules\Inventory\Livewire\Brands;

use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithFileUploads;
use Modules\Dashboard\Traits\RemoveFile;
use Modules\Inventory\Livewire\Forms\BrandForm;
use Modules\Inventory\Models\Brand;

class BrandEdit extends Component
{
    use RemoveFile;
    use WithFileUploads;

    public BrandForm $form;

    public Brand $brand;

    public function mount(): void
    {
        $this->form->setBrand($this->brand);
    }

    public function save(): void
    {
        $this->form->save();
        Flux::toast(text: __('inventory::messages.Brand Updated Successfully'), variant: 'success');
        $this->redirectRoute('brands.index', navigate: true);
    }

    public function render(): View
    {
        return view('inventory::livewire.brands-edit', [])
            ->layout('dashboard::layouts.app')
            ->layoutData([
                'title' => __('inventory::strings.Edit Brand'),
            ]);
    }
}
