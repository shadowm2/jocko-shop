<?php

use Livewire\Livewire;
use Modules\Car\Models\Car;
use Modules\Car\Models\UserCar;
use Modules\Dashboard\Livewire\Overview;
use Modules\Dashboard\Livewire\Reports;
use Modules\Inventory\Models\Warehouse;
use Modules\Order\Enums\OrderStatus;
use Modules\Order\Enums\PaymentMethod;
use Modules\Order\Models\Order;
use Modules\Order\Models\Payment;
use Modules\User\Models\User;

beforeEach(function () {
    deferForeignKeys();
    $this->actingAs(User::factory()->create());

    // The dashboard also lists recent orders, which show the customer, car and warehouse.
    $customer = User::factory()->create();
    $car = Car::forceCreate(['slug' => 'pride', 'name' => 'Pride', 'car_company_id' => 1]);
    $userCar = UserCar::forceCreate(['slug' => 'uc1', 'user_id' => $customer->id, 'car_id' => $car->id, 'color_id' => 1]);
    $warehouse = Warehouse::forceCreate(['name' => 'Main', 'slug' => 'main']);
    $this->order = Order::forceCreate([
        'slug' => 'o1', 'user_id' => $customer->id, 'user_car_id' => $userCar->id, 'warehouse_id' => $warehouse->id,
        'order_number' => 'ORD-1', 'status' => OrderStatus::PENDING,
    ]);
    $this->pay = fn (PaymentMethod $method, float $amount, $on = null) => Payment::forceCreate([
        'slug' => 'p'.Payment::withTrashed()->count(), 'order_id' => $this->order->id,
        'amount' => $amount, 'method' => $method, 'paid_at' => $on ?? today(),
    ]);
});

it('shows what was collected today, by method, biggest first', function () {
    ($this->pay)(PaymentMethod::CASH, 1000);
    ($this->pay)(PaymentMethod::POS, 5000);
    ($this->pay)(PaymentMethod::POS, 2500);
    ($this->pay)(PaymentMethod::ONLINE, 4000);

    $component = Livewire::test(Overview::class)
        ->assertViewHas('collectedToday', fn ($rows) => $rows->map(fn ($row) => [$row->method->value, (float) $row->total, (int) $row->payments])->all()
            === [['pos', 7500.0, 2], ['online', 4000.0, 1], ['cash', 1000.0, 1]])
        ->assertSee('data-collected-total="12500"', false)
        ->assertSee('data-collected-method="pos"', false)
        ->assertSee('data-collected-method="online"', false)
        ->assertSee(PaymentMethod::POS->label())
        ->assertSee(PaymentMethod::ONLINE->label());

    expect($component->html())->not->toContain('data-collected-method="transfer"');
});

it('counts only today, and not payments that were deleted', function () {
    ($this->pay)(PaymentMethod::CASH, 1000);
    ($this->pay)(PaymentMethod::CASH, 9000, today()->subDay());
    ($this->pay)(PaymentMethod::CARD, 9000, today()->addDay());
    ($this->pay)(PaymentMethod::POS, 700)->delete();

    Livewire::test(Overview::class)
        ->assertSee('data-collected-total="1000"', false)
        ->assertDontSee('data-collected-method="pos"', false)
        ->assertDontSee('data-collected-method="card"', false);
});

it('says so when nothing was collected today', function () {
    ($this->pay)(PaymentMethod::CASH, 9000, today()->subDay());

    Livewire::test(Overview::class)
        ->assertSee('data-collected-total="0"', false)
        ->assertSee(__('dashboard::strings.No payments today'));
});

it('lists POS and online payments in the report by method too', function () {
    ($this->pay)(PaymentMethod::POS, 5000);
    ($this->pay)(PaymentMethod::ONLINE, 4000);

    Livewire::test(Reports::class)
        ->assertViewHas('byMethod', fn ($rows) => $rows->map(fn ($row) => [$row->method->value, (float) $row->total])->sortBy(0)->values()->all() === [['online', 4000.0], ['pos', 5000.0]])
        ->assertSee(PaymentMethod::POS->label())
        ->assertSee(PaymentMethod::ONLINE->label());
});

it('renders on the dashboard page itself', function () {
    ($this->pay)(PaymentMethod::POS, 5000);

    $this->get(route('dashboard'))->assertOk()->assertSee('data-collected-today', false)->assertSee(__('dashboard::strings.Collected Today'));
});
