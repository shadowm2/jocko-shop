<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Modules\Car\Models\Car;
use Modules\Car\Models\CarCompany;
use Modules\Car\Models\Color;
use Modules\Car\Models\UserCar;
use Modules\Control\Models\OutboxEvent;
use Modules\Control\Services\ShopRestorer;
use Modules\Control\Services\SyncEntities;
use Modules\Dashboard\Models\Category;
use Modules\Inventory\Models\Brand;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\UnitGroup;
use Modules\Inventory\Models\Warehouse;
use Modules\Inventory\Models\WarehouseItem;
use Modules\Order\Models\CashClose;
use Modules\User\Enums\UserType;
use Modules\User\Models\User;

beforeEach(function () {
    Cache::flush();
    deferForeignKeys();
    config(['control.url' => 'https://control.test', 'control.token' => 'secret']);
});

/**
 * Builds a small shop, then plays Jocko Control: what the shop would have sent
 * is exactly what Control would hand back. The shop is then emptied.
 *
 * @return array<string, list<array<string, mixed>>> entity type => mirrored records
 */
function mirrorThenLoseTheShopForRestore(): array
{
    $admin = User::factory()->create(['type' => UserType::Admin, 'first_name' => 'Maryam']);
    User::factory()->create(['type' => UserType::Customer, 'first_name' => 'Ali']);
    Brand::create(['slug' => 'bosch', 'name' => 'Bosch']);
    Brand::create(['slug' => 'mann', 'name' => 'Mann'])->delete();
    $group = UnitGroup::forceCreate(['name' => 'Count', 'slug' => 'count']);
    $category = Category::forceCreate(['name' => 'Oil', 'slug' => 'oil', 'type' => 'item', 'depth' => 0]);
    $oil = Item::forceCreate(['name' => 'Oil', 'slug' => 'oil-item', 'unit_group_id' => $group->id, 'category_id' => $category->id]);
    $main = Warehouse::forceCreate(['name' => 'Main', 'slug' => 'main']);
    WarehouseItem::forceCreate(['slug' => 'main-oil', 'warehouse_id' => $main->id, 'item_id' => $oil->id, 'quantity' => 10]);
    CashClose::create(['business_date' => '2026-11-01', 'expected_cash' => 100, 'counted_cash' => 90, 'difference' => -10, 'totals' => ['cash' => 100], 'closed_by' => $admin->id]);

    $customer = User::where('type', UserType::Customer)->firstOrFail();
    $company = CarCompany::forceCreate(['slug' => 'saipa', 'name' => 'Saipa']);
    $car = Car::forceCreate(['slug' => 'pride', 'name' => 'Pride', 'car_company_id' => $company->id]);
    $color = Color::forceCreate(['slug' => 'red', 'name' => 'Red', 'hex' => '#ff0000']);
    // Entered the way the form does: a Jalali date.
    UserCar::forceCreate(['slug' => 'uc-1', 'user_id' => $customer->id, 'car_id' => $car->id, 'color_id' => $color->id, 'manufactured_at' => '1395/05/10', 'description' => 'Daily car']);

    $mirror = [];

    foreach (OutboxEvent::orderBy('id')->get() as $event) {
        $mirror[$event->aggregate_type][$event->payload['id']] = $event->payload;
    }

    foreach (resolve(SyncEntities::class)->models() as $class) {
        resolve(SyncEntities::class)->queryWithDeleted($class)->forceDelete();
    }
    OutboxEvent::query()->delete();

    return array_map('array_values', $mirror);
}

/** Control answers like its RestoreController: counts, then pages by local id. */
function fakeRestoreFromControl(array $mirror, bool $allowed = true): void
{
    Http::fake([
        'control.test/api/v1/restore' => $allowed
            ? Http::response(['data' => ['entities' => array_map('count', $mirror)]])
            : Http::response(['message' => 'Restore is not allowed for this shop.'], 403),
        'control.test/api/v1/restore/*' => function ($request) use ($mirror) {
            $entity = basename(parse_url($request->url(), PHP_URL_PATH));

            return Http::response(['data' => ['records' => $mirror[$entity] ?? [], 'next' => null]]);
        },
    ]);
}

it('rebuilds the business data of a lost shop exactly as it was', function () {
    $mirror = mirrorThenLoseTheShopForRestore();
    expect(Brand::withTrashed()->count())->toBe(0);

    fakeRestoreFromControl($mirror);

    $this->artisan('control:restore', ['--yes' => true])->assertSuccessful();

    expect(User::pluck('first_name')->sort()->values()->all())->toBe(['Ali', 'Maryam'])
        ->and(Brand::pluck('name')->all())->toBe(['Bosch'])
        ->and(Brand::onlyTrashed()->pluck('name')->all())->toBe(['Mann'])
        ->and(WarehouseItem::sole()->quantity)->toEqual(10)
        ->and(CashClose::sole()->difference)->toEqual(-10)
        ->and(CashClose::sole()->totals)->toBe([])
        ->and(UserCar::sole()->manufactured_at->toDateString())->toBe('2016-07-31');

    // Everything restored is state the shop would send again: it must match what Control had.
    foreach ($mirror as $type => $records) {
        foreach ($records as $record) {
            $class = collect(resolve(SyncEntities::class)->models())->first(fn ($c) => resolve(SyncEntities::class)->typeOf(new $c) === $type);
            $restored = resolve(SyncEntities::class)->queryWithDeleted($class)->find($record['id']);

            expect(resolve(SyncEntities::class)->payloadOf($restored))->toEqual($record, "{$type} #{$record['id']} differs");
        }
    }
});

it('does not send the restored records back to Jocko Control', function () {
    $mirror = mirrorThenLoseTheShopForRestore();
    fakeRestoreFromControl($mirror);

    $this->artisan('control:restore', ['--yes' => true])->assertSuccessful();

    expect(OutboxEvent::count())->toBe(0);
});

it('explains that an administrator has to allow the restore', function () {
    fakeRestoreFromControl([], allowed: false);

    $this->artisan('control:restore', ['--yes' => true])
        ->expectsOutputToContain('Allow restore')
        ->assertFailed();
});

it('refuses a shop that already has data unless forced', function () {
    $mirror = mirrorThenLoseTheShopForRestore();
    Brand::create(['slug' => 'existing', 'name' => 'Existing']);
    fakeRestoreFromControl($mirror);

    $this->artisan('control:restore', ['--yes' => true])->expectsOutputToContain('already has data')->assertFailed();
    expect(Brand::pluck('name')->all())->toBe(['Existing']);

    $this->artisan('control:restore', ['--yes' => true, '--force' => true])->assertSuccessful();
    expect(Brand::pluck('name')->sort()->values()->all())->toBe(['Bosch', 'Existing']);
});

it('lets the person who set up the new installation keep their account', function () {
    $mirror = mirrorThenLoseTheShopForRestore();
    $mirror['user'][0]['mobile'] = '09120000001';
    $setup = User::factory()->create(['id' => $mirror['user'][0]['id'], 'type' => UserType::Admin, 'first_name' => 'Installer', 'password' => 'secret-pass']);
    $hash = $setup->password;
    fakeRestoreFromControl($mirror);

    $this->artisan('control:restore', ['--yes' => true])->assertSuccessful();

    expect($setup->fresh()->password)->toBe($hash)
        ->and($setup->fresh()->first_name)->toBe($mirror['user'][0]['first_name']);
});

it('never restores pictures, which would point at files that are not here', function () {
    expect(array_keys(resolve(ShopRestorer::class)->restorable()))->not->toContain('media');
});

it('stops cleanly when Control cannot be reached and can be run again', function () {
    $mirror = mirrorThenLoseTheShopForRestore();
    Http::fake([
        'control.test/api/v1/restore' => Http::response(['data' => ['entities' => array_map('count', $mirror)]]),
        'control.test/api/v1/restore/*' => Http::response('boom', 500),
    ]);

    $this->artisan('control:restore', ['--yes' => true])->expectsOutputToContain('safe to run it again')->assertFailed();

    // Http::fake only appends stubs and the first match wins: start from a fresh client.
    Http::swap(new Illuminate\Http\Client\Factory);
    fakeRestoreFromControl($mirror);
    $this->artisan('control:restore', ['--yes' => true, '--force' => true])->assertSuccessful();
    expect(Brand::count())->toBe(1);
});
