<?php

use Modules\Car\Models\Car;
use Modules\Car\Models\UserCar;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\Unit;
use Modules\Inventory\Models\UnitGroup;
use Modules\Inventory\Models\Warehouse;
use Modules\Order\Enums\OrderStatus;
use Modules\Order\Enums\PaymentMethod;
use Modules\Order\Models\Order;
use Modules\Order\Models\OrderItem;
use Modules\Order\Models\Payment;
use Modules\User\Models\User;

// Bare rows only (see OrderStockTest): foreign key checks are deferred.
beforeEach(function () {
    deferForeignKeys();

    $this->customer = User::factory()->create(['first_name' => 'Sara', 'last_name' => 'Karimi', 'mobile' => '09352222222']);
    $car = Car::forceCreate(['name' => 'Pride', 'slug' => 'pride', 'car_company_id' => 1]);
    $userCar = UserCar::forceCreate(['slug' => 'uc1', 'user_id' => $this->customer->id, 'car_id' => $car->id, 'color_id' => 1]);
    $warehouse = Warehouse::forceCreate(['name' => 'Main', 'slug' => 'main']);
    $group = UnitGroup::forceCreate(['name' => 'Count', 'slug' => 'count']);
    $piece = Unit::forceCreate(['name' => 'Piece', 'symbol' => 'pc', 'unit_group_id' => $group->id, 'conversion_factor' => 1, 'is_base' => true, 'slug' => 'piece']);
    $oil = Item::forceCreate(['name' => 'Engine Oil', 'slug' => 'oil', 'unit_group_id' => $group->id, 'category_id' => 1]);

    $this->order = Order::forceCreate([
        'slug' => 'o1', 'user_id' => $this->customer->id, 'user_car_id' => $userCar->id, 'warehouse_id' => $warehouse->id,
        'order_number' => 'ORD-00001', 'status' => OrderStatus::PENDING, 'description' => 'Bring the old filter',
    ]);
    OrderItem::forceCreate(['slug' => 'oi1', 'order_id' => $this->order->id, 'item_id' => $oil->id, 'unit_id' => $piece->id, 'quantity' => 2, 'unit_price' => 5000, 'total' => 10000]);
    Payment::forceCreate(['slug' => 'p1', 'order_id' => $this->order->id, 'amount' => 4000, 'method' => PaymentMethod::CARD, 'paid_at' => today()]);
});

it('prints the customer, the lines, what was paid and what is still owed', function () {
    // The warehouse is only named when the shop has several.
    Warehouse::forceCreate(['name' => 'Spare', 'slug' => 'spare']);

    $this->actingAs($this->customer)
        ->get(route('orders.invoice', $this->order))
        ->assertOk()
        ->assertSee(['ORD-00001', 'Sara Karimi', '۰۹۳۵۲۲۲۲۲۲۲', 'Pride', 'Main', 'Engine Oil', 'pc', 'Bring the old filter'])
        ->assertSeeInOrder(['۱۰,۰۰۰', '۴,۰۰۰', '۶,۰۰۰'])
        ->assertSee(PaymentMethod::CARD->label())
        ->assertSee('window.print()', false);
});

it('puts the shop details in the header when they are set', function () {
    $page = fn () => $this->actingAs($this->customer)->get(route('orders.invoice', $this->order));

    $page()->assertDontSee('Valiasr');

    config(['app.name' => 'Jocko Garage', 'shop.address' => 'Tehran, Valiasr', 'shop.phone' => '02112345678']);

    $page()->assertSee(['Jocko Garage', 'Tehran, Valiasr', '۰۲۱۱۲۳۴۵۶۷۸']);
});

it('is only for signed in users and only for orders that exist', function () {
    $this->get(route('orders.invoice', $this->order))->assertRedirect();

    $this->actingAs($this->customer)->get('/orders/nope/invoice')->assertNotFound();
});

it('leaves the warehouse off the invoice when the shop uses only one', function () {
    $this->actingAs($this->customer)
        ->get(route('orders.invoice', $this->order))
        ->assertOk()
        ->assertSee('ORD-00001')
        ->assertDontSee(__('order::attributes.Warehouse'))
        ->assertDontSee('Main');
});
