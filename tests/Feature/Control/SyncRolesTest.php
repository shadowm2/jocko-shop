<?php

use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Modules\Control\Models\OutboxEvent;
use Modules\User\Enums\UserType;
use Modules\User\Livewire\StaffList;
use Modules\User\Models\User;
use Modules\User\Services\StaffRoles;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Cache::flush();
    config(['control.url' => 'https://control.test', 'control.token' => 'secret']);
    $this->staff = User::factory()->create(['type' => UserType::Admin, 'first_name' => 'Reza']);
    OutboxEvent::query()->delete();
});

it('sends a new role with its name', function () {
    Role::create(['name' => 'Accountant', 'guard_name' => 'web']);

    $event = OutboxEvent::where('aggregate_type', 'role')->sole();

    expect($event->event_type)->toBe('role.created')->and($event->payload)->toMatchArray(['name' => 'Accountant', 'guard_name' => 'web', 'permissions' => '']);
});

it('sends what a role may do whenever that changes', function () {
    $role = Role::create(['name' => 'Accountant', 'guard_name' => 'web']);
    OutboxEvent::query()->delete();

    resolve(StaffRoles::class)->setPermissions($role, ['reports.view', 'backups.manage']);

    $event = OutboxEvent::where('aggregate_type', 'role')->sole();

    expect($event->event_type)->toBe('role.updated')->and($event->payload['permissions'])->toBe('backups.manage,reports.view');
});

it('sends which role a staff member has whenever that changes', function () {
    resolve(StaffRoles::class)->assign($this->staff, 'cashier');

    $event = OutboxEvent::where('aggregate_type', 'user')->sole();

    expect($event->event_type)->toBe('user.updated')
        ->and($event->payload)->toMatchArray(['id' => $this->staff->id, 'roles' => 'cashier']);

    OutboxEvent::query()->delete();
    resolve(StaffRoles::class)->assign($this->staff, null);

    expect(OutboxEvent::where('aggregate_type', 'user')->sole()->payload['roles'])->toBe('');
});

it('sends the change when a manager picks a role in the staff list', function () {
    $manager = User::factory()->create(['type' => UserType::Admin])->assignRole('manager');
    $this->actingAs($manager);
    OutboxEvent::query()->delete();

    Livewire::test(StaffList::class)->call('setRole', $this->staff->id, 'cashier');

    expect(OutboxEvent::where('aggregate_type', 'user')->sole()->payload['roles'])->toBe('cashier');
});

it('sends the change when a role is set from the command line', function () {
    $this->artisan('user:role', ['mobile' => $this->staff->mobile, 'role' => 'cashier'])->assertSuccessful();

    expect(OutboxEvent::where('aggregate_type', 'user')->sole()->payload['roles'])->toBe('cashier');
});

it('sends a changed password as a hash and never the password itself', function () {
    $this->staff->forceFill(['password' => 'brand-new-password'])->save();

    $event = OutboxEvent::where('aggregate_type', 'user')->sole();

    expect($event->event_type)->toBe('user.updated')
        ->and($event->payload['password'])->toStartWith('$2y$')
        ->and(Illuminate\Support\Facades\Hash::check('brand-new-password', $event->payload['password']))->toBeTrue()
        ->and($event->toJson())->not->toContain('brand-new-password');
});

it('sends a deleted role as deleted', function () {
    $role = Role::create(['name' => 'Temp', 'guard_name' => 'web']);
    OutboxEvent::query()->delete();

    $role->delete();

    expect(OutboxEvent::where('aggregate_type', 'role')->sole()->event_type)->toBe('role.deleted');
});
