<?php

use Livewire\Livewire;
use Modules\Car\Models\Car;
use Modules\Car\Models\UserCar;
use Modules\Inventory\Models\Warehouse;
use Modules\Order\Enums\OrderStatus;
use Modules\Order\Enums\PaymentMethod;
use Modules\Order\Enums\PaymentStatus;
use Modules\Order\Livewire\OrdersList;
use Modules\Order\Livewire\PaymentsList;
use Modules\Order\Models\Order;
use Modules\Order\Models\OrderItem;
use Modules\Order\Models\Payment;
use Modules\Order\Services\OrderService;
use Modules\Order\Services\PaymentService;
use Modules\User\Models\User;
use Morilog\Jalali\Jalalian;

// Bare rows only (see OrderStockTest): foreign key checks are deferred.
beforeEach(function () {
    deferForeignKeys();

    $ali = User::factory()->create(['first_name' => 'Ali', 'last_name' => 'Rezaei', 'mobile' => '09121111111']);
    $sara = User::factory()->create(['first_name' => 'Sara', 'last_name' => 'Karimi', 'mobile' => '09352222222']);
    $car = Car::forceCreate(['name' => 'Pride', 'slug' => 'pride', 'car_company_id' => 1]);
    $userCar = UserCar::forceCreate(['slug' => 'uc1', 'user_id' => $ali->id, 'car_id' => $car->id, 'color_id' => 1]);
    $warehouse = Warehouse::forceCreate(['name' => 'Main', 'slug' => 'main']);

    $order = fn (int $number, User $user, OrderStatus $status, string $createdAt) => tap(Order::forceCreate([
        'slug' => "o{$number}", 'user_id' => $user->id, 'user_car_id' => $userCar->id, 'warehouse_id' => $warehouse->id,
        'order_number' => sprintf('ORD-%05d', $number), 'status' => $status, 'created_at' => $createdAt,
    ]), fn (Order $order) => OrderItem::forceCreate(['slug' => "oi{$number}", 'order_id' => $order->id, 'item_id' => 1, 'quantity' => 2, 'unit_price' => 5000, 'total' => 10000]));
    $pay = fn (Order $order, float $amount, PaymentMethod $method, string $paidAt) => Payment::forceCreate([
        'slug' => 'p'.Payment::withTrashed()->count(), 'order_id' => $order->id, 'amount' => $amount, 'method' => $method, 'paid_at' => $paidAt,
    ]);

    $this->unpaid = $order(1, $ali, OrderStatus::PENDING, '2026-03-01 12:00:00');
    $this->partial = $order(2, $sara, OrderStatus::COMPLETED, '2026-03-10 12:00:00');
    $this->paid = $order(3, $sara, OrderStatus::PENDING, '2026-03-20 12:00:00');
    $this->voided = $order(4, $ali, OrderStatus::CANCELLED, '2026-03-25 12:00:00');

    $pay($this->partial, 4000, PaymentMethod::CASH, '2026-03-12');
    $pay($this->paid, 6000, PaymentMethod::CARD, '2026-03-15');
    $pay($this->paid, 4000, PaymentMethod::CASH, '2026-03-22');
    // A deleted payment counts for nothing, so this order is still unpaid.
    $pay($this->voided, 10000, PaymentMethod::CASH, '2026-03-26')->delete();

    $this->jalali = fn (string $date) => Jalalian::fromDateTime($date)->format('Y/m/d');
    // A fresh service per call: the repository keeps its filters between calls.
    $this->orders = fn (array $filters) => resolve(OrderService::class)->getOrders($filters, paginate: false)
        ->pluck('order_number')->sort()->values()->all();
    $this->payments = fn (array $filters) => resolve(PaymentService::class)->getPayments($filters, paginate: false)
        ->map(fn (Payment $payment) => (float) $payment->amount)->sort()->values()->all();
});

it('finds orders by number, customer name or mobile, in either digit set', function (string $term, array $numbers) {
    expect(($this->orders)(['search' => $term]))->toBe($numbers);
})->with([
    'customer name' => ['Sara', ['ORD-00002', 'ORD-00003']],
    'mobile' => ['09121111111', ['ORD-00001', 'ORD-00004']],
    'persian digits' => ['۰۹۱۲۱۱۱۱۱۱۱', ['ORD-00001', 'ORD-00004']],
    'order number' => ['ORD-00003', ['ORD-00003']],
    'part of a number' => ['00002', ['ORD-00002']],
    'no match' => ['nobody', []],
    'blank' => ['  ', ['ORD-00001', 'ORD-00002', 'ORD-00003', 'ORD-00004']],
]);

it('filters orders by status and ignores a status that does not exist', function () {
    expect(($this->orders)(['status' => 'completed']))->toBe(['ORD-00002'])
        ->and(($this->orders)(['status' => 'bogus']))->toHaveCount(4);
});

it('filters orders by payment status the same way the badge computes it', function (PaymentStatus $status, array $numbers) {
    $badge = resolve(OrderService::class)->getOrders([], paginate: false)
        ->filter(fn (Order $order) => $order->paymentStatus() === $status)
        ->pluck('order_number')->sort()->values()->all();

    expect(($this->orders)(['payment_status' => $status->value]))->toBe($numbers)->toBe($badge);
})->with([
    'unpaid' => [PaymentStatus::UNPAID, ['ORD-00001', 'ORD-00004']],
    'partial' => [PaymentStatus::PARTIAL, ['ORD-00002']],
    'paid' => [PaymentStatus::PAID, ['ORD-00003']],
]);

it('filters orders by a Jalali creation date range and skips a malformed date', function () {
    $day = ($this->jalali)('2026-03-10');

    expect(($this->orders)(['created_from' => $day]))->toBe(['ORD-00002', 'ORD-00003', 'ORD-00004'])
        ->and(($this->orders)(['created_to' => $day]))->toBe(['ORD-00001', 'ORD-00002'])
        ->and(($this->orders)(['created_from' => $day, 'created_to' => $day]))->toBe(['ORD-00002'])
        ->and(($this->orders)(['created_from' => 'garbage', 'created_to' => '1404/13/45x']))->toHaveCount(4);
});

it('filters payments by customer, method and Jalali payment date', function () {
    $day = ($this->jalali)('2026-03-15');

    expect(($this->payments)(['search' => 'Sara']))->toBe([4000.0, 4000.0, 6000.0])
        ->and(($this->payments)(['search' => 'ORD-00002']))->toBe([4000.0])
        ->and(($this->payments)(['search' => 'Ali']))->toBe([])
        ->and(($this->payments)(['method' => 'card']))->toBe([6000.0])
        ->and(($this->payments)(['method' => 'bogus']))->toHaveCount(3)
        ->and(($this->payments)(['paid_from' => $day]))->toBe([4000.0, 6000.0])
        ->and(($this->payments)(['paid_to' => $day]))->toBe([4000.0, 6000.0])
        ->and(($this->payments)(['paid_from' => $day, 'paid_to' => $day]))->toBe([6000.0]);
});

it('narrows the orders page as filters are set and restores it on clear', function () {
    Livewire::test(OrdersList::class)
        ->assertSee(['ORD-00001', 'ORD-00002', 'ORD-00003', 'ORD-00004'])
        ->set('filters.payment_status', 'paid')
        ->assertSee('ORD-00003')
        ->assertDontSee(['ORD-00001', 'ORD-00002', 'ORD-00004'])
        ->call('clearFilters')
        ->assertSee(['ORD-00001', 'ORD-00002', 'ORD-00003', 'ORD-00004'])
        ->set('filters.search', 'Sara')
        ->assertSee(['ORD-00002', 'ORD-00003'])
        ->assertDontSee(['ORD-00001', 'ORD-00004']);
});

it('narrows the payments page by method', function () {
    Livewire::test(PaymentsList::class)
        ->assertSee(['ORD-00002', 'ORD-00003'])
        ->set('filters.method', 'card')
        ->assertSee('ORD-00003')
        ->assertDontSee('ORD-00002');
});
