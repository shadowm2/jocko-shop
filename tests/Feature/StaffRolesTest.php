<?php

use Modules\Order\Enums\PaymentMethod;
use Modules\Order\Models\Payment;
use Modules\User\Enums\UserRole;
use Modules\User\Enums\UserType;
use Modules\User\Models\User;

beforeEach(function () {
    $this->staff = fn (?UserRole $role = null): User => tap(
        User::factory()->create(['type' => UserType::Admin]),
        fn (User $user) => $role === null ?: $user->assignRole($role->value),
    );
});

it('keeps cashiers out of the manager-only pages', function (string $route) {
    $this->actingAs(($this->staff)(UserRole::Cashier))->get(route($route))->assertForbidden();
})->with([
    'reports' => 'reports.index',
    'system settings' => 'system-settings.edit',
    'jocko control settings' => 'jocko-control-settings.edit',
    'queued changes' => 'jocko-control-queue.index',
    'backups' => 'backups.index',
    'warehouse settings' => 'warehouse-settings.edit',
    'purchases' => 'purchases.index',
    'new purchase' => 'purchases.create',
    'suppliers' => 'suppliers.index',
    'new item' => 'items.create',
    'stock adjustment' => 'stock-movements.create',
    'stock transfer' => 'stock-movements.transfer',
]);

it('lets cashiers work the counter', function (string $route) {
    $this->actingAs(($this->staff)(UserRole::Cashier))->get(route($route))->assertOk();
})->with([
    'dashboard' => 'dashboard',
    'orders' => 'orders.index',
    'new order' => 'orders.create',
    'payments' => 'payments.index',
    'daily close' => 'payments.close',
    'customers' => 'users.index',
]);

it('lets managers and staff without a role into the manager-only pages', function (?UserRole $role) {
    $this->actingAs(($this->staff)($role));

    foreach (['reports.index', 'backups.index', 'purchases.index', 'items.create'] as $route) {
        $this->get(route($route))->assertOk();
    }
})->with([
    'manager' => UserRole::Manager,
    'no role' => null,
]);

it('keeps cashiers from editing recorded payments', function () {
    deferForeignKeys();
    $payment = Payment::forceCreate(['slug' => 'p1', 'order_id' => 1, 'amount' => 3000, 'method' => PaymentMethod::CASH, 'paid_at' => today()]);

    $this->actingAs(($this->staff)(UserRole::Cashier))->get(route('payments.edit', $payment))->assertForbidden();
});

it('hides the manager-only links from cashiers', function () {
    $this->actingAs(($this->staff)(UserRole::Cashier))->get(route('orders.index'))
        ->assertOk()
        ->assertDontSee(route('reports.index'), false)
        ->assertDontSee(route('backups.index'), false)
        ->assertDontSee(route('purchases.index'), false)
        ->assertSee(route('payments.close'), false);

    $this->actingAs(($this->staff)(UserRole::Manager))->get(route('orders.index'))
        ->assertSee(route('reports.index'), false)
        ->assertSee(route('backups.index'), false);
});

it('sets a staff member\'s role from the command line', function () {
    $user = ($this->staff)(UserRole::Manager);

    $this->artisan('user:role', ['mobile' => $user->mobile, 'role' => 'cashier'])->assertSuccessful();

    expect($user->fresh()->isCashier())->toBeTrue()->and($user->fresh()->hasRole('manager'))->toBeFalse();
});

it('refuses an unknown role or a customer', function () {
    $customer = User::factory()->create(['type' => UserType::Customer]);

    $this->artisan('user:role', ['mobile' => $customer->mobile, 'role' => 'cashier'])->assertFailed();
    $this->artisan('user:role', ['mobile' => $customer->mobile, 'role' => 'boss'])->assertFailed();
    expect($customer->fresh()->roles)->toBeEmpty();
});
