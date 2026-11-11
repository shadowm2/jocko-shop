<?php

use Livewire\Livewire;
use Modules\User\Enums\UserPermission;
use Modules\User\Enums\UserRole;
use Modules\User\Enums\UserType;
use Modules\User\Livewire\RoleCreate;
use Modules\User\Livewire\RoleEdit;
use Modules\User\Livewire\RoleList;
use Modules\User\Livewire\UserEdit;
use Modules\User\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->manager = User::factory()->create(['type' => UserType::Admin])->assignRole(UserRole::Manager->value);
    $this->actingAs($this->manager);
    $this->role = fn (string $name) => Role::where('name', $name)->firstOrFail();
});

it('seeds the manager with every permission and the cashier with none', function () {
    expect(($this->role)('manager')->permissions->pluck('name')->sort()->values()->all())->toBe(collect(UserPermission::values())->sort()->values()->all())
        ->and(($this->role)('cashier')->permissions)->toBeEmpty();
});

it('lists the roles', function () {
    $this->get(route('roles.index'))->assertOk()->assertSee(__('user::strings.role.manager'))->assertSee(__('user::strings.role.cashier'));
});

it('is only for those allowed to manage roles', function () {
    $cashier = User::factory()->create(['type' => UserType::Admin])->assignRole('cashier');

    $this->actingAs($cashier)->get(route('roles.index'))->assertForbidden();
    $this->actingAs($this->manager)->get(route('roles.index'))->assertOk();
});

it('creates a role with permissions that really unlock those pages', function () {
    Livewire::test(RoleCreate::class)
        ->set('form.name', 'Accountant')
        ->set('form.permissions', [UserPermission::ViewReports->value])
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('roles.index'));

    $accountant = User::factory()->create(['type' => UserType::Admin])->assignRole('Accountant');

    $this->actingAs($accountant)->get(route('reports.index'))->assertOk();
    $this->actingAs($accountant)->get(route('backups.index'))->assertForbidden();
});

it('validates the name and the permissions', function () {
    Livewire::test(RoleCreate::class)->set('form.name', '')->call('save')->assertHasErrors(['form.name']);
    Livewire::test(RoleCreate::class)->set('form.name', 'manager')->call('save')->assertHasErrors(['form.name']);
    Livewire::test(RoleCreate::class)->set('form.name', 'Clerk')->set('form.permissions', ['nuke.everything'])->call('save')->assertHasErrors(['form.permissions.0']);
});

it('edits a custom role: its name and permissions', function () {
    $role = Role::create(['name' => 'Clerk', 'guard_name' => 'web']);

    Livewire::test(RoleEdit::class, ['role' => $role])
        ->set('form.name', 'Senior clerk')
        ->set('form.permissions', [UserPermission::ManageItems->value, UserPermission::ManageStock->value])
        ->call('save')
        ->assertHasNoErrors();

    expect($role->fresh()->name)->toBe('Senior clerk')
        ->and($role->fresh()->permissions->pluck('name')->sort()->values()->all())->toBe(['items.manage', 'stock.manage']);
});

it('lets the cashier permissions be changed but not its name', function () {
    $cashier = ($this->role)('cashier');

    Livewire::test(RoleEdit::class, ['role' => $cashier])
        ->set('form.name', 'Hacked')
        ->set('form.permissions', [UserPermission::ViewReports->value])
        ->call('save');

    expect($cashier->fresh()->name)->toBe('cashier')
        ->and($cashier->fresh()->permissions->pluck('name')->all())->toBe(['reports.view']);
});

it('never takes a permission away from the manager', function () {
    $manager = ($this->role)('manager');

    Livewire::test(RoleEdit::class, ['role' => $manager])->set('form.permissions', [])->call('save');

    expect($manager->fresh()->permissions)->toHaveCount(count(UserPermission::cases()));
});

it('deletes a custom role that nobody has', function () {
    $role = Role::create(['name' => 'Temp', 'guard_name' => 'web']);

    Livewire::test(RoleList::class)->call('delete', $role->id);

    expect(Role::where('name', 'Temp')->exists())->toBeFalse();
});

it('does not delete a built-in role or one that staff still have', function () {
    $used = Role::create(['name' => 'Used', 'guard_name' => 'web']);
    User::factory()->create(['type' => UserType::Admin])->assignRole('Used');

    Livewire::test(RoleList::class)
        ->call('delete', ($this->role)('cashier')->id)
        ->call('delete', ($this->role)('manager')->id)
        ->call('delete', $used->id);

    expect(Role::whereIn('name', ['cashier', 'manager', 'Used'])->count())->toBe(3);
});

it('gives a staff member a role from the user page, and only managers can', function () {
    $staff = User::factory()->create(['type' => UserType::Admin]);

    Livewire::test(UserEdit::class, ['user' => $staff])
        ->set('form.role', 'cashier')
        ->call('save')
        ->assertHasNoErrors();

    expect($staff->fresh()->hasRole('cashier'))->toBeTrue();

    $this->actingAs(User::factory()->create(['type' => UserType::Admin])->assignRole('cashier'));

    Livewire::test(UserEdit::class, ['user' => $staff])->set('form.role', 'manager')->call('save');

    expect($staff->fresh()->hasRole('cashier'))->toBeTrue()->and($staff->fresh()->hasRole('manager'))->toBeFalse();
});

it('keeps staff without a role at full access', function () {
    $this->actingAs(User::factory()->create(['type' => UserType::Admin]));

    $this->get(route('roles.index'))->assertOk();
    $this->get(route('reports.index'))->assertOk();
});

it('does not show the role picker for customers', function () {
    $customer = User::factory()->create(['type' => UserType::Customer]);

    $this->get(route('users.edit', $customer))->assertOk()->assertDontSee(__('user::strings.No role (full access)'));
    $this->get(route('users.edit', User::factory()->create(['type' => UserType::Admin])))->assertSee(__('user::strings.No role (full access)'));
});

it('shows a built-in role\'s name as text in the current language and a custom role\'s as an input', function () {
    $this->get(route('roles.edit', ($this->role)('cashier')))
        ->assertOk()
        ->assertSee('data-builtin-role-name', false)
        ->assertSee(__('user::strings.role.cashier'))
        ->assertDontSee('wire:model="form.name"', false);

    $custom = Role::create(['name' => 'Clerk', 'guard_name' => 'web']);

    $this->get(route('roles.edit', $custom))->assertOk()->assertSee('wire:model="form.name"', false)->assertDontSee('data-builtin-role-name', false);
});
