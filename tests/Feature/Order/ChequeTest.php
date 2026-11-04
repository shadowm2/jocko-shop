<?php

use Livewire\Livewire;
use Modules\Order\Enums\ChequeStatus;
use Modules\Order\Enums\OrderStatus;
use Modules\Order\Enums\PaymentMethod;
use Modules\Order\Enums\PaymentStatus;
use Modules\Order\Livewire\Cheques;
use Modules\Order\Livewire\PaymentCreate;
use Modules\Order\Livewire\PaymentEdit;
use Modules\Order\Models\Order;
use Modules\Order\Models\OrderItem;
use Modules\Order\Models\Payment;
use Modules\Order\Services\OrderService;
use Modules\User\Models\User;
use Morilog\Jalali\Jalalian;

beforeEach(function () {
    deferForeignKeys();
    $this->travelTo(now()->startOfDay()->addHours(10));

    $this->actingAs($this->user = User::factory()->create());
    $this->order = Order::forceCreate([
        'slug' => 'o1', 'user_id' => $this->user->id, 'user_car_id' => 1, 'warehouse_id' => 1,
        'order_number' => 'ORD-00001', 'status' => OrderStatus::PENDING,
    ]);
    OrderItem::forceCreate(['slug' => 'oi1', 'order_id' => $this->order->id, 'item_id' => 1, 'quantity' => 1, 'unit_price' => 10000, 'total' => 10000]);

    $this->cheque = fn (float $amount, $due, ChequeStatus $status = ChequeStatus::PENDING, string $number = '111') => Payment::forceCreate([
        'slug' => 'p'.(Payment::withoutGlobalScopes()->count() + 1), 'order_id' => $this->order->id, 'amount' => $amount,
        'method' => PaymentMethod::CHEQUE, 'paid_at' => today(), 'cheque_number' => $number, 'cheque_bank' => 'Melli',
        'cheque_due_at' => $due, 'cheque_status' => $status,
    ]);
    $this->status = fn () => resolve(OrderService::class)->getOrders([], paginate: false)->first();
});

it('records a cheque with its number, bank and due date, waiting to be cashed', function () {
    $due = Jalalian::fromDateTime(today()->addDays(30))->format('Y/m/d');

    Livewire::test(PaymentCreate::class)
        ->set('form.order', 'o1')
        ->set('form.amount', '4000')
        ->set('form.method', PaymentMethod::CHEQUE->value)
        ->set('form.cheque_number', '123456')
        ->set('form.cheque_bank', 'Melli')
        ->set('form.cheque_due_at', $due)
        ->call('save')
        ->assertHasNoErrors();

    $cheque = Payment::sole();
    expect($cheque->cheque_status)->toBe(ChequeStatus::PENDING)
        ->and($cheque->cheque_number)->toBe('123456')
        ->and($cheque->cheque_bank)->toBe('Melli')
        ->and($cheque->cheque_due_at->toDateString())->toBe(today()->addDays(30)->toDateString());
});

it('needs a number and a due date for a cheque, and none for other methods', function () {
    Livewire::test(PaymentCreate::class)
        ->set('form.order', 'o1')
        ->set('form.amount', '4000')
        ->set('form.method', PaymentMethod::CHEQUE->value)
        ->call('save')
        ->assertHasErrors(['form.cheque_number', 'form.cheque_due_at']);

    Livewire::test(PaymentCreate::class)
        ->set('form.order', 'o1')
        ->set('form.amount', '4000')
        ->set('form.method', PaymentMethod::CASH->value)
        ->set('form.cheque_number', 'left over')
        ->call('save')
        ->assertHasNoErrors();

    expect(Payment::sole()->only(['cheque_number', 'cheque_due_at', 'cheque_status']))->toBe(['cheque_number' => null, 'cheque_due_at' => null, 'cheque_status' => null]);
});

it('keeps a cheque\'s status when it is edited', function () {
    $cheque = ($this->cheque)(4000, today()->addDays(5), ChequeStatus::CLEARED);

    Livewire::test(PaymentEdit::class, ['payment' => $cheque])
        ->set('form.cheque_bank', 'Mellat')
        ->call('save')
        ->assertHasNoErrors();

    expect($cheque->fresh()->cheque_bank)->toBe('Mellat')
        ->and($cheque->fresh()->cheque_status)->toBe(ChequeStatus::CLEARED);
});

it('stops counting a bounced cheque as paid, everywhere', function () {
    ($this->cheque)(10000, today()->addDays(5));
    expect(($this->status)()->paymentStatus())->toBe(PaymentStatus::PAID);

    Payment::sole()->update(['cheque_status' => ChequeStatus::BOUNCED]);

    $order = ($this->status)();
    expect($order->paymentStatus())->toBe(PaymentStatus::UNPAID)
        ->and((float) $order->payments_sum_amount)->toBe(0.0)
        ->and((float) $this->order->payments()->sum('amount'))->toBe(0.0)
        ->and(Order::query()->paymentStatusIs(PaymentStatus::UNPAID)->count())->toBe(1)
        ->and(Order::query()->paymentStatusIs(PaymentStatus::PAID)->count())->toBe(0);
});

it('counts a cheque as paid while it is waiting or once cashed', function () {
    ($this->cheque)(4000, today()->addDays(5), ChequeStatus::PENDING, '1');
    ($this->cheque)(6000, today()->subDays(5), ChequeStatus::CLEARED, '2');

    expect(($this->status)()->paymentStatus())->toBe(PaymentStatus::PAID);
});

it('summarises the cheques still to be cashed and lists them by due date', function () {
    ($this->cheque)(1000, today()->subDays(3), number: 'overdue');
    ($this->cheque)(2000, today()->addDays(2), number: 'soon');
    ($this->cheque)(4000, today()->addDays(40), number: 'later');
    ($this->cheque)(500, today()->addDays(1), ChequeStatus::CLEARED, 'done');
    ($this->cheque)(300, today(), ChequeStatus::BOUNCED, 'bad');

    Livewire::test(Cheques::class)
        ->assertSeeHtml('data-pending="7000"')
        ->assertSeeHtml('data-soon="2000"')
        ->assertSeeHtml('data-overdue="1000"')
        ->assertSeeHtml('data-bounced="300"')
        ->assertSeeInOrder(['overdue', 'soon', 'later'])
        ->assertDontSee('done')
        ->set('status', 'cleared')
        ->assertSee('done')
        ->assertDontSee('later')
        ->set('status', 'bounced')
        ->assertSee('bad')
        ->set('status', 'all')
        ->assertSee('overdue')->assertSee('done')->assertSee('bad');
});

it('marks a cheque cashed, bounced and back again', function () {
    $cheque = ($this->cheque)(10000, today()->addDay());

    $page = Livewire::test(Cheques::class)->call('mark', $cheque->id, 'cleared');
    expect($cheque->fresh()->cheque_status)->toBe(ChequeStatus::CLEARED)
        ->and(($this->status)()->paymentStatus())->toBe(PaymentStatus::PAID);

    $page->call('mark', $cheque->id, 'bounced');
    expect($cheque->refresh()->cheque_status)->toBe(ChequeStatus::BOUNCED)
        ->and(($this->status)()->paymentStatus())->toBe(PaymentStatus::UNPAID);

    // A bounced cheque can still be found and put back (it was cashed after all).
    $page->set('status', 'bounced')->assertSeeHtml('data-reopen="'.$cheque->id.'"')->call('mark', $cheque->id, 'pending');
    expect($cheque->refresh()->cheque_status)->toBe(ChequeStatus::PENDING)
        ->and(($this->status)()->paymentStatus())->toBe(PaymentStatus::PAID);
});

it('ignores an unknown status and payments that are not cheques', function () {
    $cheque = ($this->cheque)(4000, today());
    $cash = Payment::forceCreate(['slug' => 'cash', 'order_id' => $this->order->id, 'amount' => 100, 'method' => PaymentMethod::CASH, 'paid_at' => today()]);

    Livewire::test(Cheques::class)->call('mark', $cheque->id, 'nonsense')->call('mark', $cash->id, 'bounced');

    expect($cheque->fresh()->cheque_status)->toBe(ChequeStatus::PENDING)
        ->and($cash->fresh()->cheque_status)->toBeNull();
});

it('serves the cheques page behind sign-in', function () {
    $this->get(route('payments.cheques'))->assertOk()->assertSee(__('order::strings.Cheques'));

    auth()->logout();
    $this->get(route('payments.cheques'))->assertRedirect();
});

it('lets a cheque from before cheques were tracked be edited without details', function () {
    $legacy = Payment::forceCreate(['slug' => 'old', 'order_id' => $this->order->id, 'amount' => 3000, 'method' => PaymentMethod::CHEQUE, 'paid_at' => today()]);

    Livewire::test(PaymentEdit::class, ['payment' => $legacy])
        ->set('form.description', 'old cheque')
        ->call('save')
        ->assertHasNoErrors();

    expect($legacy->fresh()->description)->toBe('old cheque')
        ->and($legacy->fresh()->cheque_status)->toBeNull();

    Livewire::test(Cheques::class)->set('status', 'all')->assertDontSee('old cheque');
});
