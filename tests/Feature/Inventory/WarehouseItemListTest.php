<?php

use Livewire\Livewire;
use Modules\Inventory\Livewire\WarehouseItems\WarehouseItemList;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\UnitGroup;
use Modules\Inventory\Models\Warehouse;
use Modules\Inventory\Models\WarehouseItem;
use Modules\User\Models\User;

// Bare rows only (see OrderStockTest): foreign key checks are deferred.
beforeEach(function () {
    deferForeignKeys();
    $this->actingAs(User::factory()->create());

    $group = UnitGroup::forceCreate(['name' => 'Count', 'slug' => 'count']);
    $warehouse = Warehouse::forceCreate(['name' => 'Main', 'slug' => 'main']);

    // The warehouse holding the most items is the active one.
    foreach (['spark' => 'Spark plug', 'wiper' => 'Wiper blade'] as $slug => $name) {
        $item = Item::forceCreate(['name' => $name, 'slug' => $slug, 'unit_group_id' => $group->id, 'category_id' => 1]);
        WarehouseItem::forceCreate([
            'slug' => "main-{$slug}", 'warehouse_id' => $warehouse->id, 'item_id' => $item->id,
            'quantity' => 5, 'min_quantity' => 1, 'max_quantity' => 9,
        ]);
    }
});

it('links each row to its item and its stock record', function () {
    Livewire::test(WarehouseItemList::class)
        ->assertOk()
        ->assertSeeHtml('href="'.route('items.edit', ['item' => 'spark']).'"')
        ->assertSeeHtml('href="'.route('warehouses.items.edit', ['warehouse' => 'main', 'warehouseItem' => 'main-wiper']).'"');
});

it('narrows the rows by item name and brings them back when cleared', function () {
    Livewire::test(WarehouseItemList::class)
        ->set('filters.search', 'spark')
        ->assertSee('Spark plug')
        ->assertDontSee('Wiper blade')
        ->call('clearFilters')
        ->assertSee('Spark plug')
        ->assertSee('Wiper blade');
});
