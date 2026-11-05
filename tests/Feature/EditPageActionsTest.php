<?php

use Modules\Car\Models\Car;
use Modules\Car\Models\CarCompany;
use Modules\Car\Models\Color;
use Modules\Car\Models\UserCar;
use Modules\Dashboard\Enums\CategoryType;
use Modules\Dashboard\Models\Category;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\UnitGroup;
use Modules\Inventory\Models\Warehouse;
use Modules\Order\Enums\OrderStatus;
use Modules\Order\Enums\PaymentMethod;
use Modules\Order\Models\Order;
use Modules\Order\Models\OrderItem;
use Modules\Order\Models\Payment;
use Modules\User\Enums\UserType;
use Modules\User\Models\User;

beforeEach(function () {
    deferForeignKeys();
    $this->actingAs(User::factory()->create());

    $this->customer = User::factory()->create(['first_name' => 'Ali', 'last_name' => 'Rezaei']);
    $this->customer->forceFill(['type' => UserType::Customer, 'slug' => 'ali'])->save();
    $car = Car::factory()->create(['car_company_id' => CarCompany::factory()->create()->id]);
    $this->userCar = UserCar::forceCreate(['slug' => 'uc1', 'user_id' => $this->customer->id, 'car_id' => $car->id, 'color_id' => Color::factory()->create()->id]);
    $warehouse = Warehouse::forceCreate(['name' => 'Main', 'slug' => 'main']);
    $this->order = Order::forceCreate(['slug' => 'o1', 'user_id' => $this->customer->id, 'user_car_id' => $this->userCar->id, 'warehouse_id' => $warehouse->id, 'order_number' => 'ORD-00001', 'status' => OrderStatus::PENDING]);
    OrderItem::forceCreate(['slug' => 'oi1', 'order_id' => $this->order->id, 'item_id' => 1, 'quantity' => 1, 'unit_price' => 10000, 'total' => 10000]);
    $this->payment = Payment::forceCreate(['slug' => 'p1', 'order_id' => $this->order->id, 'amount' => 3000, 'method' => PaymentMethod::CASH, 'paid_at' => today()]);
});

it('offers the receipt on the payment edit page', function () {
    $this->get(route('payments.edit', $this->payment))
        ->assertOk()
        ->assertSee('data-edit-actions', false)
        ->assertSee(route('payments.receipt', $this->payment), false);
});

it('offers the cars and the statement on the user edit page', function () {
    $this->get(route('users.edit', $this->customer))
        ->assertOk()
        ->assertSee(route('users.cars.index', $this->customer), false)
        ->assertSee(route('users.statement', $this->customer), false);
});

it('offers the vehicle history on the user car edit page', function () {
    $this->get(route('users.cars.edit', ['user' => $this->customer, 'userCar' => $this->userCar]))
        ->assertOk()
        ->assertSee(route('users.cars.history', ['user' => $this->customer, 'userCar' => $this->userCar]), false);
});

it('offers payment and the invoice on the order edit page, and no payment once it is paid', function () {
    $this->get(route('orders.edit', $this->order))
        ->assertOk()
        ->assertSee(route('orders.pay', $this->order), false)
        ->assertSee(route('orders.invoice', $this->order), false);

    $this->order->forceFill(['status' => OrderStatus::CANCELLED])->save();

    $this->get(route('orders.edit', $this->order))
        ->assertOk()
        ->assertDontSee(route('orders.pay', $this->order), false)
        ->assertSee(route('orders.invoice', $this->order), false);
});

it('offers the brands on the item edit page', function () {
    UnitGroup::factory()->create();
    Category::factory()->create();
    $item = Item::factory()->create();

    $this->get(route('items.edit', $item))
        ->assertOk()
        ->assertSee(route('items.brands.index', $item), false);
});

it('makes categories of a type that exists, every time', function () {
    $types = collect(range(1, 20))->map(fn () => Category::factory()->create()->type);

    expect($types->every(fn ($type) => $type instanceof CategoryType))->toBeTrue();
});
