<?php

use Livewire\Livewire;
use Modules\Car\Models\Car;
use Modules\Car\Models\UserCar;
use Modules\Inventory\Models\Warehouse;
use Modules\Order\Enums\OrderStatus;
use Modules\Order\Livewire\OrdersList;
use Modules\Order\Models\Order;
use Modules\Order\Models\OrderItem;
use Modules\User\Models\User;

beforeEach(function () {
    deferForeignKeys();
    $this->actingAs(User::factory()->create());

    $customer = User::factory()->create();
    $customer->forceFill(['slug' => 'cust1'])->save();
    $car = Car::forceCreate(['slug' => 'pride', 'name' => 'Pride', 'car_company_id' => 1]);
    $userCar = UserCar::forceCreate(['slug' => 'uc1', 'user_id' => $customer->id, 'car_id' => $car->id, 'color_id' => 1]);
    $this->main = Warehouse::forceCreate(['name' => 'Main', 'slug' => 'main']);
    $order = Order::forceCreate(['slug' => 'o1', 'user_id' => $customer->id, 'user_car_id' => $userCar->id, 'warehouse_id' => $this->main->id, 'order_number' => 'ORD-00001', 'status' => OrderStatus::PENDING]);
    OrderItem::forceCreate(['slug' => 'oi1', 'order_id' => $order->id, 'item_id' => 1, 'unit_id' => 1, 'quantity' => 1, 'unit_price' => 5000, 'total' => 5000]);
});

/** Count the header cells and the cells of the first row of the rendered list. */
function ordersListShape(string $html): array
{
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="utf-8"?>'.$html);
    $xpath = new DOMXPath($document);

    return [
        'headers' => $xpath->query('//table//thead//th | //table//*[@data-flux-column]')->length,
        'cells' => $xpath->query('(//table//tbody//tr)[1]/*[self::td or self::th or @data-flux-cell]')->length,
    ];
}

it('lines every cell of a row up under a header, with one warehouse and with several', function () {
    $single = Livewire::test(OrdersList::class)->html();
    $shape = ordersListShape($single);
    expect($shape['headers'])->toBeGreaterThan(0)->and($shape['cells'])->toBe($shape['headers']);

    Warehouse::forceCreate(['name' => 'Spare', 'slug' => 'spare']);
    $several = Livewire::test(OrdersList::class)->html();
    $shape = ordersListShape($several);
    expect($shape['cells'])->toBe($shape['headers'])->and($several)->toContain('Main');
});

it('leaves the warehouse out while only one is used', function () {
    $html = Livewire::test(OrdersList::class)->html();

    expect($html)->not->toContain('Main');
});

it('never prints template directives as text', function () {
    $html = Livewire::test(OrdersList::class)->html();

    expect($html)->not->toMatch('/@(end)?(if|foreach|multiwarehouse|php|forelse)\b/');
});

it('uses no custom Blade directive in any template, because component optimisation (Blaze) compiles templates without them', function () {
    $used = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path('Modules'))) as $file) {
        if (str_ends_with((string) $file, '.blade.php') && str_contains((string) file_get_contents((string) $file), '@multiwarehouse')) {
            $used[] = str_replace(base_path().'/', '', (string) $file);
        }
    }

    expect($used)->toBe([]);
});
