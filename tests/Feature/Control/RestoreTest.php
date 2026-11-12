<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Modules\Car\Models\Car;
use Modules\Car\Models\CarCompany;
use Modules\Car\Models\Color;
use Modules\Car\Models\UserCar;
use Modules\Control\Models\OutboxEvent;
use Modules\Control\Services\ShopRestorer;
use Modules\Control\Services\SyncEntities;
use Modules\Dashboard\Livewire\JockoControlSettings;
use Modules\Dashboard\Models\Category;
use Modules\Inventory\Models\Brand;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\UnitGroup;
use Modules\Inventory\Models\Warehouse;
use Modules\Inventory\Models\WarehouseItem;
use Modules\Order\Models\CashClose;
use Modules\User\Enums\UserPermission;
use Modules\User\Enums\UserRole;
use Modules\User\Enums\UserType;
use Modules\User\Models\User;
use Modules\User\Services\StaffRoles;
use Spatie\Permission\Models\Role;

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

    $staffRoles = resolve(StaffRoles::class);
    $accountant = Role::create(['name' => 'Accountant', 'guard_name' => 'web']);
    $staffRoles->setPermissions($accountant, ['reports.view', 'backups.manage']);
    $staffRoles->assign($admin, 'manager');
    $staffRoles->assign(User::factory()->create(['type' => UserType::Admin, 'first_name' => 'Reza', 'password' => 'reza-secret']), 'Accountant');

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
    // A fresh installation has the built-in roles from its migration.
    foreach (UserRole::cases() as $builtIn) {
        Role::findOrCreate($builtIn->value, 'web');
    }
    Role::findByName('manager')->syncPermissions(UserPermission::values());
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

    expect(User::pluck('first_name')->sort()->values()->all())->toBe(['Ali', 'Maryam', 'Reza'])
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
            // A role is found by its name: its id on the new installation may differ.
            $restored = $type === 'role'
                ? Role::findByName($record['name'])
                : resolve(SyncEntities::class)->queryWithDeleted($class)->find($record['id']);
            $ignore = $type === 'role' ? ['id', 'created_at', 'updated_at'] : [];

            expect(Illuminate\Support\Arr::except(resolve(SyncEntities::class)->payloadOf($restored), $ignore))->toEqual(Illuminate\Support\Arr::except($record, $ignore), "{$type} #{$record['id']} differs");
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

it('puts back passwords, roles and what each role may do', function () {
    $mirror = mirrorThenLoseTheShopForRestore();
    fakeRestoreFromControl($mirror);

    $this->artisan('control:restore', ['--yes' => true])->assertSuccessful();

    $reza = User::where('first_name', 'Reza')->sole();

    expect(Hash::check('reza-secret', $reza->password))->toBeTrue()
        ->and($reza->getRoleNames()->all())->toBe(['Accountant'])
        ->and(User::where('first_name', 'Maryam')->sole()->getRoleNames()->all())->toBe(['manager'])
        ->and(Role::findByName('Accountant')->permissions->pluck('name')->sort()->values()->all())->toBe(['backups.manage', 'reports.view'])
        ->and(Role::findByName('manager')->permissions)->toHaveCount(8);
});

it('does not duplicate the built-in roles a fresh installation already has', function () {
    $mirror = mirrorThenLoseTheShopForRestore();
    fakeRestoreFromControl($mirror);

    $this->artisan('control:restore', ['--yes' => true])->assertSuccessful();

    expect(Role::where('name', 'manager')->count())->toBe(1);
});

it('lets the person who set up the new installation be put back as they were', function () {
    $mirror = mirrorThenLoseTheShopForRestore();
    $first = collect($mirror['user'])->firstWhere('first_name', 'Maryam');
    User::factory()->create(['id' => $first['id'], 'type' => UserType::Admin, 'first_name' => 'Installer', 'password' => 'installer-pass']);
    fakeRestoreFromControl($mirror);

    $this->artisan('control:restore', ['--yes' => true])->assertSuccessful();

    $restored = User::findOrFail($first['id']);
    expect($restored->first_name)->toBe('Maryam')->and($restored->password)->toBe($first['password']);
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

it('restores from the settings page with a button, the same as the command', function () {
    $mirror = mirrorThenLoseTheShopForRestore();
    fakeRestoreFromControl($mirror);
    $this->actingAs(User::factory()->create());

    Livewire::test(JockoControlSettings::class)
        ->assertSee('data-restore-check', false)
        ->assertDontSee('data-restore-now', false)
        ->call('checkRestore')
        ->assertSet('restoreError', null)
        ->assertSee('data-restore-available', false)
        ->assertSee('data-restore-now', false)
        ->call('restore')
        ->assertSet('restorable', null)
        ->assertSet('restoreError', null);

    expect(Brand::pluck('name')->all())->toBe(['Bosch'])
        ->and(WarehouseItem::sole()->quantity)->toEqual(10)
        ->and(Brand::onlyTrashed()->pluck('name')->all())->toBe(['Mann']);
});

it('tells the page when an administrator has not allowed the restore', function () {
    fakeRestoreFromControl([], allowed: false);
    $this->actingAs(User::factory()->create());

    Livewire::test(JockoControlSettings::class)
        ->call('checkRestore')
        ->assertSet('restorable', null)
        ->assertSet('restoreError', __('dashboard::messages.Restore Not Allowed'))
        ->assertSee('data-restore-error', false);
});

it('will not restore over existing data from the page until the box is ticked', function () {
    $mirror = mirrorThenLoseTheShopForRestore();
    Brand::create(['slug' => 'existing', 'name' => 'Existing']);
    fakeRestoreFromControl($mirror);
    $this->actingAs(User::factory()->create());

    Livewire::test(JockoControlSettings::class)
        ->call('checkRestore')
        ->assertSee('data-restore-existing', false)
        ->call('restore')
        ->assertSet('restoreError', __('dashboard::messages.Restore Needs Confirmation'));

    expect(Brand::pluck('name')->all())->toBe(['Existing']);

    Livewire::test(JockoControlSettings::class)
        ->call('checkRestore')
        ->set('restoreOverExisting', true)
        ->call('restore')
        ->assertSet('restoreError', null);

    expect(Brand::pluck('name')->sort()->values()->all())->toBe(['Bosch', 'Existing']);
});

it('says so on the page when the shop is not linked to Control', function () {
    config(['control.url' => null, 'control.token' => null]);
    $this->actingAs(User::factory()->create());

    Livewire::test(JockoControlSettings::class)
        ->call('checkRestore')
        ->assertSet('restoreError', __('dashboard::messages.Restore Not Configured'));
});

it('keeps the page usable and tells how to retry when Control fails halfway', function () {
    $mirror = mirrorThenLoseTheShopForRestore();
    Http::fake([
        'control.test/api/v1/restore' => Http::response(['data' => ['entities' => array_map('count', $mirror)]]),
        'control.test/api/v1/restore/*' => Http::response('boom', 500),
    ]);
    $this->actingAs(User::factory()->create());

    Livewire::test(JockoControlSettings::class)
        ->call('checkRestore')
        ->call('restore')
        ->assertSee('data-restore-error', false);
});
