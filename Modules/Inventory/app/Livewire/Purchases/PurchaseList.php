<?php

namespace Modules\Inventory\Livewire\Purchases;

use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Inventory\Enums\PurchaseStatus;
use Modules\Inventory\Livewire\Concerns\InteractsWithTableFilters;
use Modules\Inventory\Livewire\Concerns\InteractsWithTableSorting;
use Modules\Inventory\Livewire\Forms\PurchaseForm;
use Modules\Inventory\Livewire\Purchases\Concerns\RemovesPurchaseItems;
use Modules\Inventory\Livewire\Purchases\Concerns\UsesWarehouseItemPicker;
use Modules\Inventory\Models\Purchase;
use Modules\Inventory\Services\PurchaseService;
use Modules\Inventory\Services\SupplierService;
use Modules\Inventory\Services\WarehouseService;

class PurchaseList extends Component
{
    use InteractsWithTableFilters;
    use InteractsWithTableSorting;
    use RemovesPurchaseItems;
    use UsesWarehouseItemPicker;
    use WithPagination;

    /** @var array<string, mixed> */
    public array $filters = [
        'search' => '',
        'statuses' => [],
        'supplier_id' => '',
        'ordered_from' => '',
        'ordered_to' => '',
    ];

    public string $sortBy = 'ordered_at';

    public string $sortDirection = 'desc';

    public PurchaseForm $form;

    protected function defaultTableFilters(): array
    {
        return [
            'search' => '',
            'statuses' => [],
            'supplier_id' => '',
            'ordered_from' => '',
            'ordered_to' => '',
        ];
    }

    public function editPurchaseItems(string $slug): void
    {
        $purchase = resolve(PurchaseService::class)->findByKey($slug);
        abort_unless($purchase instanceof Purchase, 404);

        $this->form->setPurchase($purchase);
        Flux::modal('purchase-items-editor')->show();
    }

    public function savePurchaseItems(): void
    {
        if (! $this->form->saveItems()) {
            return;
        }

        Flux::modal('purchase-items-editor')->close();
        Flux::toast(text: __('inventory::messages.Purchase Order Updated Successfully'), variant: 'success');
    }

    public function render(): View
    {
        $columns = [
            [
                'label' => __('inventory::attributes.Purchase Supplier'),
            ],
            ...(resolve(WarehouseService::class)->isMultiple() ? [['label' => __('inventory::attributes.Purchase Warehouse')]] : []),
            [
                'label' => __('inventory::attributes.Purchase Order Number'),
            ],
            [
                'label' => __('inventory::attributes.Purchase Items and Total'),
            ],
            [
                'label' => __('inventory::attributes.Purchase Status'),
            ],
            [
                'label' => __('inventory::attributes.Purchase Ordered At'),
            ],
            [
                'label' => __('inventory::attributes.Purchase Received At'),
            ],
            [
                'label' => '',
            ],
        ];
        $service = resolve(PurchaseService::class);
        $rows = $service->getPurchases([
            ...$this->filters,
            'sort_by' => $this->sortBy,
            'sort_direction' => $this->sortDirection,
        ]);
        $suppliers = resolve(SupplierService::class)->getSuppliers(paginate: false);
        $statusOptions = collect(PurchaseStatus::cases())
            ->reject(fn (PurchaseStatus $status) => $status->isDisplayOnly())
            ->mapWithKeys(fn (PurchaseStatus $status) => [$status->value => $status->label()])
            ->all();
        $supplierOptions = $suppliers->mapWithKeys(fn ($supplier) => [
            $supplier->id => $supplier->user->fullName(),
        ])->all();
        $filterDefinitions = [
            ['name' => 'search', 'type' => 'search', 'label' => __('inventory::strings.Search'), 'placeholder' => __('inventory::strings.Search Purchases')],
            ['name' => 'supplier_id', 'type' => 'select', 'label' => __('inventory::attributes.Purchase Supplier'), 'placeholder' => __('strings.All'), 'options' => $supplierOptions],
            ['name' => 'statuses', 'type' => 'checkboxes', 'label' => __('inventory::attributes.Purchase Status'), 'options' => $statusOptions],
            ['name' => 'ordered_from', 'type' => 'date', 'label' => __('inventory::strings.Ordered From')],
            ['name' => 'ordered_to', 'type' => 'date', 'label' => __('inventory::strings.Ordered To')],
        ];
        $sortOptions = [
            'ordered_at' => __('inventory::attributes.Purchase Ordered At'),
            'order_number' => __('inventory::attributes.Purchase Order Number'),
            'status' => __('inventory::attributes.Purchase Status'),
            'received_at' => __('inventory::attributes.Purchase Received At'),
        ];

        return view('inventory::livewire.purchases-list', [
            ...compact('columns', 'rows', 'filterDefinitions', 'sortOptions'),
            'itemCatalog' => $this->form->selectedCatalog(),
        ])
            ->layout('dashboard::layouts.app')
            ->layoutData([
                'title' => __('inventory::strings.Purchases'),
            ]);
    }
}
