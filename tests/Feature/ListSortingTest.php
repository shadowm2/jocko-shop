<?php

use Livewire\Livewire;
use Modules\Car\Models\Car;
use Modules\Car\Models\CarCompany;
use Modules\Car\Models\Color;
use Modules\Car\Models\UserCar;
use Modules\Inventory\Livewire\ItemBrands\ItemBrandList;
use Modules\Inventory\Livewire\UnitGroups\UnitGroupList;
use Modules\Inventory\Livewire\WarehouseItems\WarehouseItemList;
use Modules\Inventory\Livewire\Warehouses\WarehouseList;
use Modules\Inventory\Models\Brand;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\ItemBrand;
use Modules\Inventory\Models\Unit;
use Modules\Inventory\Models\UnitGroup;
use Modules\Inventory\Models\Warehouse;
use Modules\Inventory\Models\WarehouseItem;
use Modules\User\Livewire\UserCarsList;
use Modules\User\Models\User;

// Bare rows only (see OrderStockTest): foreign key checks are deferred.
beforeEach(function () {
    deferForeignKeys();
    $this->actingAs($this->user = User::factory()->create());

    $this->column = fn (string $key) => fn ($rows) => $rows->pluck($key)->all();
});

it('sorts unit groups by name or by how many units they hold', function () {
    $units = fn (string $slug, int $count) => tap(
        UnitGroup::forceCreate(['name' => ucfirst($slug), 'slug' => $slug]),
        fn (UnitGroup $group) => collect(range(1, $count))->each(fn (int $i) => Unit::forceCreate([
            'name' => "{$slug}{$i}", 'slug' => "{$slug}{$i}", 'symbol' => "{$slug[0]}{$i}", 'unit_group_id' => $group->id, 'conversion_factor' => 1, 'is_active' => true,
        ])),
    );
    $units('volume', 1);
    $units('weight', 3);
    $units('area', 2);

    Livewire::test(UnitGroupList::class)
        ->assertViewHas('rows', fn ($rows) => (($this->column)('name'))($rows) === ['Area', 'Volume', 'Weight'])
        ->call('toggleSortDirection')
        ->assertViewHas('rows', fn ($rows) => (($this->column)('name'))($rows) === ['Weight', 'Volume', 'Area'])
        ->set('sortBy', 'units_count')
        ->assertViewHas('rows', fn ($rows) => (($this->column)('units_count'))($rows) === [3, 2, 1]);
});

it('sorts warehouses by item count, most first, or by name', function () {
    $item = Item::forceCreate(['name' => 'Oil', 'slug' => 'oil', 'unit_group_id' => 1, 'category_id' => 1]);
    $stock = fn (string $name, int $count) => tap(
        Warehouse::forceCreate(['name' => $name, 'slug' => strtolower($name)]),
        fn (Warehouse $warehouse) => collect(range(1, $count))->each(fn (int $i) => WarehouseItem::forceCreate([
            'slug' => strtolower($name).$i, 'warehouse_id' => $warehouse->id, 'item_id' => $item->id + $i, 'quantity' => 1,
        ])),
    );
    $stock('Main', 2);
    $stock('Annex', 1);
    $stock('Depot', 3);

    Livewire::test(WarehouseList::class)
        ->assertSet('sortBy', 'warehouse_items_count')
        ->assertViewHas('rows', fn ($rows) => (($this->column)('name'))($rows) === ['Depot', 'Main', 'Annex'])
        ->call('toggleSortDirection')
        ->assertViewHas('rows', fn ($rows) => (($this->column)('name'))($rows) === ['Annex', 'Main', 'Depot'])
        ->set('sortBy', 'name')
        ->assertViewHas('rows', fn ($rows) => (($this->column)('name'))($rows) === ['Annex', 'Depot', 'Main']);
});

it('sorts an items brands by brand name, sku or price, and ignores an unknown sort', function () {
    $item = Item::forceCreate(['name' => 'Oil', 'slug' => 'oil', 'unit_group_id' => 1, 'category_id' => 1]);
    $shell = Brand::forceCreate(['name' => 'Shell', 'slug' => 'shell']);
    $bp = Brand::forceCreate(['name' => 'BP', 'slug' => 'bp']);
    $total = Brand::forceCreate(['name' => 'Total', 'slug' => 'total']);

    ItemBrand::forceCreate(['slug' => 'a', 'item_id' => $item->id, 'brand_id' => $shell->id, 'sku' => 'S-3', 'purchase_price' => 50, 'sale_price' => 90]);
    ItemBrand::forceCreate(['slug' => 'b', 'item_id' => $item->id, 'brand_id' => $bp->id, 'sku' => 'S-1', 'purchase_price' => 70, 'sale_price' => 60]);
    ItemBrand::forceCreate(['slug' => 'c', 'item_id' => $item->id, 'brand_id' => $total->id, 'sku' => 'S-2', 'purchase_price' => 60, 'sale_price' => 80]);

    Livewire::test(ItemBrandList::class, ['item' => $item])
        ->assertViewHas('rows', fn ($rows) => (($this->column)('slug'))($rows) === ['b', 'a', 'c'])
        ->set('sortBy', 'sku')
        ->assertViewHas('rows', fn ($rows) => (($this->column)('slug'))($rows) === ['b', 'c', 'a'])
        ->set('sortBy', 'purchase_price')
        ->call('toggleSortDirection')
        ->assertViewHas('rows', fn ($rows) => (($this->column)('slug'))($rows) === ['b', 'c', 'a'])
        ->set('sortBy', 'sale_price')
        ->assertViewHas('rows', fn ($rows) => (($this->column)('slug'))($rows) === ['a', 'c', 'b'])
        ->set('sortBy', 'slug; drop table users')
        ->assertOk();
});

it('sorts warehouse items by item name or quantity', function () {
    $warehouse = Warehouse::forceCreate(['name' => 'Main', 'slug' => 'main']);
    $group = UnitGroup::forceCreate(['name' => 'Count', 'slug' => 'count']);
    $stock = function (string $name, int $quantity) use ($warehouse, $group) {
        $item = Item::forceCreate(['name' => $name, 'slug' => strtolower($name), 'unit_group_id' => $group->id, 'category_id' => 1]);

        return WarehouseItem::forceCreate(['slug' => strtolower($name), 'warehouse_id' => $warehouse->id, 'item_id' => $item->id, 'quantity' => $quantity]);
    };
    $stock('Oil', 5);
    $stock('Brake pad', 20);
    $stock('Filter', 10);

    Livewire::test(WarehouseItemList::class)
        ->assertViewHas('rows', fn ($rows) => $rows->map(fn ($row) => $row->item->name)->all() === ['Brake pad', 'Filter', 'Oil'])
        ->set('sortBy', 'quantity')
        ->call('toggleSortDirection')
        ->assertViewHas('rows', fn ($rows) => $rows->map(fn ($row) => $row->item->name)->all() === ['Brake pad', 'Filter', 'Oil']);
});

it('sorts a users cars by company, car or color', function () {
    $peugeot = CarCompany::forceCreate(['name' => 'Peugeot', 'slug' => 'peugeot']);
    $kia = CarCompany::forceCreate(['name' => 'Kia', 'slug' => 'kia']);
    $car206 = Car::forceCreate(['name' => '206', 'slug' => '206', 'car_company_id' => $peugeot->id]);
    $pride = Car::forceCreate(['name' => 'Pride', 'slug' => 'pride', 'car_company_id' => $kia->id]);
    $red = Color::forceCreate(['name' => 'Red', 'slug' => 'red', 'hex' => '#f00']);
    $blue = Color::forceCreate(['name' => 'Blue', 'slug' => 'blue', 'hex' => '#00f']);

    $a = UserCar::forceCreate(['slug' => 'a', 'user_id' => $this->user->id, 'car_id' => $car206->id, 'color_id' => $red->id]);
    $b = UserCar::forceCreate(['slug' => 'b', 'user_id' => $this->user->id, 'car_id' => $pride->id, 'color_id' => $red->id]);
    $c = UserCar::forceCreate(['slug' => 'c', 'user_id' => $this->user->id, 'car_id' => $car206->id, 'color_id' => $blue->id]);
    UserCar::forceCreate(['slug' => 'theirs', 'user_id' => User::factory()->create()->id, 'car_id' => $pride->id, 'color_id' => $blue->id]);

    Livewire::test(UserCarsList::class, ['user' => $this->user])
        ->assertSet('sortBy', 'company')
        ->assertViewHas('rows', fn ($rows) => (($this->column)('id'))($rows) === [$b->id, $a->id, $c->id])
        ->call('toggleSortDirection')
        ->assertViewHas('rows', fn ($rows) => (($this->column)('id'))($rows) === [$a->id, $c->id, $b->id])
        ->set('sortBy', 'car')
        ->call('toggleSortDirection')
        ->assertViewHas('rows', fn ($rows) => (($this->column)('id'))($rows) === [$a->id, $c->id, $b->id])
        ->set('sortBy', 'color')
        ->assertViewHas('rows', fn ($rows) => (($this->column)('id'))($rows) === [$c->id, $a->id, $b->id]);
});
