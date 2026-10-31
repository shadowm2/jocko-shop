<?php

namespace Modules\Inventory\Livewire\ItemBrands;

use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Modules\Dashboard\Services\BrandService;
use Modules\Inventory\Livewire\Forms\ItemBrandForm;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\ItemBrand;

class ItemBrandEdit extends Component
{
    public ItemBrandForm $form;

    public Item $item;

    public ItemBrand $itemBrand;

    public function mount(): void
    {
        $this->form->setItem($this->item);
        $this->form->setItemBrand($this->itemBrand);
    }

    public function removeFile(string $property, int $index): void
    {
        $value = data_get($this, $property);

        if (is_array($value)) {
            unset($value[$index]);

            data_set(
                $this,
                $property,
                array_values($value)
            );

            return;
        }

        data_set($this, $property, null);
    }

    public function save(): void
    {
        $this->form->save();
        Flux::toast(text: __('inventory::messages.Item Brand Updated Successfully'), variant: 'success');
        $this->redirectRoute('items.brands.index', parameters: ['item' => $this->item], navigate: true);
    }

    public function render(): View
    {
        $brandService = resolve(BrandService::class);
        $brands = $brandService->getBrands(paginate: false);

        return view('inventory::livewire.item-brands.item-brands-edit', [
            ...compact('brands'),
        ])
            ->layout('dashboard::layouts.app')
            ->layoutData([
                'title' => __('inventory::strings.Edit Item Brand'),
            ]);
    }
}
