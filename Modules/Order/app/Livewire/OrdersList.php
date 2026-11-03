<?php

namespace Modules\Order\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Inventory\Livewire\Concerns\InteractsWithTableFilters;
use Modules\Inventory\Services\WarehouseService;
use Modules\Order\Enums\OrderStatus;
use Modules\Order\Enums\PaymentStatus;
use Modules\Order\Services\OrderService;

class OrdersList extends Component
{
    use InteractsWithTableFilters;
    use WithPagination;

    private const FILTERS = [
        'search' => '',
        'status' => '',
        'payment_status' => '',
        'created_from' => '',
        'created_to' => '',
    ];

    /** @var array<string, mixed> */
    public array $filters = self::FILTERS;

    protected function defaultTableFilters(): array
    {
        return self::FILTERS;
    }

    public function render(OrderService $orderService): View
    {
        $multiWarehouse = resolve(WarehouseService::class)->isMultiple();
        $columns = [
            ['label' => __('order::attributes.Order Number')],
            ['label' => __('order::attributes.User Name')],
            ['label' => __('order::attributes.User Car')],
            ...($multiWarehouse ? [['label' => __('order::attributes.Warehouse')]] : []),
            ['label' => __('order::attributes.Items and Total')],
            ['label' => __('order::attributes.Order Status')],
            ['label' => __('order::attributes.Payment Status')],
            [],
        ];
        $orders = $orderService->getOrders($this->filters);

        $filterDefinitions = [
            ['name' => 'search', 'type' => 'search', 'label' => __('order::strings.Search'), 'placeholder' => __('order::strings.Search Orders')],
            ['name' => 'status', 'type' => 'select', 'label' => __('order::attributes.Order Status'), 'placeholder' => __('strings.All'), 'options' => collect(OrderStatus::cases())
                ->mapWithKeys(fn (OrderStatus $status) => [$status->value => $status->label()])->all()],
            ['name' => 'payment_status', 'type' => 'select', 'label' => __('order::attributes.Payment Status'), 'placeholder' => __('strings.All'), 'options' => collect(PaymentStatus::cases())
                ->mapWithKeys(fn (PaymentStatus $status) => [$status->value => $status->label()])->all()],
            ['name' => 'created_from', 'type' => 'date', 'label' => __('order::strings.Created From'), 'placeholder' => __('strings.Select Date')],
            ['name' => 'created_to', 'type' => 'date', 'label' => __('order::strings.Created To'), 'placeholder' => __('strings.Select Date')],
        ];

        return view('order::livewire.orders-list', [
            'rows' => $orders,
            'columns' => $columns,
            'filterDefinitions' => $filterDefinitions,
        ])
            ->layout('dashboard::layouts.app')
            ->layoutData([
                'title' => __('order::strings.Orders'),
            ]);
    }
}
