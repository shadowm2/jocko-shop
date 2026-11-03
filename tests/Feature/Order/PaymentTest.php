<?php

use Livewire\Livewire;
use Modules\Order\Enums\OrderStatus;
use Modules\Order\Enums\PaymentMethod;
use Modules\Order\Enums\PaymentStatus;
use Modules\Order\Livewire\PaymentCreate;
use Modules\Order\Livewire\PaymentEdit;
use Modules\Order\Models\Order;
use Modules\Order\Models\OrderItem;
use Modules\Order\Models\Payment;
use Modules\Order\Services\OrderService;
use Modules\User\Models\User;

// Bare rows only: items, cars and warehouses are irrelevant here, so foreign key
// checks are deferred (never reached, the test transaction rolls back).
beforeEach(function () {
    deferForeignKeys();

    $this->user = User::factory()->create();
    $this->order = Order::forceCreate([
        'slug' => 'o1', 'user_id' => $this->user->id, 'user_car_id' => 1, 'warehouse_id' => 1,
        'order_number' => 'ORD-00001', 'status' => OrderStatus::PENDING,
    ]);
    OrderItem::forceCreate(['slug' => 'oi1', 'order_id' => $this->order->id, 'item_id' => 1, 'quantity' => 2, 'unit_price' => 5000, 'total' => 10000]);

    $this->pay = fn (float $amount) => Payment::forceCreate([
        'slug' => 'p'.Payment::count(), 'order_id' => $this->order->id, 'amount' => $amount,
        'method' => PaymentMethod::CASH, 'paid_at' => today(),
    ]);
});

it('derives the payment status from what has been paid', function (float $total, float $paid, PaymentStatus $status) {
    expect(PaymentStatus::for($total, $paid))->toBe($status);
})->with([
    'nothing paid' => [10000, 0, PaymentStatus::UNPAID],
    'part paid' => [10000, 4000, PaymentStatus::PARTIAL],
    'exactly paid' => [10000, 10000, PaymentStatus::PAID],
    'overpaid' => [10000, 12000, PaymentStatus::PAID],
]);

it('reports an order as unpaid, partial and paid as payments come in', function () {
    $status = fn () => resolve(OrderService::class)->getOrders([], paginate: false)->first()->paymentStatus();

    expect($status())->toBe(PaymentStatus::UNPAID);

    ($this->pay)(4000);
    expect($status())->toBe(PaymentStatus::PARTIAL);

    ($this->pay)(6000);
    expect($status())->toBe(PaymentStatus::PAID);
});

it('records a payment dated today for an order', function () {
    Livewire::test(PaymentCreate::class)
        ->set('form.order', 'o1')
        ->set('form.amount', '1,500')
        ->set('form.method', PaymentMethod::CARD->value)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('payments.index'));

    $payment = Payment::sole();
    expect((float) $payment->amount)->toBe(1500.0)
        ->and($payment->order_id)->toBe($this->order->id)
        ->and($payment->method)->toBe(PaymentMethod::CARD)
        ->and($payment->paid_at->isToday())->toBeTrue();
});

it('rejects a payment with no amount or a non-positive one', function (string $amount) {
    Livewire::test(PaymentCreate::class)
        ->set('form.order', 'o1')
        ->set('form.amount', $amount)
        ->call('save')
        ->assertHasErrors('form.amount');

    expect(Payment::count())->toBe(0);
})->with(['empty' => [''], 'zero' => ['0'], 'negative' => ['-5'], 'text' => ['abc']]);

it('rejects a payment larger than what the order still owes', function () {
    ($this->pay)(4000);
    $pay = fn (string $amount) => Livewire::test(PaymentCreate::class)
        ->set('form.order', 'o1')
        ->set('form.amount', $amount)
        ->call('save');

    $pay('6,000.01')->assertHasErrors('form.amount');
    expect(Payment::count())->toBe(1);

    $pay('6,000')->assertHasNoErrors();
    expect(Payment::count())->toBe(2);
});

it('does not count a payment against itself when editing it', function () {
    ($this->pay)(4000);
    $payment = ($this->pay)(6000);
    $edit = fn (string $amount) => Livewire::test(PaymentEdit::class, ['payment' => $payment])
        ->set('form.amount', $amount)
        ->call('save');

    $edit('6000')->assertHasNoErrors();
    $edit('6001')->assertHasErrors('form.amount');
    $edit('2000')->assertHasNoErrors();
    expect((float) $payment->fresh()->amount)->toBe(2000.0);
});

it('edits a payment without moving it to another order', function () {
    $payment = ($this->pay)(1000);

    Livewire::test(PaymentEdit::class, ['payment' => $payment])
        ->assertSet('form.amount', '۱۰۰۰')
        ->set('form.amount', '2500')
        ->set('form.method', PaymentMethod::CHEQUE->value)
        ->call('save')
        ->assertHasNoErrors();

    $payment->refresh();
    expect((float) $payment->amount)->toBe(2500.0)
        ->and($payment->method)->toBe(PaymentMethod::CHEQUE)
        ->and($payment->order_id)->toBe($this->order->id)
        ->and($payment->slug)->toBe('p0');
});

it('shows the payment pages to a signed in user', function () {
    $payment = ($this->pay)(1000);
    $this->actingAs($this->user);

    $this->get(route('payments.index'))->assertOk()->assertSee('ORD-00001');
    $this->get(route('payments.create'))->assertOk();
    $this->get(route('payments.edit', $payment))->assertOk();
});
