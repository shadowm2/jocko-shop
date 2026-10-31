<?php

use Modules\Control\Models\OutboxEvent;
use Modules\Inventory\Models\Supplier;
use Modules\Inventory\Services\SupplierService;
use Modules\User\Models\User;

beforeEach(function () {
    $this->supplier = resolve(SupplierService::class)->create([
        'first_name' => 'Sara', 'last_name' => 'Parts', 'mobile' => '09121111111', 'email' => 'sara@example.test',
        'phone' => '021111', 'address' => 'Old street', 'notes' => null, 'is_active' => true,
    ]);
});

test('editing only the person of a supplier saves the person', function () {
    resolve(SupplierService::class)->update($this->supplier->id, [
        'first_name' => 'Sarah', 'last_name' => 'Parts', 'mobile' => '09122222222', 'email' => 'sarah@example.test',
        'phone' => '021111', 'address' => 'Old street', 'notes' => null, 'is_active' => true,
    ]);

    $user = User::findOrFail($this->supplier->user_id);

    expect($user->first_name)->toBe('Sarah')
        ->and($user->mobile)->toBe('09122222222')
        ->and($user->email)->toBe('sarah@example.test');
});

test('editing the supplier itself still works and leaves the person alone', function () {
    resolve(SupplierService::class)->update($this->supplier->id, [
        'first_name' => 'Sara', 'last_name' => 'Parts', 'mobile' => '09121111111', 'email' => 'sara@example.test',
        'phone' => '021999', 'address' => 'New street', 'notes' => 'VIP', 'is_active' => false,
    ]);

    $supplier = Supplier::findOrFail($this->supplier->id);

    expect($supplier->phone)->toBe('021999')
        ->and($supplier->address)->toBe('New street')
        ->and($supplier->notes)->toBe('VIP')
        ->and((bool) $supplier->is_active)->toBeFalse()
        ->and(User::findOrFail($supplier->user_id)->first_name)->toBe('Sara');
});

test('a person-only edit reaches Jocko Control as a user update', function () {
    config(['control.url' => 'https://control.test', 'control.token' => 'secret']);

    resolve(SupplierService::class)->update($this->supplier->id, [
        'first_name' => 'Sarah', 'last_name' => 'Parts', 'mobile' => '09121111111', 'email' => 'sara@example.test',
        'phone' => '021111', 'address' => 'Old street', 'notes' => null, 'is_active' => true,
    ]);

    $event = OutboxEvent::where('aggregate_type', 'user')->latest('id')->firstOrFail();

    expect($event->event_type)->toBe('user.updated')
        ->and($event->payload['id'])->toBe($this->supplier->user_id)
        ->and($event->payload['first_name'])->toBe('Sarah');
});
