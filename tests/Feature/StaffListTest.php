<?php

use Livewire\Livewire;
use Modules\User\Enums\UserRole;
use Modules\User\Enums\UserType;
use Modules\User\Livewire\StaffList;
use Modules\User\Models\User;

beforeEach(function () {
    $this->manager = User::factory()->create(['type' => UserType::Admin, 'first_name' => 'Maryam', 'last_name' => 'Boss'])->assignRole(UserRole::Manager->value);
    $this->actingAs($this->manager);
});

it('lists only staff, with their role', function () {
    User::factory()->create(['type' => UserType::Admin, 'first_name' => 'Reza', 'last_name' => 'Till'])->assignRole('cashier');
    User::factory()->create(['type' => UserType::Customer, 'first_name' => 'Zahra', 'last_name' => 'Customer']);

    $this->get(route('staff.index'))
        ->assertOk()
        ->assertSee('Maryam')
        ->assertSee('Reza')
        ->assertSee(__('user::strings.role.cashier'))
        ->assertDontSee('Zahra');
});

it('searches staff by name or mobile', function () {
    User::factory()->create(['type' => UserType::Admin, 'first_name' => 'Reza', 'last_name' => 'Till', 'mobile' => '09121234567']);

    Livewire::test(StaffList::class)->set('filters.search', '0912123')->assertSee('Reza')->assertDontSee('Maryam');
    Livewire::test(StaffList::class)->set('filters.search', 'Maryam')->assertSee('Maryam')->assertDontSee('Reza');
});

it('lets a manager assign and clear a role right in the list', function () {
    $staff = User::factory()->create(['type' => UserType::Admin]);

    Livewire::test(StaffList::class)->call('setRole', $staff->id, 'cashier');
    expect($staff->fresh()->hasRole('cashier'))->toBeTrue();

    Livewire::test(StaffList::class)->call('setRole', $staff->id, '');
    expect($staff->fresh()->roles)->toBeEmpty();
});

it('ignores a role that does not exist and refuses customers', function () {
    $staff = User::factory()->create(['type' => UserType::Admin]);
    $customer = User::factory()->create(['type' => UserType::Customer]);

    Livewire::test(StaffList::class)->call('setRole', $staff->id, 'boss');
    expect($staff->fresh()->roles)->toBeEmpty();

    Livewire::test(StaffList::class)->call('setRole', $customer->id, 'cashier')->assertNotFound();
    expect($customer->fresh()->roles)->toBeEmpty();
});

it('does not let a cashier change roles, only see them', function () {
    $cashier = User::factory()->create(['type' => UserType::Admin])->assignRole('cashier');
    $other = User::factory()->create(['type' => UserType::Admin]);

    $this->actingAs($cashier);

    $this->get(route('staff.index'))->assertOk()->assertDontSee('wire:change="setRole', false);
    Livewire::test(StaffList::class)->call('setRole', $other->id, 'manager')->assertForbidden();
    expect($other->fresh()->roles)->toBeEmpty();
});

it('has a first-level Staff menu and no starter-kit links', function () {
    $this->get(route('staff.index'))
        ->assertSee(route('staff.index'), false)
        ->assertSee(route('roles.index'), false)
        ->assertDontSee('livewire-starter-kit', false)
        ->assertDontSee('laravel.com/docs/starter-kits', false);
});

it('shows each staff member\'s current role selected in the list, and the new one after a change', function () {
    $staff = User::factory()->create(['type' => UserType::Admin, 'first_name' => 'Reza'])->assignRole('cashier');

    $selected = fn (string $html): array => collect(
        preg_match_all('/<option[^>]*value="([^"]*)"[^>]*selected|<option[^>]*selected[^>]*value="([^"]*)"/', $html, $m, PREG_SET_ORDER) ? $m : [],
    )->map(fn ($match) => $match[1] !== '' ? $match[1] : ($match[2] ?? ''))->all();

    $component = Livewire::test(StaffList::class);
    expect($selected($component->html()))->toContain('cashier');

    $component->call('setRole', $staff->id, 'manager');
    expect($selected($component->html()))->toContain('manager')->not->toContain('cashier');
});
