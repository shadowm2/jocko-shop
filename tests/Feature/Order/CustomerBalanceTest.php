<?php

use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Modules\Car\Models\Car;
use Modules\Car\Models\UserCar;
use Modules\Dashboard\Exceptions\SmsException;
use Modules\Dashboard\Facades\Sms;
use Modules\Inventory\Models\Warehouse;
use Modules\Order\Enums\OrderStatus;
use Modules\Order\Enums\PaymentMethod;
use Modules\Order\Livewire\CustomerStatement;
use Modules\Order\Livewire\Debts;
use Modules\Order\Models\Order;
use Modules\Order\Models\OrderItem;
use Modules\Order\Models\Payment;
use Modules\Order\Models\PaymentReminder;
use Modules\Order\Services\CustomerBalanceService;
use Modules\User\Enums\UserType;
use Modules\User\Models\User;

beforeEach(function () {
    deferForeignKeys();
    Cache::flush();
    $this->travelTo(now()->startOfSecond());

    $this->admin = User::factory()->create();
    $this->admin->forceFill(['type' => UserType::Admin])->save();
    $this->actingAs($this->admin);

    $this->warehouse = Warehouse::forceCreate(['name' => 'Main', 'slug' => 'main']);
    $this->car = Car::forceCreate(['slug' => 'pride', 'name' => 'Pride', 'car_company_id' => 1]);
    $this->n = 0;

    $this->customer = function (string $name, ?string $mobile) {
        $user = User::factory()->create(['first_name' => $name, 'last_name' => 'Test', 'mobile' => $mobile ?? '']);
        $user->forceFill(['type' => UserType::Customer, 'slug' => strtolower($name)])->save();

        return $user;
    };
    $this->order = function (User $user, float $total, float $paid, OrderStatus $status = OrderStatus::PENDING, $at = null) {
        $n = ++$this->n;
        $car = UserCar::forceCreate(['slug' => "uc{$n}", 'user_id' => $user->id, 'car_id' => $this->car->id, 'color_id' => 1]);
        $order = Order::forceCreate(['slug' => "o{$n}", 'user_id' => $user->id, 'user_car_id' => $car->id, 'warehouse_id' => $this->warehouse->id, 'order_number' => sprintf('ORD-%05d', $n), 'status' => $status, 'created_at' => $at ?? now()]);
        OrderItem::forceCreate(['slug' => "oi{$n}", 'order_id' => $order->id, 'item_id' => 1, 'unit_id' => 1, 'quantity' => 1, 'unit_price' => $total, 'total' => $total]);

        if ($paid > 0) {
            Payment::forceCreate(['slug' => "p{$n}", 'order_id' => $order->id, 'amount' => $paid, 'method' => PaymentMethod::CASH, 'paid_at' => today()]);
        }

        return $order;
    };
    $this->balances = fn () => resolve(CustomerBalanceService::class);

    $this->ali = ($this->customer)('Ali', '09121234567');
    $this->sara = ($this->customer)('Sara', '09351112233');
    $this->noPhone = ($this->customer)('Nophone', null);
});

it('works out what a customer owes, leaving cancelled orders out', function () {
    ($this->order)($this->ali, 1000, 400);
    ($this->order)($this->ali, 500, 500);
    ($this->order)($this->ali, 9000, 0, OrderStatus::CANCELLED);

    expect(($this->balances)()->summary($this->ali))->toBe(['total' => 1500.0, 'paid' => 900.0, 'balance' => 600.0, 'open_orders' => 1])
        ->and(($this->balances)()->orders($this->ali))->toHaveCount(2);
});

it('lists the customers who owe, the most first, and nobody else', function () {
    ($this->order)($this->ali, 1000, 400, at: now()->subDays(20));
    ($this->order)($this->ali, 300, 0, at: now()->subDays(3));
    ($this->order)($this->sara, 5000, 0);
    ($this->order)($this->noPhone, 700, 700);
    ($this->order)($this->noPhone, 9000, 0, OrderStatus::CANCELLED);
    // An administrator's order is not a customer's debt.
    ($this->order)($this->admin, 8000, 0);

    $debtors = ($this->balances)()->debtors();

    expect($debtors->pluck('user.first_name')->all())->toBe(['Sara', 'Ali'])
        ->and($debtors[0]['balance'])->toBe(5000.0)
        ->and($debtors[1]['balance'])->toBe(900.0)
        ->and($debtors[1]['open_orders'])->toBe(2)
        ->and((int) $debtors[1]['oldest_at']->diffInDays(now()))->toBe(20)
        ->and($debtors[1]['last_reminder_at'])->toBeNull();
});

it('texts a customer what they owe and remembers it', function () {
    ($this->order)($this->ali, 1000, 400);
    config(['app.name' => 'Jocko Garage']);
    Sms::shouldReceive('send')->once()->withArgs(fn (string $to, string $message) => $to === '09121234567'
        && str_contains($message, 'Jocko Garage')
        && str_contains($message, 'Ali Test')
        && str_contains($message, '۶۰۰'));

    $reminder = ($this->balances)()->remind($this->ali, $this->admin->id);

    expect($reminder->status)->toBe('sent')->and((float) $reminder->amount)->toBe(600.0)->and($reminder->orders_count)->toBe(1)->and($reminder->sent_by)->toBe($this->admin->id)
        ->and(($this->balances)()->debtors()[0]['last_reminder_at'])->not->toBeNull();
});

it('does not text the same customer twice in a week, unless asked to', function () {
    ($this->order)($this->ali, 1000, 0);
    Sms::shouldReceive('send')->times(3);

    ($this->balances)()->remind($this->ali);
    Cache::flush();

    expect(fn () => ($this->balances)()->remind($this->ali))->toThrow(InvalidArgumentException::class, '7');

    ($this->balances)()->remind($this->ali, force: true);
    Cache::flush();
    $this->travel(8)->days();
    ($this->balances)()->remind($this->ali);

    expect(PaymentReminder::where('status', 'sent')->count())->toBe(3);
});

it('does not text twice for two clicks at once', function () {
    ($this->order)($this->ali, 1000, 0);
    Sms::shouldReceive('send')->once();

    ($this->balances)()->remind($this->ali, force: true);

    expect(fn () => ($this->balances)()->remind($this->ali, force: true))->toThrow(InvalidArgumentException::class);
});

it('has nothing to say to a customer without a mobile or without a debt', function () {
    ($this->order)($this->noPhone, 1000, 0);
    ($this->order)($this->sara, 1000, 1000);
    Sms::shouldReceive('send')->never();

    expect(fn () => ($this->balances)()->remind($this->noPhone))->toThrow(InvalidArgumentException::class)
        ->and(fn () => ($this->balances)()->remind($this->sara))->toThrow(InvalidArgumentException::class);
});

it('records a failed text and lets the next try go through', function () {
    ($this->order)($this->ali, 1000, 0);
    Sms::shouldReceive('send')->once()->andThrow(new SmsException('no credit'));
    Sms::shouldReceive('send')->once();

    expect(fn () => ($this->balances)()->remind($this->ali))->toThrow(SmsException::class);
    expect(PaymentReminder::sole()->status)->toBe('failed')->and(PaymentReminder::sole()->error)->toBe('no credit')
        ->and(($this->balances)()->debtors()[0]['last_reminder_at'])->toBeNull();

    ($this->balances)()->remind($this->ali);
    expect(PaymentReminder::where('status', 'sent')->count())->toBe(1);
});

it('reminds everyone who owes and says how it went', function () {
    ($this->order)($this->ali, 1000, 0);
    ($this->order)($this->sara, 2000, 0);
    ($this->order)($this->noPhone, 3000, 0);
    Sms::shouldReceive('send')->twice()->andReturnUsing(function (string $to) {
        if ($to === '09351112233') {
            throw new SmsException('fails');
        }

        return [];
    });

    expect(($this->balances)()->remindAll($this->admin->id))->toBe(['sent' => 1, 'skipped' => 1, 'failed' => 1]);
});

it('shows who owes on the debts page and sends reminders from it', function () {
    ($this->order)($this->ali, 1000, 400);
    ($this->order)($this->sara, 5000, 0);

    $this->get(route('payments.debts'))->assertOk()
        ->assertSee('data-total-owed="5600"', false)
        ->assertSeeInOrder(['data-debtor="'.$this->sara->id.'"', 'data-debtor="'.$this->ali->id.'"'], false)
        ->assertSee('data-remind-all', false)
        ->assertSee(route('users.statement', $this->ali), false);

    Sms::shouldReceive('send')->once();
    Livewire::test(Debts::class)->call('remind', $this->ali->id)->assertOk();
    expect(PaymentReminder::sole()->user_id)->toBe($this->ali->id);

    Livewire::test(Debts::class)->call('remind', $this->ali->id)->assertOk();
    expect(PaymentReminder::count())->toBe(1);
});

it('says nobody owes anything when that is so', function () {
    ($this->order)($this->ali, 1000, 1000);

    $this->get(route('payments.debts'))->assertOk()->assertSee('data-no-debts', false)->assertDontSee('data-remind-all', false);
});

it('prints a customer\'s statement with every order, the payments and the balance', function () {
    ($this->order)($this->ali, 1000, 400);
    ($this->order)($this->ali, 500, 500);
    ($this->order)($this->ali, 9000, 0, OrderStatus::CANCELLED);
    ($this->order)($this->sara, 7000, 0);

    $this->get(route('users.statement', $this->ali))->assertOk()
        ->assertSee('data-statement-order="ORD-00001"', false)
        ->assertSee('data-statement-order="ORD-00002"', false)
        ->assertDontSee('data-statement-order="ORD-00003"', false)
        ->assertDontSee('data-statement-order="ORD-00004"', false)
        ->assertSee('data-balance="600"', false)
        ->assertSee('window.print()', false);

    Livewire::test(CustomerStatement::class, ['user' => $this->ali])->assertViewHas('payments', fn ($payments) => $payments->count() === 2);
});

it('needs a signed in user for the statement and the debts', function () {
    auth()->logout();

    $this->get(route('users.statement', $this->ali))->assertRedirect(route('login'));
    $this->get(route('payments.debts'))->assertRedirect(route('login'));
});

it('links to the statement from the users list and the debts from the menu', function () {
    $this->get(route('users.index'))->assertOk()->assertSee(route('users.statement', $this->ali), false);
    $this->get(route('dashboard'))->assertOk()->assertSee(route('payments.debts'), false);
});
