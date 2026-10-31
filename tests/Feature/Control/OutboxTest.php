<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Modules\Control\Models\OutboxEvent;
use Modules\Control\Services\SyncEntities;
use Modules\Dashboard\Models\Category;
use Modules\Dashboard\Models\Setting;
use Modules\Inventory\Models\Brand;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\UnitGroup;
use Modules\Inventory\Models\Warehouse;
use Modules\Inventory\Models\WarehouseItem;
use Modules\User\Models\User;

beforeEach(function () {
    Cache::flush();
    config(['control.url' => 'https://control.test', 'control.token' => 'secret']);
});

function makeBrand(string $name = 'Bosch'): Brand
{
    return Brand::create(['slug' => str($name)->slug()->toString(), 'name' => $name, 'is_active' => true]);
}

test('a stand-alone shop collects nothing', function () {
    config(['control.url' => null, 'control.token' => null]);

    makeBrand();

    expect(OutboxEvent::count())->toBe(0);
});

test('creating a record queues its complete allowlisted state, database defaults included', function () {
    $brand = Brand::create(['slug' => 'bosch', 'name' => 'Bosch']);

    $event = OutboxEvent::sole();

    expect($event->event_type)->toBe('brand.created')
        ->and($event->aggregate_type)->toBe('brand')
        ->and($event->payload)->toMatchArray(['id' => $brand->id, 'slug' => 'bosch', 'name' => 'Bosch', 'is_active' => 1, 'deleted_at' => null])
        ->and($event->payload)->toHaveKeys(['created_at', 'updated_at', 'description'])
        ->and($event->pushed_at)->toBeNull();
});

test('every record always gets the same aggregate id and different types never collide', function () {
    $entities = resolve(SyncEntities::class);

    expect($entities->aggregateId('brand', 5))->toBe($entities->aggregateId('brand', 5))
        ->and($entities->aggregateId('brand', 5))->not->toBe($entities->aggregateId('brand', 6))
        ->and($entities->aggregateId('brand', 5))->not->toBe($entities->aggregateId('warehouse', 5))
        ->and($entities->aggregateId('brand', 5))->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-5[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/');

    $brand = makeBrand();
    $brand->update(['name' => 'Bosch GmbH']);

    expect(OutboxEvent::pluck('aggregate_id')->unique())->toHaveCount(1);
});

test('an update queues the new state, and an update of nothing sent queues nothing', function () {
    $brand = makeBrand();

    $brand->update(['name' => 'Bosch GmbH']);
    $brand->touch();

    $events = OutboxEvent::orderBy('id')->get();

    expect($events->pluck('event_type')->all())->toBe(['brand.created', 'brand.updated'])
        ->and($events[1]->payload['name'])->toBe('Bosch GmbH');
});

test('soft deletes, restores and hard deletes each queue exactly one event', function () {
    $brand = makeBrand();

    $brand->delete();
    $brand->restore();
    $brand->forceDelete();

    $events = OutboxEvent::orderBy('id')->get();

    expect($events->pluck('event_type')->all())->toBe(['brand.created', 'brand.deleted', 'brand.restored', 'brand.deleted'])
        ->and($events[1]->payload['deleted_at'])->not->toBeNull()
        ->and($events[2]->payload['deleted_at'])->toBeNull();
});

test('incrementing stock quantity is a change worth sending', function () {
    // Real parent rows, so the foreign keys hold on every database (disabling them
    // does nothing on SQLite inside a test transaction).
    $group = UnitGroup::forceCreate(['name' => 'Count', 'slug' => 'count']);
    $category = Category::forceCreate(['name' => 'Oil', 'slug' => 'oil', 'type' => 'item', 'depth' => 0]);
    $oil = Item::forceCreate(['name' => 'Oil', 'slug' => 'oil-item', 'unit_group_id' => $group->id, 'category_id' => $category->id]);
    $main = Warehouse::forceCreate(['name' => 'Main', 'slug' => 'main']);
    $item = WarehouseItem::forceCreate(['slug' => 'main-oil', 'warehouse_id' => $main->id, 'item_id' => $oil->id, 'quantity' => 10]);
    $before = OutboxEvent::where('aggregate_type', 'warehouse_item')->count();

    $item->increment('quantity', 5);

    $latest = OutboxEvent::where('aggregate_type', 'warehouse_item')->latest('id')->first();

    expect(OutboxEvent::where('aggregate_type', 'warehouse_item')->count())->toBe($before + 1)
        ->and($latest->event_type)->toBe('warehouse_item.updated')
        ->and((float) $latest->payload['quantity'])->toBe(15.0);
});

test('secrets never reach the outbox', function () {
    $user = User::factory()->create([
        'first_name' => 'Ali',
        'password' => 'a-very-secret-Password-1',
        'mobile' => '09120000000',
    ]);
    $user->forceFill(['otp_code' => '123456', 'otp_issued_at' => now(), 'remember_token' => 'remember'])->save();

    $events = OutboxEvent::where('aggregate_type', 'user')->get();
    $json = $events->toJson();

    expect($events)->toHaveCount(1)
        ->and($events->first()->payload)->toHaveKeys(['id', 'first_name', 'mobile'])
        ->and(array_keys($events->first()->payload))->not->toContain('password', 'otp_code', 'otp_issued_at', 'remember_token')
        ->and($json)->not->toContain('a-very-secret-Password-1')
        ->not->toContain('123456')
        ->not->toContain('remember');
});

test('no credential field is allowlisted and settings are not synchronized', function () {
    $forbidden = ['password', 'otp_code', 'otp_issued_at', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes', 'token', 'api_key', 'value'];

    foreach (config('control.sync.entities') as $class => $entity) {
        expect(array_intersect($forbidden, $entity['fields']))->toBe([], "{$class} must not send a credential");
    }

    expect(array_keys(config('control.sync.entities')))->not->toContain(Setting::class);
});

test('a broken outbox never stops the shop from saving its own data', function () {
    Schema::drop('control_outbox_events');

    $brand = makeBrand();

    expect($brand->exists)->toBeTrue()
        ->and(Brand::count())->toBe(1);
});

test('a rolled back change leaves no event behind', function () {
    try {
        DB::transaction(function () {
            makeBrand();

            throw new RuntimeException('abort');
        });
    } catch (RuntimeException) {
    }

    expect(Brand::count())->toBe(0)->and(OutboxEvent::count())->toBe(0);
});

test('every configured model exists and is observed', function () {
    foreach (resolve(SyncEntities::class)->models() as $class) {
        expect(class_exists($class))->toBeTrue()
            ->and($class::getEventDispatcher())->not->toBeNull();
    }

    expect(resolve(SyncEntities::class)->models())->toHaveCount(23);
});
