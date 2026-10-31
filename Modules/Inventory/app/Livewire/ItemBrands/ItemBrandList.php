<?php

namespace Modules\Inventory\Livewire\ItemBrands;

use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Inventory\Livewire\Concerns\InteractsWithTableFilters;
use Modules\Inventory\Livewire\Concerns\InteractsWithTableSorting;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Services\ItemBrandService;

class ItemBrandList extends Component
{
    use InteractsWithTableFilters;
    use InteractsWithTableSorting;
    use WithPagination;

    private const FILTERS = [
        'search' => '',
        'is_active' => [],
    ];

    /** @var array<string, mixed> */
    public array $filters = self::FILTERS;

    public string $sortBy = 'brand';

    public string $sortDirection = 'asc';

    protected function defaultTableFilters(): array
    {
        return self::FILTERS;
    }

    /** @return array<int, array<string, mixed>> */
    private function filterDefinitions(): array
    {
        return [
            ['name' => 'search', 'type' => 'search', 'label' => __('inventory::strings.Search'), 'placeholder' => __('inventory::strings.Search Item Brands')],
            ['name' => 'is_active', 'type' => 'checkboxes', 'label' => __('inventory::attributes.Is Active'), 'options' => ['1' => __('strings.Yes'), '0' => __('strings.No')]],
        ];
    }

    /** @return array<string, string> */
    private function sortOptions(): array
    {
        return ['brand' => __('inventory::attributes.Item Brand Brand'), 'sku' => __('inventory::attributes.Item Brand SKU'), 'purchase_price' => __('inventory::attributes.Item Brand Purchase Price'), 'sale_price' => __('inventory::attributes.Item Brand Sale Price')];
    }

    public Item $item;

    public function toggleIsActive(string $itemBrandSlug): void
    {
        $itemBrandService = resolve(ItemBrandService::class);

        $itemBrand = $itemBrandService->findByKeyOrFail($itemBrandSlug);

        $itemBrandService->update($itemBrand, [
            'is_active' => ! $itemBrand->is_active,
        ]);

        Flux::toast(__('inventory::messages.Item Brand Updated Successfully'), variant: 'success');
    }

    public function render(): View
    {
        $columns = [
            [
                'label' => __('inventory::attributes.Item Brand Item'),
            ],
            [
                'label' => __('inventory::attributes.Item Brand Brand'),
            ],
            [
                'label' => __('inventory::attributes.Item Brand SKU'),
            ],
            [
                'label' => __('inventory::attributes.Item Brand Barcode'),
            ],
            [
                'label' => __('inventory::attributes.Item Brand Part Number'),
            ],
            [
                'label' => __('inventory::attributes.Item Brand Purchase Price'),
            ],
            [
                'label' => __('inventory::attributes.Item Brand Sale Price'),
            ],
            [
                'label' => __('inventory::attributes.Is Active'),
            ],
            [
                'label' => __('strings.Actions'),
            ],
        ];
        $itemService = resolve(ItemBrandService::class);
        $rows = $itemService->getItemBrands([...$this->filters, 'sort_by' => $this->sortBy, 'sort_direction' => $this->sortDirection, 'item_id' => $this->item->id]);

        return view('inventory::livewire.item-brands.item-brands-list', [
            ...compact('columns', 'rows'),
            'filterDefinitions' => $this->filterDefinitions(),
            'sortOptions' => $this->sortOptions(),
            'item' => $this->item,
        ])
            ->layout('dashboard::layouts.app')
            ->layoutData([
                'title' => __('inventory::strings.Item Brands'),
            ]);
    }
}
