<?php

namespace Modules\Inventory\Livewire\Items;

use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithFileUploads;
use Modules\Dashboard\Services\CategoryService;
use Modules\Dashboard\Traits\RemoveFile;
use Modules\Inventory\Livewire\Forms\ItemForm;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Services\UnitGroupService;

class ItemEdit extends Component
{
    use RemoveFile;
    use WithFileUploads;

    public ItemForm $form;

    public Item $item;

    public function mount(): void
    {
        $this->form->setItem($this->item);
    }

    public function save(): void
    {
        $this->form->save();
        Flux::toast(text: __('inventory::messages.Brand Updated Successfully'), variant: 'success');
        $this->redirectRoute('items.index', navigate: true);
    }

    public function render(): View
    {
        $categoryService = resolve(CategoryService::class);
        $unitGroupService = resolve(UnitGroupService::class);
        $categories = $categoryService->getItemCategories();
        $unitGroups = $unitGroupService->getUnitGroups(paginate: false);

        return view('inventory::livewire.items-edit', [
            ...compact('categories', 'unitGroups'),
        ])
            ->layout('dashboard::layouts.app')
            ->layoutData([
                'title' => __('inventory::strings.Edit Item'),
            ]);
    }
}
