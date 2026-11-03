<?php

use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Modules\Dashboard\Livewire\WarehouseSettings;
use Modules\Dashboard\Services\SettingService;
use Modules\Inventory\Livewire\Purchases\PurchaseCreate;
use Modules\Inventory\Livewire\StockMovements\StockAdjustmentCreate;
use Modules\Inventory\Livewire\StockMovements\StockMovementList;
use Modules\Inventory\Livewire\StockMovements\StockTransferCreate;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\UnitGroup;
use Modules\Inventory\Models\Warehouse;
use Modules\Inventory\Models\WarehouseItem;
use Modules\Inventory\Services\WarehouseService;
use Modules\Order\Livewire\Forms\OrderForm;
use Modules\Order\Livewire\OrderCreate;
use Modules\Order\Models\Order;
use Modules\User\Models\User;

beforeEach(function () {
    deferForeignKeys();
    Cache::flush();
    $this->actingAs(User::factory()->create());

    $group = UnitGroup::forceCreate(['name' => 'Count', 'slug' => 'count']);
    $this->main = Warehouse::forceCreate(['name' => 'Main', 'slug' => 'main']);
    $this->spare = Warehouse::forceCreate(['name' => 'Spare', 'slug' => 'spare']);

    $stock = function (string $name, Warehouse $warehouse) use ($group) {
        $item = Item::forceCreate(['name' => $name, 'slug' => str($name)->slug(), 'unit_group_id' => $group->id, 'category_id' => 1]);

        return WarehouseItem::forceCreate(['slug' => $warehouse->slug.'-'.$item->slug, 'warehouse_id' => $warehouse->id, 'item_id' => $item->id, 'quantity' => 5, 'min_quantity' => 1]);
    };
    $stock('Oil', $this->main);
    $stock('Filter', $this->main);
    $stock('Spare belt', $this->spare);

    $this->warehouses = fn () => resolve(WarehouseService::class);
    $this->setting = function (string $key, ?string $value): void {
        resolve(SettingService::class)->set($key, $value);
        Cache::flush();
    };
    $this->single = fn () => ($this->setting)('warehouses.multiple', '0') ?? ($this->setting)('warehouses.default', (string) $this->main->id);
});

it('keeps working as before until someone decides: several warehouses means multiple', function () {
    expect(($this->warehouses)()->isMultiple())->toBeTrue();

    $this->spare->delete();

    expect(($this->warehouses)()->isMultiple())->toBeFalse();
});

it('follows the setting once it is made, whatever the number of warehouses', function () {
    ($this->setting)('warehouses.multiple', '0');
    expect(($this->warehouses)()->isMultiple())->toBeFalse();

    $this->spare->delete();
    ($this->setting)('warehouses.multiple', '1');
    expect(($this->warehouses)()->isMultiple())->toBeTrue();
});

it('uses the chosen default warehouse while only one is in use, and none when several are', function () {
    expect(($this->warehouses)()->fixedWarehouse())->toBeNull();

    ($this->single)();
    expect(($this->warehouses)()->fixedWarehouse()->id)->toBe($this->main->id)
        ->and(($this->warehouses)()->getActiveWarehouse()->id)->toBe($this->main->id);

    ($this->setting)('warehouses.default', (string) $this->spare->id);
    expect(($this->warehouses)()->fixedWarehouse()->id)->toBe($this->spare->id);
});

it('falls back to the best stocked warehouse when no default is chosen or it is gone', function () {
    ($this->setting)('warehouses.multiple', '0');

    expect(($this->warehouses)()->defaultWarehouse())->toBeNull()
        ->and(($this->warehouses)()->fixedWarehouse()->id)->toBe($this->main->id);

    ($this->setting)('warehouses.default', '9999');
    expect(($this->warehouses)()->defaultWarehouse())->toBeNull()
        ->and(($this->warehouses)()->fixedWarehouse()->id)->toBe($this->main->id);
});

it('hides the warehouses menu only when one is in use and the default exists', function () {
    expect(($this->warehouses)()->showsWarehouseMenu())->toBeTrue();

    ($this->setting)('warehouses.multiple', '0');
    expect(($this->warehouses)()->showsWarehouseMenu())->toBeTrue('no default chosen yet: the menu must stay so one can be created');

    ($this->setting)('warehouses.default', (string) $this->main->id);
    expect(($this->warehouses)()->showsWarehouseMenu())->toBeFalse();

    $this->main->delete();
    expect(($this->warehouses)()->showsWarehouseMenu())->toBeTrue('the default no longer exists');
});

it('shows the warehouses menu and transfers while several warehouses are used', function () {
    $page = $this->get(route('dashboard'))->assertOk();

    $page->assertSee(route('warehouses.index'), false)
        ->assertSee(route('stock-movements.transfer'), false);
});

it('replaces the warehouses menu with the stock pages when only one is used', function () {
    ($this->single)();

    $this->get(route('dashboard'))->assertOk()
        ->assertDontSee(route('warehouses.index'), false)
        ->assertDontSee(route('warehouses.create'), false)
        ->assertDontSee(route('stock-movements.transfer'), false)
        ->assertSee(route('warehouses.items.index'), false)
        ->assertSee(route('warehouses.items.low-stock'), false)
        ->assertSee(route('warehouse-settings.edit'), false);
});

it('has no stock transfer page while only one warehouse is used', function () {
    Livewire::test(StockTransferCreate::class)->assertOk();

    ($this->single)();

    Livewire::test(StockTransferCreate::class)->assertNotFound();
});

it('offers only the default warehouse stock in a stock adjustment, without the warehouse name', function () {
    Livewire::test(StockAdjustmentCreate::class)->assertSee('Spare belt')->assertSee('Main —', false);

    ($this->single)();

    Livewire::test(StockAdjustmentCreate::class)
        ->assertSee('Oil')
        ->assertSee('Filter')
        ->assertDontSee('Spare belt')
        ->assertDontSee('Main —', false);
});

it('drops the warehouse column and filter from the stock movements list', function () {
    $count = fn () => Livewire::test(StockMovementList::class)->viewData('filterDefinitions');

    expect(collect($count())->pluck('name')->all())->toContain('warehouse_id');

    ($this->single)();

    expect(collect($count())->pluck('name')->all())->not->toContain('warehouse_id');
});

it('gives a new order the default warehouse and no picker', function () {
    ($this->single)();

    $form = Livewire::test(OrderCreate::class)->assertSet('form.warehouse', 'main')->instance()->form;

    expect($form->showsWarehousePicker())->toBeFalse();
});

it('makes the user pick the warehouse of a new order while several are used', function () {
    $form = Livewire::test(OrderCreate::class)->assertSet('form.warehouse', '')->instance()->form;

    expect($form->showsWarehousePicker())->toBeTrue();
});

it('shows the picker when one warehouse is meant but none exists yet, so the page still works', function () {
    Warehouse::query()->delete();
    ($this->setting)('warehouses.multiple', '0');

    $form = Livewire::test(OrderCreate::class)->assertOk()->instance()->form;

    expect($form->warehouse)->toBe('')->and($form->showsWarehousePicker())->toBeTrue();
});

it('overrides a stale warehouse on a new order but never moves an existing one', function () {
    ($this->single)();

    $form = new OrderForm(Livewire::test(OrderCreate::class)->instance(), 'form');
    $form->warehouse = 'spare';
    $form->useFixedWarehouse();
    expect($form->warehouse)->toBe('main');

    $existing = Order::forceCreate(['slug' => 'o1', 'user_id' => 1, 'user_car_id' => 1, 'warehouse_id' => $this->spare->id, 'order_number' => 'ORD-1', 'status' => 'pending']);
    $form = new OrderForm(Livewire::test(OrderCreate::class)->instance(), 'form');
    $form->order = $existing;
    $form->warehouse = 'spare';
    $form->useFixedWarehouse();
    expect($form->warehouse)->toBe('spare');
});

it('saves the warehouse settings and insists on a default while only one is used', function () {
    Livewire::test(WarehouseSettings::class)
        ->assertSet('multiple', true)
        ->set('multiple', false)
        ->set('default', '')
        ->call('save')
        ->assertHasErrors(['default']);

    Livewire::test(WarehouseSettings::class)
        ->set('multiple', false)
        ->set('default', (string) $this->spare->id)
        ->call('save')
        ->assertHasNoErrors();

    Cache::flush();
    expect(($this->warehouses)()->isMultiple())->toBeFalse()
        ->and(($this->warehouses)()->defaultWarehouse()->id)->toBe($this->spare->id);

    Livewire::test(WarehouseSettings::class)
        ->assertSet('multiple', false)
        ->assertSet('default', (string) $this->spare->id)
        ->set('multiple', true)
        ->call('save')
        ->assertHasNoErrors();

    Cache::flush();
    expect(($this->warehouses)()->isMultiple())->toBeTrue();
});

it('refuses a default warehouse that does not exist', function () {
    Livewire::test(WarehouseSettings::class)
        ->set('multiple', false)
        ->set('default', '9999')
        ->call('save')
        ->assertHasErrors(['default']);
});

it('gives a new purchase the default warehouse and no picker', function () {
    ($this->single)();

    $form = Livewire::test(PurchaseCreate::class)->assertSet('form.warehouse', 'main')->instance()->form;

    expect($form->showsWarehousePicker())->toBeFalse();
});

it('makes the user pick the warehouse of a new purchase while several are used', function () {
    $form = Livewire::test(PurchaseCreate::class)->assertSet('form.warehouse', '')->instance()->form;

    expect($form->showsWarehousePicker())->toBeTrue();
});

it('renders the warehouse settings page and links to it from the menu', function () {
    $this->get(route('warehouse-settings.edit'))->assertOk()
        ->assertSee(route('warehouse-settings.edit'), false)
        ->assertSee(__('dashboard::strings.Default Warehouse'));
});
