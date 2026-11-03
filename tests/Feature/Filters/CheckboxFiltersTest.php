<?php

use App\Filters\BaseFilter;
use Illuminate\Database\Eloquent\Builder;
use Modules\Dashboard\Filters\BrandFilter;
use Modules\Dashboard\Filters\CategoryFilter;
use Modules\Dashboard\Filters\CityFilter;
use Modules\Dashboard\Filters\CountryFilter;
use Modules\Dashboard\Filters\ProvinceFilter;
use Modules\Dashboard\Models\Category;
use Modules\Dashboard\Models\City;
use Modules\Dashboard\Models\Country;
use Modules\Dashboard\Models\Province;
use Modules\Inventory\Filters\ItemFilter;
use Modules\Inventory\Filters\SupplierFilter;
use Modules\Inventory\Filters\UnitFilter;
use Modules\Inventory\Filters\WarehouseFilter;
use Modules\Inventory\Models\Brand;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\Supplier;
use Modules\Inventory\Models\Unit;
use Modules\Inventory\Models\Warehouse;

// Every list with a yes/no checkbox group shares BaseFilter::whereCheckboxes().
$filters = [
    'items' => [ItemFilter::class, Item::class],
    'suppliers' => [SupplierFilter::class, Supplier::class],
    'brands' => [BrandFilter::class, Brand::class],
    'categories' => [CategoryFilter::class, Category::class],
    'cities' => [CityFilter::class, City::class],
    'countries' => [CountryFilter::class, Country::class],
    'provinces' => [ProvinceFilter::class, Province::class],
    'units' => [UnitFilter::class, Unit::class],
    'warehouses' => [WarehouseFilter::class, Warehouse::class],
];

$sql = fn (string $filter, string $model, array $input): string => (new $filter($input))
    ->apply($model::query())
    ->toRawSql();

it('keeps only the ticked state of is_active', function (string $filter, string $model) use ($sql) {
    expect($sql($filter, $model, ['is_active' => ['1']]))->toMatch('/["`]is_active["`] = 1/')
        ->and($sql($filter, $model, ['is_active' => ['0']]))->toMatch('/["`]is_active["`] = 0/');
})->with($filters);

it('does not filter when none or both states are ticked', function (string $filter, string $model) use ($sql) {
    foreach ([[], ['is_active' => []], ['is_active' => ['0', '1']], ['is_active' => ['x']]] as $input) {
        expect($sql($filter, $model, $input))->not->toContain('is_active');
    }
})->with($filters);

it('still accepts a plain true from code that picks active rows', function (string $filter, string $model) use ($sql) {
    expect($sql($filter, $model, ['is_active' => true]))->toMatch('/["`]is_active["`] = 1/');
})->with($filters);

it('filters units by is_base the same way', function () use ($sql) {
    expect($sql(UnitFilter::class, Unit::class, ['is_base' => ['1']]))->toMatch('/["`]is_base["`] = 1/')
        ->and($sql(UnitFilter::class, Unit::class, ['is_base' => ['0', '1']]))->not->toContain('is_base');
});

it('ignores values that are not 0 or 1', function () {
    $filter = new class(['flag' => ['1', 'junk', '']]) extends BaseFilter
    {
        protected function filter(): void
        {
            $this->whereCheckboxes('flag');
        }
    };

    $query = $filter->apply(Warehouse::query());

    expect($query)->toBeInstanceOf(Builder::class)
        ->and($query->toRawSql())->toMatch('/["`]flag["`] = 1/');
});
