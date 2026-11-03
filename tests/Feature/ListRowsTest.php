<?php

use Livewire\Livewire;
use Modules\Car\Livewire\CarList;
use Modules\Car\Livewire\ColorList;
use Modules\Car\Livewire\CompanyList;
use Modules\Car\Models\Car;
use Modules\Car\Models\CarCompany;
use Modules\Car\Models\Color;
use Modules\Car\Models\UserCar;
use Modules\Dashboard\Enums\CategoryType;
use Modules\Dashboard\Livewire\CategoryList;
use Modules\Dashboard\Livewire\CityList;
use Modules\Dashboard\Livewire\CountryList;
use Modules\Dashboard\Livewire\ProvinceList;
use Modules\Dashboard\Models\Category;
use Modules\Dashboard\Models\City;
use Modules\Dashboard\Models\Country;
use Modules\Dashboard\Models\Province;
use Modules\Inventory\Livewire\Suppliers\SupplierList;
use Modules\Inventory\Livewire\UnitGroups\UnitGroupList;
use Modules\Inventory\Livewire\Units\UnitList;
use Modules\Inventory\Livewire\Warehouses\WarehouseList;
use Modules\Inventory\Models\Supplier;
use Modules\Inventory\Models\Unit;
use Modules\Inventory\Models\UnitGroup;
use Modules\Inventory\Models\Warehouse;
use Modules\User\Livewire\UserCarsList;
use Modules\User\Models\User;

// Bare rows only (see OrderStockTest): foreign key checks are deferred.
beforeEach(function () {
    deferForeignKeys();
    $this->actingAs($this->user = User::factory()->create(['slug' => 'ali']));
});

// Every list renders its rows through a row component; this fills one row per list and checks its cells.
it('renders the cells of each list row', function () {
    $company = CarCompany::forceCreate(['name' => 'Saipa', 'slug' => 'saipa']);
    $car = Car::forceCreate(['name' => 'Pride', 'slug' => 'pride', 'car_company_id' => $company->id]);
    $color = Color::forceCreate(['name' => 'Crimson', 'slug' => 'crimson', 'hex' => '#dc143c']);
    UserCar::forceCreate(['slug' => 'mine', 'user_id' => $this->user->id, 'car_id' => $car->id, 'color_id' => $color->id]);

    $country = Country::forceCreate(['name' => 'Iran', 'code' => 'IR', 'code3' => 'IRN']);
    $province = Province::forceCreate(['name' => 'Alborz', 'slug' => 'alborz', 'country_id' => $country->id]);
    $city = City::forceCreate(['name' => 'Karaj', 'slug' => 'karaj', 'province_id' => $province->id]);
    $engine = Category::forceCreate(['name' => 'Engine', 'slug' => 'engine', 'type' => CategoryType::Item, 'depth' => 0, 'icon' => 'cog']);
    Category::forceCreate(['name' => 'Pistons', 'slug' => 'pistons', 'type' => CategoryType::Item, 'depth' => 1, 'parent_id' => $engine->id, 'icon' => 'wrench']);

    Supplier::forceCreate([
        'slug' => 'acme', 'user_id' => $this->user->id, 'city_id' => $city->id, 'website' => 'https://acme.example',
        'address' => 'Unit 12, 34 Some Very Long Street Name, Tehran, Iran 1234567',
    ]);
    $group = UnitGroup::forceCreate(['name' => 'Volume', 'slug' => 'volume']);
    Unit::forceCreate(['name' => 'Liter', 'slug' => 'liter', 'symbol' => 'L', 'unit_group_id' => $group->id, 'conversion_factor' => 1.5, 'is_base' => true]);
    Warehouse::forceCreate(['name' => 'Main', 'slug' => 'main', 'description' => 'Ground floor of the old building']);

    $lists = [
        [CarList::class, [], ['Pride', 'Saipa', route('cars.edit', ['car' => 'pride'])]],
        [ColorList::class, [], ['Crimson', '#DC143C', route('colors.edit', ['color' => 'crimson'])]],
        [CompanyList::class, [], ['Saipa', route('companies.edit', ['company' => 'saipa'])]],
        [CategoryList::class, [], ['Pistons', 'Engine', "toggleIsActive('pistons')", route('categories.edit', ['category' => 'pistons'])]],
        [CityList::class, [], ['Karaj', 'Alborz', "toggleIsActive('karaj')", route('cities.edit', ['city' => 'karaj'])]],
        [CountryList::class, [], ['IRN', "toggleIsActive('IR')", route('countries.edit', ['country' => 'IR'])]],
        [ProvinceList::class, [], ['Alborz', "toggleIsActive('alborz')", route('provinces.edit', ['province' => 'alborz'])]],
        // The address is cut at 45 characters.
        [SupplierList::class, [], ['acme.example', 'Some Very Long Street Name, Tehra...', "toggleIsActive('acme')", route('suppliers.edit', ['supplier' => 'acme'])]],
        [UnitGroupList::class, [], ['Volume', route('units.groups.edit', ['unitGroup' => 'volume'])]],
        [UnitList::class, [], ['Liter', '۱.۵', "toggleIsActive('liter')", "toggleIsBase('liter')", route('units.edit', ['unit' => 'liter'])]],
        // The description is cut at 15 characters.
        [WarehouseList::class, [], ['Main', 'Ground floor of...', "changeActiveWarehouse('main')", "toggleIsActive('main')", route('warehouses.edit', ['warehouse' => 'main'])]],
        [UserCarsList::class, ['user' => $this->user], ['Saipa', 'Pride', 'Crimson']],
    ];

    foreach ($lists as [$list, $params, $see]) {
        $page = Livewire::test($list, $params)->assertOk();

        foreach ($see as $text) {
            $page->assertSee($text, false);
        }
    }

    Livewire::test(SupplierList::class)->assertDontSee('Iran 1234567');
    Livewire::test(WarehouseList::class)->assertDontSee('old building');
});
