<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Modules\Dashboard\Services\SettingService;
use Modules\Inventory\Models\Warehouse;
use Modules\Order\Enums\OrderStatus;
use Modules\Order\Enums\PaymentMethod;
use Modules\Order\Models\Order;
use Modules\Order\Models\OrderItem;
use Modules\Order\Models\Payment;
use Modules\User\Models\User;

// Real browser, real clicks: the cashier's screen and the warehouse switch as a person uses them.

beforeEach(function () {
    Schema::disableForeignKeyConstraints();
    $this->actingAs(User::factory()->create());

    $this->main = Warehouse::forceCreate(['name' => 'Main', 'slug' => 'main']);
    $this->order = Order::forceCreate([
        'slug' => 'o1', 'user_id' => 1, 'user_car_id' => 1, 'warehouse_id' => $this->main->id,
        'order_number' => 'ORD-00001', 'status' => OrderStatus::PENDING,
    ]);
    OrderItem::forceCreate(['slug' => 'oi1', 'order_id' => $this->order->id, 'item_id' => 1, 'unit_id' => 1, 'quantity' => 1, 'unit_price' => 100000, 'total' => 100000]);
});

test('a cashier takes a card terminal payment and the order is fully paid', function () {
    visit(route('orders.pay', $this->order))
        ->assertSee('ORD-00001')
        ->click('[data-terminal="manual"]')
        ->waitForText(__('order::strings.Terminal Approved'))
        ->type('[wire\:model="terminalReference"]', '778899')
        ->click(__('order::strings.Terminal Approved'))
        ->waitForText(__('order::strings.Order Fully Paid'))
        ->assertNoSmoke();

    $payment = Payment::sole();
    expect($payment->method)->toBe(PaymentMethod::POS)
        ->and((float) $payment->amount)->toBe(100000.0)
        ->and($payment->description)->toContain('#778899');
});

test('with several warehouses an order asks which one, and with one it only says which', function () {
    Warehouse::forceCreate(['name' => 'Spare', 'slug' => 'spare']);
    $label = __('inventory::attributes.Purchase Warehouse');

    visit(route('orders.create'))->waitForText(__('order::attributes.User Car'))->assertDontSee($label.':');

    resolve(SettingService::class)->set('warehouses.multiple', '0');
    resolve(SettingService::class)->set('warehouses.default', (string) $this->main->id);
    Cache::flush();

    visit(route('orders.create'))
        ->waitForText($label.':')
        ->assertSee('Main')
        ->assertDontSee('Spare')
        ->assertNoSmoke();
});
