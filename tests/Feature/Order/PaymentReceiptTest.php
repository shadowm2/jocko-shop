<?php

use Livewire\Livewire;
use Modules\Car\Models\Car;
use Modules\Car\Models\UserCar;
use Modules\Inventory\Models\Warehouse;
use Modules\Order\Enums\OrderStatus;
use Modules\Order\Enums\PaymentMethod;
use Modules\Order\Livewire\PaymentReceipt;
use Modules\Order\Models\Order;
use Modules\Order\Models\OrderItem;
use Modules\Order\Models\Payment;
use Modules\User\Enums\UserType;
use Modules\User\Models\User;

beforeEach(function () {
    deferForeignKeys();
    $this->actingAs(User::factory()->create());

    $customer = User::factory()->create(['first_name' => 'Ali', 'last_name' => 'Rezaei']);
    $customer->forceFill(['type' => UserType::Customer, 'slug' => 'ali'])->save();
    $car = Car::forceCreate(['slug' => 'pride', 'name' => 'Pride', 'car_company_id' => 1]);
    $userCar = UserCar::forceCreate(['slug' => 'uc1', 'user_id' => $customer->id, 'car_id' => $car->id, 'color_id' => 1]);
    $warehouse = Warehouse::forceCreate(['name' => 'Main', 'slug' => 'main']);
    $this->order = Order::forceCreate(['slug' => 'o1', 'user_id' => $customer->id, 'user_car_id' => $userCar->id, 'warehouse_id' => $warehouse->id, 'order_number' => 'ORD-00001', 'status' => OrderStatus::PENDING]);
    OrderItem::forceCreate(['slug' => 'oi1', 'order_id' => $this->order->id, 'item_id' => 1, 'quantity' => 1, 'unit_price' => 10000, 'total' => 10000]);
    $this->first = Payment::forceCreate(['slug' => 'p1', 'order_id' => $this->order->id, 'amount' => 3000, 'method' => PaymentMethod::CASH, 'paid_at' => today()]);
    $this->second = Payment::forceCreate(['slug' => 'p2', 'order_id' => $this->order->id, 'amount' => 2000, 'method' => PaymentMethod::POS, 'paid_at' => today(), 'description' => 'second part']);
});

it('prints the payment with what the order still owes', function () {
    Livewire::test(PaymentReceipt::class, ['payment' => $this->second])
        ->assertOk()
        ->assertSee(['ORD-00001', 'Ali Rezaei', 'Pride', 'second part'])
        ->assertSee($this->second->method->label())
        ->assertSeeHtml('data-receipt-amount="2000')
        ->assertSeeHtml('data-receipt-remaining="5000"');
});

it('is 80 mm wide unless the printer takes 58 mm paper', function () {
    $this->get(route('payments.receipt', $this->first))->assertOk()->assertSee('data-receipt="80"', false)->assertSee('size: 80mm auto', false);
    $this->get(route('payments.receipt', ['payment' => $this->first, 'width' => 58]))->assertOk()->assertSee('data-receipt="58"', false)->assertSee('size: 58mm auto', false);
    // Any other width falls back to the shop's setting.
    config(['payments.receipt_width' => 58]);
    $this->get(route('payments.receipt', ['payment' => $this->first, 'width' => 999]))->assertOk()->assertSee('data-receipt="58"', false);
});

it('shows a cheque\'s number and due date', function () {
    $this->first->forceFill(['method' => PaymentMethod::CHEQUE, 'cheque_number' => '998877', 'cheque_due_at' => today()->addMonth()])->save();

    Livewire::test(PaymentReceipt::class, ['payment' => $this->first])->assertSee(__('order::attributes.Cheque Number'))->assertSee('۹۹۸۸۷۷');
});

it('is linked from the payments list and needs a sign-in', function () {
    $this->get(route('payments.index'))->assertOk()->assertSee(route('payments.receipt', $this->first), false);

    auth()->logout();
    $this->get(route('payments.receipt', $this->first))->assertRedirect();
});

it('has no receipt for a payment that does not exist', function () {
    $this->get('/payments/nope/receipt')->assertNotFound();
});
