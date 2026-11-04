<?php

use Livewire\Livewire;
use Modules\Car\Models\Car;
use Modules\Car\Models\UserCar;
use Modules\Dashboard\Exceptions\SmsException;
use Modules\Dashboard\Facades\Sms;
use Modules\Inventory\Models\Warehouse;
use Modules\Order\Enums\OrderStatus;
use Modules\Order\Livewire\OrderEdit;
use Modules\Order\Livewire\VehicleHistory;
use Modules\Order\Models\Order;
use Modules\Order\Services\ServiceReminderService;
use Modules\User\Enums\UserType;
use Modules\User\Models\User;
use Morilog\Jalali\Jalalian;

beforeEach(function () {
    deferForeignKeys();
    $this->travelTo(now()->startOfDay()->addHours(9));

    $this->admin = User::factory()->create();
    $this->admin->forceFill(['type' => UserType::Admin])->save();
    $this->actingAs($this->admin);

    $this->warehouse = Warehouse::forceCreate(['name' => 'Main', 'slug' => 'main']);
    $this->car = Car::forceCreate(['slug' => 'pride', 'name' => 'Pride', 'car_company_id' => 1]);
    $this->n = 0;

    $this->customer = User::factory()->create(['first_name' => 'Ali', 'last_name' => 'Test', 'mobile' => '09121234567']);
    $this->customer->forceFill(['type' => UserType::Customer, 'slug' => 'ali'])->save();
    $this->userCar = UserCar::forceCreate(['slug' => 'uc-main', 'user_id' => $this->customer->id, 'car_id' => $this->car->id, 'color_id' => 1]);

    $this->visit = function (array $attributes = [], ?UserCar $userCar = null): Order {
        $n = ++$this->n;
        $userCar ??= $this->userCar;

        return Order::forceCreate($attributes + [
            'slug' => "o{$n}", 'user_id' => $userCar->user_id, 'user_car_id' => $userCar->id, 'warehouse_id' => $this->warehouse->id,
            'order_number' => sprintf('ORD-%05d', $n), 'status' => OrderStatus::COMPLETED,
        ]);
    };
    $this->remind = fn () => resolve(ServiceReminderService::class)->remindDue();
});

it('texts the customer a few days before the service date, once', function () {
    config(['app.name' => 'Jocko Garage']);
    $order = ($this->visit)(['next_service_at' => today()->addDays(2)]);
    $sent = [];
    Sms::shouldReceive('send')->once()->andReturnUsing(function (string $mobile, string $message) use (&$sent) {
        $sent = [$mobile, $message];

        return [];
    });

    expect(($this->remind)())->toBe(['sent' => 1, 'skipped' => 0, 'failed' => 0])
        ->and($sent[0])->toBe('09121234567')
        ->and($sent[1])->toContain('Jocko Garage', 'Pride', 'Ali')
        ->and($order->fresh()->service_reminded_at)->not->toBeNull();

    // A second run finds nothing left to say (the mock would fail on a second send).
    expect(($this->remind)())->toBe(['sent' => 0, 'skipped' => 0, 'failed' => 0]);
});

it('leaves out dates that are far away, long gone, cancelled or already answered by a later visit', function () {
    Sms::shouldReceive('send')->never();

    ($this->visit)(['next_service_at' => today()->addDays(10)]);
    ($this->visit)(['next_service_at' => today()->subDays(90)]);
    ($this->visit)(['next_service_at' => today()->addDay(), 'status' => OrderStatus::CANCELLED]);
    ($this->visit)(['next_service_at' => null]);

    // The same car came back and was serviced again after the date was set.
    $other = UserCar::forceCreate(['slug' => 'uc-other', 'user_id' => $this->customer->id, 'car_id' => $this->car->id, 'color_id' => 1]);
    ($this->visit)(['next_service_at' => today()->addDay()], $other);
    ($this->visit)([], $other);

    expect(($this->remind)())->toBe(['sent' => 0, 'skipped' => 0, 'failed' => 0]);
});

it('skips a customer without a mobile and retries after a failed text', function () {
    $this->customer->forceFill(['mobile' => ''])->save();
    $order = ($this->visit)(['next_service_at' => today()->addDay()]);

    expect(($this->remind)())->toBe(['sent' => 0, 'skipped' => 1, 'failed' => 0]);

    $this->customer->forceFill(['mobile' => '09121234567'])->save();
    Sms::shouldReceive('send')->once()->andThrow(new SmsException('down'));
    expect(($this->remind)())->toBe(['sent' => 0, 'skipped' => 0, 'failed' => 1])
        ->and($order->fresh()->service_reminded_at)->toBeNull();
});

it('reminds again when the service date is changed', function () {
    $order = ($this->visit)(['next_service_at' => today()->addDay(), 'service_reminded_at' => now()]);

    expect(($this->remind)()['sent'])->toBe(0);

    $order->update(['next_service_at' => today()->addDays(2)]);
    Sms::shouldReceive('send')->once()->andReturn([]);

    expect(($this->remind)()['sent'])->toBe(1);
});

it('is off when the lead time is negative', function () {
    config(['service.remind_days_before' => -1]);
    ($this->visit)(['next_service_at' => today()]);
    Sms::shouldReceive('send')->never();

    expect(($this->remind)()['sent'])->toBe(0);
});

it('runs from the scheduler every morning', function () {
    $event = collect(app(Illuminate\Console\Scheduling\Schedule::class)->events())
        ->first(fn ($event) => str_contains($event->command ?? '', 'service:remind'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('30 9 * * *');
});

it('shows a car\'s visits, the highest odometer and when it is due next', function () {
    ($this->visit)(['odometer' => 60000, 'created_at' => now()->subMonths(8)]);
    ($this->visit)(['odometer' => 72500, 'next_service_at' => today()->subDays(2), 'created_at' => now()->subDay()]);
    ($this->visit)(['odometer' => 99999, 'status' => OrderStatus::CANCELLED]);

    Livewire::test(VehicleHistory::class, ['user' => $this->customer, 'userCar' => $this->userCar])
        ->assertOk()
        ->assertSeeHtml('data-visits="2"')
        ->assertSeeHtml('data-odometer="72500"')
        ->assertSeeHtml('data-next-service="'.today()->subDays(2)->toDateString().'"')
        ->assertSee(__('order::strings.Overdue'))
        ->assertSeeHtml('data-visit="ORD-00001"')
        ->assertSeeHtml('data-visit="ORD-00003"');
});

it('does not show a car under someone else', function () {
    $stranger = User::factory()->create();

    Livewire::test(VehicleHistory::class, ['user' => $stranger, 'userCar' => $this->userCar])->assertNotFound();
});

it('serves the history page and links to it from the customer\'s cars', function () {
    $url = route('users.cars.history', ['user' => $this->customer, 'userCar' => $this->userCar]);

    $this->get($url)->assertOk()->assertSee('Pride');
    $this->get(route('users.cars.index', ['user' => $this->customer]))->assertOk()->assertSee($url, false);
});

it('saves the odometer and the next service date from the order form', function () {
    $order = ($this->visit)();
    $jalali = Jalalian::fromDateTime(today()->addMonths(6))->format('Y/m/d');

    Livewire::test(OrderEdit::class, ['order' => $order])
        ->set('form.odometer', '85,000')
        ->set('form.next_service_at', $jalali)
        ->call('save')
        ->assertHasNoErrors();

    $order->refresh();
    expect($order->odometer)->toBe(85000)
        ->and($order->next_service_at->toDateString())->toBe(today()->addMonths(6)->toDateString());

    Livewire::test(OrderEdit::class, ['order' => $order])
        ->set('form.odometer', '')
        ->set('form.next_service_at', '')
        ->call('save')
        ->assertHasNoErrors();

    expect($order->fresh()->odometer)->toBeNull()
        ->and($order->fresh()->next_service_at)->toBeNull();
});

it('rejects an odometer that is not a number', function () {
    Livewire::test(OrderEdit::class, ['order' => ($this->visit)()])
        ->set('form.odometer', 'lots')
        ->call('save')
        ->assertHasErrors(['form.odometer']);
});
