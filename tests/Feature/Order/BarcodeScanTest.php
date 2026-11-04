<?php

use Livewire\Livewire;
use Modules\Car\Models\Car;
use Modules\Car\Models\UserCar;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\ItemBrand;
use Modules\Inventory\Models\Unit;
use Modules\Inventory\Models\UnitGroup;
use Modules\Inventory\Models\Warehouse;
use Modules\Inventory\Models\WarehouseItem;
use Modules\Inventory\Services\BarcodeService;
use Modules\Order\Enums\OrderStatus;
use Modules\Order\Livewire\OrderEdit;
use Modules\Order\Models\Order;
use Modules\User\Enums\UserType;
use Modules\User\Models\User;

beforeEach(function () {
    deferForeignKeys();
    $this->actingAs(User::factory()->create());

    $group = UnitGroup::forceCreate(['name' => 'Count', 'slug' => 'count']);
    $this->piece = Unit::forceCreate(['name' => 'Piece', 'symbol' => 'pc', 'unit_group_id' => $group->id, 'conversion_factor' => 1, 'is_base' => true, 'slug' => 'piece']);
    $this->pad = Item::forceCreate(['name' => 'Brake pad', 'slug' => 'pad', 'unit_group_id' => $group->id, 'category_id' => 1]);
    $this->filter = Item::forceCreate(['name' => 'Oil filter', 'slug' => 'filter', 'unit_group_id' => $group->id, 'category_id' => 1]);
    $this->main = Warehouse::forceCreate(['name' => 'Main', 'slug' => 'main']);
    $this->other = Warehouse::forceCreate(['name' => 'Other', 'slug' => 'other']);
    $this->padStock = WarehouseItem::forceCreate(['slug' => 'main-pad', 'warehouse_id' => $this->main->id, 'item_id' => $this->pad->id, 'quantity' => 30]);
    WarehouseItem::forceCreate(['slug' => 'other-filter', 'warehouse_id' => $this->other->id, 'item_id' => $this->filter->id, 'quantity' => 30]);

    $brand = fn (Item $item, string $slug, array $codes, bool $active = true, ?float $price = null, int $brandId = 1) => ItemBrand::forceCreate([
        'slug' => $slug, 'item_id' => $item->id, 'brand_id' => $brandId, 'is_active' => $active, 'sale_price' => $price,
    ] + $codes);
    $brand($this->pad, 'pad-bosch', ['barcode' => '6260001112223', 'sku' => 'BP-100', 'part_number' => 'BOSCH-0986'], price: 450000);
    $brand($this->filter, 'filter-mann', ['barcode' => '6260009998887']);
    $brand($this->filter, 'filter-old', ['barcode' => '1111111111111'], active: false, brandId: 2);

    $customer = User::factory()->create();
    $customer->forceFill(['type' => UserType::Customer, 'slug' => 'cust'])->save();
    $car = Car::forceCreate(['name' => 'Pride', 'slug' => 'pride', 'car_company_id' => 1]);
    $userCar = UserCar::forceCreate(['slug' => 'uc1', 'user_id' => $customer->id, 'car_id' => $car->id, 'color_id' => 1]);
    $this->order = Order::forceCreate(['slug' => 'o1', 'user_id' => $customer->id, 'user_car_id' => $userCar->id, 'warehouse_id' => $this->main->id, 'order_number' => 'ORD-00001', 'status' => OrderStatus::PENDING]);

    $this->scan = fn (string $code) => Livewire::test(OrderEdit::class, ['order' => $this->order])->set('barcode', $code)->call('scanBarcode');
});

it('finds the stock a barcode, SKU or part number stands for', function (string $code) {
    $result = resolve(BarcodeService::class)->lookup($code, 'main');

    expect($result['status'])->toBe('found')
        ->and($result['warehouse_item']->slug)->toBe('main-pad')
        ->and($result['sale_price'])->toBe(450000.0);
})->with(['barcode' => '6260001112223', 'sku' => 'BP-100', 'part number' => 'BOSCH-0986', 'spaces around it' => ' 6260001112223 ', 'persian digits' => '۶۲۶۰۰۰۱۱۱۲۲۲۳']);

it('says when a code is unknown, inactive, or not stocked in this warehouse', function (string $code, string $status) {
    expect(resolve(BarcodeService::class)->lookup($code, 'main')['status'])->toBe($status);
})->with([
    'nobody has it' => ['0000', 'unknown'],
    'empty' => ['', 'unknown'],
    'an inactive brand' => ['1111111111111', 'unknown'],
    'stocked elsewhere only' => ['6260009998887', 'not_stocked'],
]);

it('puts a scanned item on the order at the brand\'s sale price, and one more on every repeat scan', function () {
    $page = ($this->scan)('6260001112223')
        ->assertSet('barcode', '')
        ->assertSet('form.selected', ['main-pad'])
        ->assertSet('form.items.main-pad.quantity', 1)
        ->assertSet('form.items.main-pad.unit_price', 450000.0);

    $page->set('barcode', 'BP-100')->call('scanBarcode')->assertSet('form.items.main-pad.quantity', 2.0);
    $page->set('barcode', 'BP-100')->call('scanBarcode')->assertSet('form.selected', ['main-pad'])->assertSet('form.items.main-pad.quantity', 3.0);
});

it('leaves the order alone for a code it cannot use', function (string $code) {
    ($this->scan)($code)->assertSet('form.selected', [])->assertSet('barcode', '');
})->with(['unknown' => '0000', 'other warehouse' => '6260009998887', 'blank' => '   ']);

it('keeps a scanned line when the order is saved', function () {
    ($this->scan)('6260001112223')
        ->set('form.items.main-pad.quantity', '3')
        ->call('save')
        ->assertHasNoErrors();

    $line = $this->order->items()->sole();
    expect($line->item_id)->toBe($this->pad->id)
        ->and((float) $line->quantity)->toBe(3.0)
        ->and((float) $line->total)->toBe(1350000.0)
        ->and((float) $this->padStock->fresh()->quantity)->toBe(27.0);
});

it('offers the scan box on the order pages', function () {
    $this->get(route('orders.edit', $this->order))->assertOk()->assertSee('data-scan-barcode', false);
    $this->get(route('orders.create'))->assertOk()->assertSee('data-scan-barcode', false);
});
