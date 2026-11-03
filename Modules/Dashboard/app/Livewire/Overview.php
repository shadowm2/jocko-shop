<?php

namespace Modules\Dashboard\Livewire;

use Carbon\CarbonInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Component;
use Modules\Inventory\Enums\PurchaseStatus;
use Modules\Inventory\Models\Purchase;
use Modules\Inventory\Models\WarehouseItem;
use Modules\Order\Enums\OrderStatus;
use Modules\Order\Models\Order;
use Modules\Order\Models\OrderItem;
use Modules\Order\Models\Payment;
use Modules\User\Enums\UserType;
use Modules\User\Models\User;
use Morilog\Jalali\Jalalian;

class Overview extends Component
{
    private const LIST_SIZE = 5;

    private const MONTHS = 6;

    private const DAYS = 30;

    /**
     * Sales and purchases per Jalali month, and sales per day.
     * ponytail: loads the orders and purchases of the window into PHP, group in SQL when volumes grow.
     *
     * @return array{monthLabels: array<int, string>, monthSales: array<int, float>, monthPurchases: array<int, float>, dayLabels: array<int, string>, daySales: array<int, float>}
     */
    private function trends(): array
    {
        $thisMonth = Jalalian::now()->getFirstDayOfMonth();
        $months = collect(range(self::MONTHS - 1, 0))
            ->map(fn (int $ago): Jalalian => $ago > 0 ? $thisMonth->subMonths($ago) : $thisMonth);
        $days = collect(range(self::DAYS - 1, 0))->map(fn (int $ago): CarbonInterface => today()->subDays($ago));
        $since = $thisMonth->subMonths(self::MONTHS - 1)->toCarbon()->startOfDay();
        $monthKey = fn (CarbonInterface $date): string => Jalalian::fromDateTime($date)->format('Y/m');

        // Cancelled orders are not sales.
        $orders = Order::query()
            ->where('status', '!=', OrderStatus::CANCELLED)
            ->where('created_at', '>=', $since)
            ->withSum('items', 'total')
            ->get();
        $purchases = Purchase::query()
            ->whereIn('status', [PurchaseStatus::ORDERED, PurchaseStatus::PARTIALLY_RECEIVED, PurchaseStatus::RECEIVED])
            ->whereRaw('coalesce(ordered_at, created_at) >= ?', [$since])
            ->withSum('items', 'total')
            ->get();

        $salesByMonth = $orders->groupBy(fn (Order $order): string => $monthKey($order->created_at))->map->sum('items_sum_total');
        $purchasesByMonth = $purchases->groupBy(fn (Purchase $purchase): string => $monthKey($purchase->ordered_at ?? $purchase->created_at))->map->sum('items_sum_total');
        $salesByDay = $orders->groupBy(fn (Order $order): string => $order->created_at->toDateString())->map->sum('items_sum_total');

        $perMonth = fn (Collection $totals): array => $months
            ->map(fn (Jalalian $month): float => (float) ($totals[$month->format('Y/m')] ?? 0))
            ->all();

        return [
            'monthLabels' => $months->map(fn (Jalalian $month): string => $month->format('F'))->all(),
            'monthSales' => $perMonth($salesByMonth),
            'monthPurchases' => $perMonth($purchasesByMonth),
            'dayLabels' => $days->map(fn (CarbonInterface $day): string => Jalalian::fromDateTime($day)->format('m/d'))->all(),
            'daySales' => $days->map(fn (CarbonInterface $day): float => (float) ($salesByDay[$day->toDateString()] ?? 0))->all(),
        ];
    }

    public function render(): View
    {
        $lowStock = WarehouseItem::query()->lowStock();

        return view('dashboard::livewire.overview', [
            ...$this->trends(),
            'collectedToday' => Payment::query()
                ->whereDate('paid_at', today())
                ->selectRaw('method, sum(amount) as total, count(*) as payments')
                ->groupBy('method')
                ->orderByDesc('total')
                ->get(),
            'orderCount' => Order::count(),
            'statusCounts' => Order::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            // Cancelled orders are not revenue.
            'orderValue' => OrderItem::whereRelation('order', 'status', '!=', OrderStatus::CANCELLED)->sum('total'),
            'openPurchases' => Purchase::whereIn('status', [PurchaseStatus::ORDERED, PurchaseStatus::PARTIALLY_RECEIVED])->count(),
            'customerCount' => User::where('type', UserType::Customer)->count(),
            'lowStockCount' => (clone $lowStock)->count(),
            'lowStock' => $lowStock->with(['item', 'warehouse'])
                ->orderByRaw('min_quantity - quantity desc')
                ->limit(self::LIST_SIZE)
                ->get(),
            'recentOrders' => Order::with(['user', 'userCar.car', 'warehouse'])
                ->withSum('items', 'total')
                ->latest('id')
                ->limit(self::LIST_SIZE)
                ->get(),
            'recentPurchases' => Purchase::with(['supplier.user', 'warehouse'])
                ->withSum('items', 'total')
                ->latest('id')
                ->limit(self::LIST_SIZE)
                ->get(),
        ])
            ->layout('dashboard::layouts.app')
            ->layoutData(['title' => __('dashboard::strings.Dashboard')]);
    }
}
