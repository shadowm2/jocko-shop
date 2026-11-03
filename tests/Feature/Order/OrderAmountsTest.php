<?php

use Livewire\Livewire;
use Modules\Car\Models\Car;
use Modules\Car\Models\UserCar;
use Modules\Inventory\Models\Warehouse;
use Modules\Order\Enums\OrderStatus;
use Modules\Order\Enums\PaymentMethod;
use Modules\Order\Livewire\OrderEdit;
use Modules\Order\Models\Order;
use Modules\Order\Models\OrderItem;
use Modules\Order\Models\Payment;
use Modules\User\Models\User;

beforeEach(function () {
    deferForeignKeys();
    $this->actingAs(User::factory()->create());

    $customer = User::factory()->create();
    $customer->forceFill(['slug' => 'cust1'])->save();
    $car = Car::forceCreate(['slug' => 'pride', 'name' => 'Pride', 'car_company_id' => 1]);
    $userCar = UserCar::forceCreate(['slug' => 'uc1', 'user_id' => $customer->id, 'car_id' => $car->id, 'color_id' => 1]);
    $warehouse = Warehouse::forceCreate(['name' => 'Main', 'slug' => 'main']);

    $this->order = function (string $number, float $total, float $paid) use ($customer, $userCar, $warehouse): Order {
        $order = Order::forceCreate([
            'slug' => strtolower($number), 'user_id' => $customer->id, 'user_car_id' => $userCar->id, 'warehouse_id' => $warehouse->id,
            'order_number' => $number, 'status' => OrderStatus::PENDING,
        ]);
        OrderItem::forceCreate(['slug' => "oi-{$number}", 'order_id' => $order->id, 'item_id' => 1, 'unit_id' => 1, 'quantity' => 1, 'unit_price' => $total, 'total' => $total]);

        if ($paid > 0) {
            Payment::forceCreate(['slug' => "p-{$number}", 'order_id' => $order->id, 'amount' => $paid, 'method' => PaymentMethod::CASH, 'paid_at' => now()]);
        }

        return $order;
    };
});

it('shows total, paid and remaining for every order in the list', function () {
    ($this->order)('ORD-A', 100000, 30000);
    ($this->order)('ORD-B', 50000, 50000);

    $html = $this->get(route('orders.index'))->assertOk()->getContent();

    expect($html)
        ->toContain('data-total="100000"')->toContain('data-paid="30000"')->toContain('data-remaining="70000"')
        ->toContain('data-total="50000"')->toContain('data-paid="50000"')->toContain('data-remaining="0"');
});

it('shows zero paid and the whole total as remaining for an unpaid order', function () {
    ($this->order)('ORD-C', 8000, 0);

    $this->get(route('orders.index'))->assertOk()
        ->assertSee('data-paid="0"', false)
        ->assertSee('data-remaining="8000"', false);
});

it('shows a negative remaining for an order paid more than it is worth', function () {
    ($this->order)('ORD-D', 1000, 1500);

    $this->get(route('orders.index'))->assertOk()->assertSee('data-remaining="-500"', false);
});

it('shows the saved total, paid and remaining on the order edit page', function () {
    $order = ($this->order)('ORD-E', 100000, 40000);

    Livewire::test(OrderEdit::class, ['order' => $order])
        ->assertSee('data-total="100000"', false)
        ->assertSee('data-paid="40000"', false)
        ->assertSee('data-remaining="60000"', false);

    $this->get(route('orders.edit', $order))->assertOk()->assertSee('data-remaining="60000"', false);
});

it('keeps the edit page amounts in step with a payment made meanwhile', function () {
    $order = ($this->order)('ORD-F', 100000, 0);
    $page = Livewire::test(OrderEdit::class, ['order' => $order])->assertSee('data-remaining="100000"', false);

    Payment::forceCreate(['slug' => 'p-late', 'order_id' => $order->id, 'amount' => 100000, 'method' => PaymentMethod::CARD, 'paid_at' => now()]);

    $page->call('$refresh')->assertSee('data-remaining="0"', false);
});
