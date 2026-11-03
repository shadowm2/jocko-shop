<?php

use Illuminate\Support\Facades\Blade;
use Modules\Dashboard\Livewire\Overview;
use Modules\Inventory\Enums\PurchaseStatus;
use Modules\Inventory\Models\Purchase;
use Modules\Inventory\Models\PurchaseItem;
use Modules\Order\Enums\OrderStatus;
use Modules\Order\Models\Order;
use Modules\Order\Models\OrderItem;
use Modules\User\Models\User;
use Morilog\Jalali\Jalalian;

// Bare rows only (see OrderStockTest): foreign key checks are deferred.
beforeEach(function () {
    deferForeignKeys();

    $this->order = function (string $number, OrderStatus $status, int $total, $at) {
        $order = Order::forceCreate([
            'slug' => $number, 'user_id' => 1, 'user_car_id' => 1, 'warehouse_id' => 1,
            'order_number' => $number, 'status' => $status, 'created_at' => $at,
        ]);
        OrderItem::forceCreate(['slug' => "{$number}-line", 'order_id' => $order->id, 'item_id' => 1, 'quantity' => 1, 'unit_price' => $total, 'total' => $total]);
    };
    $this->purchase = function (string $number, PurchaseStatus $status, int $total, $at) {
        $purchase = Purchase::forceCreate([
            'slug' => $number, 'supplier_id' => 1, 'warehouse_id' => 1,
            'order_number' => $number, 'status' => $status, 'ordered_at' => $at,
        ]);
        PurchaseItem::forceCreate(['slug' => "{$number}-line", 'purchase_id' => $purchase->id, 'item_id' => 1, 'quantity' => 1, 'unit_price' => $total, 'total' => $total]);
    };
    // Two Jalali months back, whatever day today is.
    $this->earlier = Jalalian::now()->getFirstDayOfMonth()->subMonths(2)->toCarbon()->setTime(12, 0);

    // Overview::render() needs customers and cars for its lists, so read the private trends() directly.
    $this->trends = fn () => (fn () => $this->trends())->call(new Overview);

    $this->actingAs(User::factory()->create());
});

it('adds up sales and purchases per Jalali month, leaving cancelled ones out', function () {
    ($this->order)('A', OrderStatus::PENDING, 1000, now());
    ($this->order)('B', OrderStatus::COMPLETED, 2000, now());
    ($this->order)('C', OrderStatus::CANCELLED, 9000, now());
    ($this->order)('D', OrderStatus::PENDING, 700, $this->earlier);
    ($this->order)('E', OrderStatus::PENDING, 500, $this->earlier->copy()->subMonths(6));
    ($this->purchase)('P1', PurchaseStatus::RECEIVED, 400, now());
    ($this->purchase)('P2', PurchaseStatus::DRAFT, 8000, now());
    ($this->purchase)('P3', PurchaseStatus::ORDERED, 300, $this->earlier);

    $trends = ($this->trends)();

    expect($trends['monthLabels'])->toHaveCount(6)->and($trends['monthLabels'][5])->toBe(Jalalian::now()->format('F'))
        ->and($trends['monthSales'])->toBe([0.0, 0.0, 0.0, 700.0, 0.0, 3000.0])
        ->and($trends['monthPurchases'])->toBe([0.0, 0.0, 0.0, 300.0, 0.0, 400.0]);
});

it('adds up sales per day over the last 30 days', function () {
    ($this->order)('A', OrderStatus::PENDING, 1000, now());
    ($this->order)('B', OrderStatus::COMPLETED, 250, now());
    ($this->order)('C', OrderStatus::PENDING, 700, now()->subDays(29));
    ($this->order)('D', OrderStatus::PENDING, 900, now()->subDays(30));

    $sales = ($this->trends)()['daySales'];

    expect($sales)->toHaveCount(30)->and($sales[0])->toBe(700.0)->and($sales[29])->toBe(1250.0)->and(array_sum($sales))->toBe(1950.0);
});

it('draws a column per label with a tooltip, a legend and a table view', function () {
    $html = Blade::render(
        '<x-dashboard::chart.columns :labels="$labels" :series="$series" />',
        ['labels' => ['مهر', 'آبان'], 'series' => ['Sales' => [1500, 0], 'Purchases' => [500, 2000]]],
    );

    expect($html)
        // The scale tops out at the next 1, 2 or 5 times a power of ten above the peak.
        ->toContain('۲,۰۰۰', '۱,۰۰۰', 'height: 75%', 'height: 100%')
        ->toContain(__('dashboard::strings.Show as table'), 'aria-label="مهر: Sales ۱,۵۰۰، Purchases ۵۰۰"')
        ->and(substr_count($html, 'role="img"'))->toBe(2);
});

it('leaves out the legend for a single series', function () {
    $html = Blade::render('<x-dashboard::chart.columns :labels="[\'a\']" :series="[\'Sales\' => [3]]" />');

    expect($html)->not->toContain('flex-wrap gap-x-4');
});

it('shows no chart before there is anything to chart', function () {
    $this->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee(__('dashboard::strings.Sales vs Purchases'))
        ->assertDontSee(__('dashboard::strings.Daily Sales'));
});
