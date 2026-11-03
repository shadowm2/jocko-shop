<?php

use App\Helpers\Utils;
use Livewire\Livewire;
use Modules\Dashboard\Livewire\Reports;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\UnitGroup;
use Modules\Order\Enums\OrderStatus;
use Modules\Order\Enums\PaymentMethod;
use Modules\Order\Models\Order;
use Modules\Order\Models\OrderItem;
use Modules\Order\Models\Payment;
use Modules\User\Models\User;
use Morilog\Jalali\Jalalian;

// Bare rows only (see OrderStockTest): foreign key checks are deferred.
beforeEach(function () {
    deferForeignKeys();

    $group = UnitGroup::forceCreate(['name' => 'Count', 'slug' => 'count']);
    $oil = Item::forceCreate(['name' => 'Oil', 'slug' => 'oil', 'unit_group_id' => $group->id, 'category_id' => 1]);
    $filter = Item::forceCreate(['name' => 'Filter', 'slug' => 'filter', 'unit_group_id' => $group->id, 'category_id' => 1]);
    $customer = User::factory()->create();

    $order = function (string $number, OrderStatus $status, array $lines, array $payments = [], int $daysAgo = 0) use ($customer) {
        $order = Order::forceCreate([
            'slug' => $number, 'user_id' => $customer->id, 'user_car_id' => 1, 'warehouse_id' => 1,
            'order_number' => $number, 'status' => $status, 'created_at' => now()->subDays($daysAgo),
        ]);
        foreach ($lines as [$item, $total]) {
            OrderItem::forceCreate(['slug' => "{$number}-{$item->slug}", 'order_id' => $order->id, 'item_id' => $item->id, 'quantity' => 1, 'unit_price' => $total, 'total' => $total]);
        }
        foreach ($payments as $method => $amount) {
            Payment::forceCreate(['slug' => "{$number}-{$method}", 'order_id' => $order->id, 'amount' => $amount, 'method' => PaymentMethod::from($method), 'paid_at' => today()]);
        }
    };

    // A part paid, B settled, C cancelled (never counts), D old and unpaid.
    $order('A', OrderStatus::PENDING, [[$oil, 1000]], ['cash' => 400]);
    $order('B', OrderStatus::COMPLETED, [[$oil, 2000], [$filter, 500]], ['card' => 2500]);
    $order('C', OrderStatus::CANCELLED, [[$oil, 9000]]);
    $order('D', OrderStatus::PENDING, [[$filter, 700]], [], daysAgo: 40);

    $this->range = fn (string $from = '', string $to = '') => Livewire::test(Reports::class)
        ->set('filters.from', $from)
        ->set('filters.to', $to);
    $this->week = Jalalian::fromDateTime(now()->subDays(7))->format('Y/m/d');
});

it('adds up sales, collections and what is still owed, leaving cancelled orders out', function () {
    ($this->range)()
        ->assertViewHas('orderCount', 3)
        ->assertViewHas('revenue', 4200.0)
        ->assertViewHas('collected', 2900.0)
        ->assertViewHas('outstandingTotal', 1300.0)
        ->assertViewHas('outstandingCount', 2)
        ->assertViewHas('outstanding', fn ($rows) => $rows->pluck('order.order_number')->all() === ['D', 'A'])
        ->assertViewHas('topItems', fn ($items) => $items->map(fn ($line) => [$line->item->name, (float) $line->revenue, (int) $line->orders])->all() === [['Oil', 3000.0, 2], ['Filter', 1200.0, 2]]);
});

it('totals every order that is owed but lists only the largest ten', function () {
    $customer = User::first();
    // Not item 1: only SQLite starts counting again after every test.
    $oilId = Item::where('slug', 'oil')->value('id');
    foreach (range(1, 12) as $n) {
        $order = Order::forceCreate([
            'slug' => "X{$n}", 'user_id' => $customer->id, 'user_car_id' => 1, 'warehouse_id' => 1,
            'order_number' => "X{$n}", 'status' => OrderStatus::PENDING,
        ]);
        OrderItem::forceCreate(['slug' => "X{$n}-line", 'order_id' => $order->id, 'item_id' => $oilId, 'quantity' => 1, 'unit_price' => 100, 'total' => 100]);
    }

    ($this->range)()
        ->assertViewHas('outstandingTotal', 2500.0)
        ->assertViewHas('outstandingCount', 14)
        ->assertViewHas('outstanding', fn ($rows) => $rows->count() === 10 && $rows->take(2)->pluck('order.order_number')->all() === ['D', 'A']);
});

it('limits sales to the date range but not what is owed', function () {
    // The range is typed in Persian digits, as the date picker fills it.
    ($this->range)(Utils::pDigits($this->week))
        ->assertViewHas('orderCount', 2)
        ->assertViewHas('revenue', 3500.0)
        ->assertViewHas('outstandingTotal', 1300.0)
        ->assertViewHas('topItems', fn ($items) => (float) $items->last()->revenue === 500.0);

    ($this->range)('', Jalalian::fromDateTime(now()->subDays(30))->format('Y/m/d'))
        ->assertViewHas('orderCount', 1)
        ->assertViewHas('revenue', 700.0)
        ->assertViewHas('collected', 0.0);

    ($this->range)(Jalalian::fromDateTime(now()->addDays(2))->format('Y/m/d'))
        ->assertViewHas('orderCount', 0)
        ->assertViewHas('revenue', 0.0)
        ->assertViewHas('average', 0)
        ->assertViewHas('topItems', fn ($items) => $items->isEmpty());
});

it('treats a half typed date as no limit', function () {
    ($this->range)('1404/0')->assertViewHas('orderCount', 3);
});

it('breaks collections down by method', function () {
    ($this->range)()->assertViewHas('byMethod', fn ($rows) => $rows->map(fn ($row) => [$row->method, (float) $row->total])->all() === [[PaymentMethod::CARD, 2500.0], [PaymentMethod::CASH, 400.0]]);
});

it('shows the reports page to a signed in user', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('reports.index'))->assertOk()->assertSee('Oil')->assertSee(route('orders.edit', ['order' => 'D']), false);
});
