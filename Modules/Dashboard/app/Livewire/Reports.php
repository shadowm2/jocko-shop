<?php

namespace Modules\Dashboard\Livewire;

use App\Helpers\Utils;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Modules\Dashboard\Services\ProfitService;
use Modules\Inventory\Enums\PurchaseStatus;
use Modules\Inventory\Models\PurchaseItem;
use Modules\Inventory\Models\Supplier;
use Modules\Order\Enums\OrderStatus;
use Modules\Order\Models\Order;
use Modules\Order\Models\OrderItem;
use Modules\Order\Models\Payment;

class Reports extends Component
{
    private const LIST_SIZE = 10;

    /** @var array{from:string,to:string} */
    public array $filters = [
        'from' => '',
        'to' => '',
    ];

    public function clearFilters(): void
    {
        $this->reset('filters');
    }

    public function render(ProfitService $profits): View
    {
        $from = Utils::jalaliToDate($this->filters['from']);
        $to = Utils::jalaliToDate($this->filters['to']);
        $within = fn (Builder $query, string $column): Builder => $query
            ->when($from, fn (Builder $query) => $query->whereDate($column, '>=', $from))
            ->when($to, fn (Builder $query) => $query->whereDate($column, '<=', $to));

        // Cancelled orders are not sales.
        $sale = fn (Builder $query): Builder => $within($query->where('status', '!=', OrderStatus::CANCELLED->value), 'created_at');
        $orderCount = $sale(Order::query())->count();
        $revenue = (float) OrderItem::query()->whereHas('order', $sale)->sum('total');

        // Whatever is still owed, whenever it was ordered; an overpaid order does not offset another.
        // The sums are columns of a derived table so the balance can be filtered and sorted in SQL.
        $balance = 'round(coalesce(items_sum_total, 0) - coalesce(payments_sum_amount, 0), 2)';
        $owing = Order::query()
            ->fromSub(
                Order::query()
                    ->where('status', '!=', OrderStatus::CANCELLED->value)
                    ->withSum('items', 'total')
                    ->withSum('payments', 'amount'),
                'orders',
            )
            ->whereRaw("{$balance} > 0");
        $owed = (clone $owing)->toBase()->selectRaw("count(*) as orders, coalesce(sum({$balance}), 0) as total")->first();
        $outstanding = $owing
            ->with('user')
            ->orderByRaw("{$balance} desc")
            ->orderBy('id')
            ->limit(self::LIST_SIZE)
            ->get()
            ->map(fn (Order $order): array => [
                'order' => $order,
                'balance' => round((float) $order->items_sum_total - (float) $order->payments_sum_amount, 2),
            ]);

        // Money committed to suppliers: drafts and cancelled purchases are not spending.
        // Dated by the day it was ordered, or created when no order date was set.
        $day = 'date(coalesce(purchases.ordered_at, purchases.created_at))';
        $bySupplier = PurchaseItem::query()
            ->join('purchases', 'purchases.id', '=', 'purchase_items.purchase_id')
            ->whereNull('purchases.deleted_at')
            ->whereIn('purchases.status', [PurchaseStatus::ORDERED->value, PurchaseStatus::PARTIALLY_RECEIVED->value, PurchaseStatus::RECEIVED->value])
            ->when($from, fn (Builder $query) => $query->whereRaw("{$day} >= ?", [$from]))
            ->when($to, fn (Builder $query) => $query->whereRaw("{$day} <= ?", [$to]))
            ->selectRaw('purchases.supplier_id, sum(purchase_items.total) as spent, count(distinct purchases.id) as purchases')
            ->groupBy('purchases.supplier_id')
            ->orderByDesc('spent')
            ->limit(self::LIST_SIZE)
            ->toBase()
            ->get();
        $suppliers = Supplier::query()->with('user')->findMany($bySupplier->pluck('supplier_id'))->keyBy('id');

        return view('dashboard::livewire.reports', [
            'orderCount' => $orderCount,
            'revenue' => $revenue,
            'average' => $orderCount ? $revenue / $orderCount : 0,
            'collected' => (float) $within(Payment::query(), 'paid_at')->sum('amount'),
            'byMethod' => $within(Payment::query(), 'paid_at')
                ->selectRaw('method, sum(amount) as total, count(*) as payments')
                ->groupBy('method')
                ->orderByDesc('total')
                ->get(),
            // Quantities are left out on purpose: lines are sold in different units.
            'topItems' => OrderItem::query()
                ->whereHas('order', $sale)
                ->selectRaw('item_id, sum(total) as revenue, count(*) as orders')
                ->groupBy('item_id')
                ->orderByDesc('revenue')
                ->limit(self::LIST_SIZE)
                ->with('item')
                ->get(),
            'bySupplier' => $bySupplier->each(function (\stdClass $row) use ($suppliers): void {
                $row->supplier = $suppliers->get($row->supplier_id);
            }),
            'profit' => $profits->between($from, $to, self::LIST_SIZE),
            'outstandingTotal' => (float) $owed?->total,
            'outstandingCount' => (int) $owed?->orders,
            'outstanding' => $outstanding,
            'filterDefinitions' => [
                ['name' => 'from', 'type' => 'date', 'label' => __('dashboard::strings.From Date'), 'placeholder' => __('strings.Select Date')],
                ['name' => 'to', 'type' => 'date', 'label' => __('dashboard::strings.To Date'), 'placeholder' => __('strings.Select Date')],
            ],
        ])
            ->layout('dashboard::layouts.app')
            ->layoutData(['title' => __('dashboard::strings.Reports')]);
    }
}
