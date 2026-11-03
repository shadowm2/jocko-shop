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
use Modules\Inventory\Models\UnitGroup;
use Modules\Inventory\Models\Warehouse;
use Modules\User\Livewire\UserCarsList;
use Modules\User\Models\User;

// Bare rows only (see OrderStockTest): foreign key checks are deferred.
beforeEach(function () {
    deferForeignKeys();
    $this->actingAs($this->user = User::factory()->create());
});

it('searches unit groups by name', function () {
    UnitGroup::forceCreate(['name' => 'Volume', 'slug' => 'volume']);
    UnitGroup::forceCreate(['name' => 'Weight', 'slug' => 'weight']);

    Livewire::test(UnitGroupList::class)
        ->assertViewHas('rows', fn ($rows) => $rows->count() === 2)
        ->set('filters.search', 'vol')
        ->assertViewHas('rows', fn ($rows) => $rows->pluck('name')->all() === ['Volume']);
});

it('searches warehouses and filters them by active state without losing the filter on toggle', function () {
    Warehouse::forceCreate(['name' => 'Main', 'slug' => 'main', 'description' => 'Ground floor', 'is_active' => true]);
    Warehouse::forceCreate(['name' => 'Spare', 'slug' => 'spare', 'description' => 'Basement', 'is_active' => false]);

    Livewire::test(WarehouseList::class)
        ->set('filters.search', 'basement')
        ->assertViewHas('rows', fn ($rows) => $rows->pluck('name')->all() === ['Spare'])
        ->call('clearFilters')
        ->set('filters.is_active', ['1'])
        ->assertViewHas('rows', fn ($rows) => $rows->pluck('name')->all() === ['Main'])
        ->set('filters.is_active', ['0', '1'])
        ->assertViewHas('rows', fn ($rows) => $rows->count() === 2)
        ->set('filters.is_active', ['0'])
        ->call('toggleIsActive', 'spare')
        ->assertSet('filters.is_active', ['0']);
});

it('searches an items brands by brand, sku, barcode or part number, only for that item', function () {
    $group = UnitGroup::forceCreate(['name' => 'Count', 'slug' => 'count']);
    $item = Item::forceCreate(['name' => 'Oil', 'slug' => 'oil', 'unit_group_id' => $group->id, 'category_id' => 1]);
    $other = Item::forceCreate(['name' => 'Filter', 'slug' => 'filter', 'unit_group_id' => $group->id, 'category_id' => 1]);
    $shell = Brand::forceCreate(['name' => 'Shell', 'slug' => 'shell']);
    $bp = Brand::forceCreate(['name' => 'BP', 'slug' => 'bp']);

    ItemBrand::forceCreate(['slug' => 'a', 'item_id' => $item->id, 'brand_id' => $shell->id, 'sku' => 'SKU-1', 'is_active' => true]);
    ItemBrand::forceCreate(['slug' => 'b', 'item_id' => $item->id, 'brand_id' => $bp->id, 'barcode' => '6260001', 'is_active' => false]);
    ItemBrand::forceCreate(['slug' => 'c', 'item_id' => $other->id, 'brand_id' => $shell->id, 'sku' => 'SKU-1', 'is_active' => true]);

    $slugs = fn ($rows) => $rows->pluck('slug')->sort()->values()->all();

    Livewire::test(ItemBrandList::class, ['item' => $item])
        ->assertViewHas('rows', fn ($rows) => $slugs($rows) === ['a', 'b'])
        ->set('filters.search', 'shell')
        ->assertViewHas('rows', fn ($rows) => $slugs($rows) === ['a'])
        ->set('filters.search', '6260')
        ->assertViewHas('rows', fn ($rows) => $slugs($rows) === ['b'])
        ->call('clearFilters')
        ->set('filters.is_active', ['0'])
        ->assertViewHas('rows', fn ($rows) => $slugs($rows) === ['b']);
});

it('searches a users cars by car, company or color', function () {
    $company = CarCompany::forceCreate(['name' => 'Peugeot', 'slug' => 'peugeot']);
    $car = Car::forceCreate(['name' => '206', 'slug' => '206', 'car_company_id' => $company->id]);
    $red = Color::forceCreate(['name' => 'Red', 'slug' => 'red', 'hex' => '#f00']);
    $blue = Color::forceCreate(['name' => 'Blue', 'slug' => 'blue', 'hex' => '#00f']);

    $mine = UserCar::forceCreate(['slug' => 'mine', 'user_id' => $this->user->id, 'car_id' => $car->id, 'color_id' => $red->id]);
    $blueOne = UserCar::forceCreate(['slug' => 'blue', 'user_id' => $this->user->id, 'car_id' => $car->id, 'color_id' => $blue->id]);
    UserCar::forceCreate(['slug' => 'theirs', 'user_id' => User::factory()->create()->id, 'car_id' => $car->id, 'color_id' => $red->id]);

    $ids = fn ($rows) => $rows->pluck('id')->sort()->values()->all();

    Livewire::test(UserCarsList::class, ['user' => $this->user])
        ->assertViewHas('rows', fn ($rows) => $ids($rows) === [$mine->id, $blueOne->id])
        ->set('filters.search', 'peugeot')
        ->assertViewHas('rows', fn ($rows) => $ids($rows) === [$mine->id, $blueOne->id])
        ->set('filters.search', 'blue')
        ->assertViewHas('rows', fn ($rows) => $ids($rows) === [$blueOne->id])
        ->set('filters.search', 'nope')
        ->assertViewHas('rows', fn ($rows) => $rows->isEmpty());
});

it('renders the warehouse items page with its search filter', function () {
    Warehouse::forceCreate(['name' => 'Main', 'slug' => 'main']);

    Livewire::test(WarehouseItemList::class)
        ->set('filters.search', 'oil')
        ->assertOk()
        ->assertSee(__('inventory::strings.Search Warehouse Items'));
});
