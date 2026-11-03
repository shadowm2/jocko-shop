<?php

use Livewire\Livewire;
use Modules\Inventory\Livewire\WarehouseItems\LowStockList;
use Modules\Inventory\Livewire\WarehouseItems\WarehouseItemList;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\UnitGroup;
use Modules\Inventory\Models\Warehouse;
use Modules\Inventory\Models\WarehouseItem;
use Modules\Inventory\Services\WarehouseItemService;
use Modules\User\Models\User;

// Bare rows only (see OrderStockTest): foreign key checks are deferred.
beforeEach(function () {
    deferForeignKeys();

    $group = UnitGroup::forceCreate(['name' => 'Count', 'slug' => 'count']);
    $this->main = Warehouse::forceCreate(['name' => 'Main', 'slug' => 'main']);
    $this->spare = Warehouse::forceCreate(['name' => 'Spare', 'slug' => 'spare']);

    $this->stock = function (string $name, Warehouse $warehouse, float $quantity, ?float $min) use ($group) {
        $item = Item::firstOrCreate(['slug' => $name], ['name' => ucfirst($name), 'unit_group_id' => $group->id, 'category_id' => 1]);

        return WarehouseItem::forceCreate([
            'slug' => "{$warehouse->slug}-{$name}", 'warehouse_id' => $warehouse->id, 'item_id' => $item->id,
            'quantity' => $quantity, 'min_quantity' => $min,
        ]);
    };
    $this->low = fn (array $filters = []) => resolve(WarehouseItemService::class)
        ->getWarehouseItems([...$filters, 'low_stock' => true], paginate: false);

    ($this->stock)('oil', $this->main, 3, 10);       // below its minimum
    ($this->stock)('filter', $this->main, 10, 10);   // exactly at it
    ($this->stock)('wiper', $this->main, 11, 10);    // fine
    ($this->stock)('brake', $this->main, 0, null);   // no minimum, never low
    ($this->stock)('oil', $this->spare, 1, 5);       // low in the other warehouse
});

it('flags items at or below their minimum, never those without one', function () {
    expect(WarehouseItem::lowStock()->count())->toBe(3)
        ->and(WarehouseItem::where('slug', 'main-oil')->first()->isLowStock())->toBeTrue()
        ->and(WarehouseItem::where('slug', 'main-filter')->first()->isLowStock())->toBeTrue()
        ->and(WarehouseItem::where('slug', 'main-wiper')->first()->isLowStock())->toBeFalse()
        ->and(WarehouseItem::where('slug', 'main-brake')->first()->isLowStock())->toBeFalse();
});

it('lists the furthest below the minimum first and filters by warehouse and name', function () {
    expect(($this->low)()->pluck('slug')->all())->toBe(['main-oil', 'spare-oil', 'main-filter'])
        ->and(($this->low)(['warehouse_id' => $this->spare->id])->pluck('slug')->all())->toBe(['spare-oil'])
        ->and(($this->low)(['search' => 'filt'])->pluck('slug')->all())->toBe(['main-filter']);
});

it('shows the low stock page to a signed in user', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('warehouses.items.low-stock'))->assertOk()->assertSee('Oil')->assertDontSee('Wiper');

    Livewire::test(LowStockList::class)
        ->set('filters.warehouse_id', (string) $this->spare->id)
        ->assertViewHas('rows', fn ($rows) => $rows->count() === 1);
});

it('badges only the low rows of the warehouse items list', function () {
    $this->actingAs(User::factory()->create());

    // Main holds the most items, so it is the one listed: oil and filter are low, wiper and brake are not.
    $html = Livewire::test(WarehouseItemList::class)->assertOk()->html();

    expect(substr_count($html, __('inventory::strings.Low Stock')))->toBe(2);
});

it('links the dashboard low stock card to the low stock page', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('dashboard'))->assertOk()->assertSee(route('warehouses.items.low-stock'), false);
});
