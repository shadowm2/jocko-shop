<?php

use App\Helpers\Utils;
use Livewire\Livewire;
use Modules\Order\Enums\OrderStatus;
use Modules\Order\Enums\PaymentMethod;
use Modules\Order\Livewire\DailyClose;
use Modules\Order\Models\CashClose;
use Modules\Order\Models\Order;
use Modules\Order\Models\Payment;
use Modules\Order\Services\CashCloseService;
use Modules\User\Models\User;
use Morilog\Jalali\Jalalian;

beforeEach(function () {
    deferForeignKeys();
    $this->travelTo(now()->startOfDay()->addHours(18));

    $this->actingAs($this->user = User::factory()->create());
    $this->order = Order::forceCreate(['slug' => 'o1', 'user_id' => $this->user->id, 'user_car_id' => 1, 'warehouse_id' => 1, 'order_number' => 'ORD-00001', 'status' => OrderStatus::PENDING]);

    $this->n = 0;
    $this->pay = fn (float $amount, PaymentMethod $method, $on = null, array $extra = []) => Payment::forceCreate([
        'slug' => 'p'.++$this->n, 'order_id' => $this->order->id, 'amount' => $amount, 'method' => $method, 'paid_at' => $on ?? today(),
    ] + $extra);
    $this->jalali = fn ($day) => Utils::pDigits(Jalalian::fromDateTime($day)->format('Y/m/d'));

    ($this->pay)(1000, PaymentMethod::CASH);
    ($this->pay)(500, PaymentMethod::CASH);
    ($this->pay)(-200, PaymentMethod::CASH);                    // a refund nets out
    ($this->pay)(4000, PaymentMethod::POS);
    ($this->pay)(3000, PaymentMethod::CHEQUE, today()->subDays(3));
    ($this->pay)(7000, PaymentMethod::CASH, today()->subDay());  // another day
});

it('adds up a day\'s payments by method', function () {
    $totals = resolve(CashCloseService::class)->totalsFor(today());

    expect($totals['cash'])->toBe(1300.0)
        ->and($totals['total'])->toBe(5300.0)
        ->and($totals['count'])->toBe(4)
        ->and($totals['methods']['cash'])->toBe(['total' => 1300.0, 'count' => 3])
        ->and($totals['methods']['pos'])->toBe(['total' => 4000.0, 'count' => 1])
        ->and($totals['methods']['cheque'])->toBe(['total' => 0.0, 'count' => 0], 'a cheque of another day');
});

it('shows today\'s totals and the cash that should be in the drawer', function () {
    Livewire::test(DailyClose::class)
        ->assertSeeHtml('data-expected="1300"')
        ->assertSeeHtml('data-day-total="5300"')
        ->assertSeeHtml('data-method-total="4000"')
        ->assertDontSeeHtml('data-method="cheque"')
        ->assertDontSeeHtml('data-difference');
});

it('shows how far the counted cash is from what was expected', function (string $counted, float $difference) {
    Livewire::test(DailyClose::class)->set('counted', $counted)->assertSeeHtml('data-difference="'.$difference.'"');
})->with([
    'short' => ['1250', -50.0],
    'exact' => ['1300', 0.0],
    'over' => ['1,400', 100.0],
]);

it('closes the day and keeps what was counted, and replaces it when closed again', function () {
    Livewire::test(DailyClose::class)
        ->set('counted', '۱۲۵۰')
        ->set('note', 'a note')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSeeHtml('data-closed');

    $close = CashClose::sole();
    expect($close->business_date->toDateString())->toBe(today()->toDateString())
        ->and($close->expected_cash)->toBe(1300.0)
        ->and($close->counted_cash)->toBe(1250.0)
        ->and($close->difference)->toBe(-50.0)
        ->and((float) $close->totals['pos'])->toBe(4000.0)
        ->and($close->note)->toBe('a note')
        ->and($close->closed_by)->toBe($this->user->id);

    Livewire::test(DailyClose::class)->assertSet('counted', Utils::formatQuantity(1250, 2, ''))->assertSet('note', 'a note')
        ->set('counted', '1300')->call('save');

    expect(CashClose::count())->toBe(1)
        ->and(CashClose::sole()->difference)->toBe(0.0);
});

it('warns when the day\'s payments changed after it was closed', function () {
    resolve(CashCloseService::class)->close(today(), 1300, null, null);

    Livewire::test(DailyClose::class)->assertDontSeeHtml('data-changed-since-close');

    ($this->pay)(100, PaymentMethod::CASH);

    Livewire::test(DailyClose::class)->assertSeeHtml('data-changed-since-close');
});

it('looks at another day and loads what was saved for it', function () {
    resolve(CashCloseService::class)->close(today()->subDay(), 6900, 'yesterday', null);

    Livewire::test(DailyClose::class)
        ->set('date', ($this->jalali)(today()->subDay()))
        ->assertSeeHtml('data-expected="7000"')
        ->assertSet('counted', Utils::formatQuantity(6900, 2, ''))
        ->assertSet('note', 'yesterday')
        ->set('date', ($this->jalali)(today()->subDays(2)))
        ->assertSet('counted', '')
        ->assertSeeHtml('data-no-payments');
});

it('needs a counted amount that is a number', function (string $counted) {
    Livewire::test(DailyClose::class)->set('counted', $counted)->call('save')->assertHasErrors(['counted']);

    expect(CashClose::count())->toBe(0);
})->with(['', 'lots', '-5']);

it('lists the recent closes with how much each was off', function () {
    resolve(CashCloseService::class)->close(today()->subDay(), 6900, null, null);

    Livewire::test(DailyClose::class)->assertSeeHtml('data-close="'.today()->subDay()->toDateString().'"');
});

it('is served behind sign-in', function () {
    $this->get(route('payments.close'))->assertOk()->assertSee(__('order::strings.Daily Close'));

    auth()->logout();
    $this->get(route('payments.close'))->assertRedirect();
});
