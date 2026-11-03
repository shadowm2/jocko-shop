<?php

use Livewire\Livewire;
use Modules\Car\Models\Car;
use Modules\Car\Models\UserCar;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\StockMovement;
use Modules\Inventory\Models\Unit;
use Modules\Inventory\Models\UnitGroup;
use Modules\Inventory\Models\Warehouse;
use Modules\Inventory\Models\WarehouseItem;
use Modules\Order\Enums\OrderStatus;
use Modules\Order\Livewire\OrderEdit;
use Modules\Order\Models\Order;
use Modules\Order\Services\OrderService;
use Modules\User\Enums\UserType;
use Modules\User\Models\User;

beforeEach(function () {
    deferForeignKeys();
    $this->actingAs(User::factory()->create());

    $group = UnitGroup::forceCreate(['name' => 'Count', 'slug' => 'count']);
    $this->piece = Unit::forceCreate(['name' => 'Piece', 'symbol' => 'pc', 'unit_group_id' => $group->id, 'conversion_factor' => 1, 'is_base' => true, 'slug' => 'piece']);
    $this->oil = Item::forceCreate(['name' => 'Oil', 'slug' => 'oil', 'unit_group_id' => $group->id, 'category_id' => 1]);
    $this->labour = Item::forceCreate(['name' => 'Labour', 'slug' => 'labour', 'unit_group_id' => $group->id, 'category_id' => 1, 'is_service' => true]);
    $this->alignment = Item::forceCreate(['name' => 'Wheel alignment', 'slug' => 'alignment', 'unit_group_id' => $group->id, 'category_id' => 1, 'is_service' => true]);
    $this->warehouse = Warehouse::forceCreate(['name' => 'Main', 'slug' => 'main']);
    $this->stock = WarehouseItem::forceCreate(['slug' => 'main-oil', 'warehouse_id' => $this->warehouse->id, 'item_id' => $this->oil->id, 'quantity' => 30]);
    $this->mechanic = User::factory()->create(['first_name' => 'Reza', 'last_name' => 'Mechanic']);
    $this->mechanic->forceFill(['type' => UserType::Admin])->save();

    $this->customer = User::factory()->create();
    $this->customer->forceFill(['type' => UserType::Customer])->save();
    $car = Car::forceCreate(['name' => 'Pride', 'slug' => 'pride', 'car_company_id' => 1]);
    $userCar = UserCar::forceCreate(['slug' => 'uc1', 'user_id' => $this->customer->id, 'car_id' => $car->id, 'color_id' => 1]);
    $this->order = Order::forceCreate([
        'slug' => 'o1', 'user_id' => $this->customer->id, 'user_car_id' => $userCar->id, 'warehouse_id' => $this->warehouse->id,
        'order_number' => 'ORD-00001', 'status' => OrderStatus::PENDING,
    ]);
    $this->service = fn () => resolve(OrderService::class);
    $lines = fn () => $this->order->items()->with('item')->get()->keyBy(fn ($line) => $line->item->slug);
    $this->lines = $lines;
});

it('adds service lines to an order without touching the warehouse', function () {
    ($this->service)()->update($this->order, ['services' => ['labour' => ['quantity' => 2, 'unit_price' => 300000, 'mechanic_id' => $this->mechanic->id]]]);

    $line = ($this->lines)()->get('labour');
    expect($line->item_id)->toBe($this->labour->id)
        ->and((float) $line->total)->toBe(600000.0)
        ->and($line->mechanic_id)->toBe($this->mechanic->id)
        ->and($line->unit_id)->toBe($this->piece->id, 'the service\'s own base unit')
        ->and(StockMovement::count())->toBe(0)
        ->and((float) $this->stock->fresh()->quantity)->toBe(30.0)
        ->and((float) $this->order->items()->sum('total'))->toBe(600000.0);
});

it('keeps parts and services apart: saving one kind never removes the other', function () {
    ($this->service)()->update($this->order, [
        'items' => ['main-oil' => ['quantity' => 4, 'unit_price' => 50000, 'unit_id' => $this->piece->id]],
        'services' => ['labour' => ['quantity' => 1, 'unit_price' => 200000]],
    ]);
    expect(($this->lines)()->keys()->sort()->values()->all())->toBe(['labour', 'oil']);

    // Only the parts are given: the service stays.
    ($this->service)()->update($this->order, ['items' => ['main-oil' => ['quantity' => 5, 'unit_price' => 50000, 'unit_id' => $this->piece->id]]]);
    expect(($this->lines)()->keys()->sort()->values()->all())->toBe(['labour', 'oil'])
        ->and((float) $this->stock->fresh()->quantity)->toBe(25.0);

    // Only the services are given: the part and its stock stay.
    ($this->service)()->update($this->order, ['services' => ['alignment' => ['quantity' => 1, 'unit_price' => 80000]]]);
    expect(($this->lines)()->keys()->sort()->values()->all())->toBe(['alignment', 'oil'])
        ->and((float) $this->stock->fresh()->quantity)->toBe(25.0);

    // Empty lists clear their own kind only.
    ($this->service)()->update($this->order, ['services' => []]);
    expect(($this->lines)()->keys()->all())->toBe(['oil']);
});

it('changes and brings back a service line instead of duplicating it', function () {
    ($this->service)()->update($this->order, ['services' => ['labour' => ['quantity' => 1, 'unit_price' => 100000]]]);
    ($this->service)()->update($this->order, ['services' => ['labour' => ['quantity' => 3, 'unit_price' => 120000]]]);
    ($this->service)()->update($this->order, ['services' => []]);
    ($this->service)()->update($this->order, ['services' => ['labour' => ['quantity' => 2, 'unit_price' => 90000]]]);

    expect($this->order->items()->withTrashed()->count())->toBe(1)
        ->and((float) ($this->lines)()->get('labour')->total)->toBe(180000.0);
});

it('refuses a service that does not exist or a part sent as a service', function () {
    expect(fn () => ($this->service)()->update($this->order, ['services' => ['nope' => ['quantity' => 1, 'unit_price' => 1]]]))
        ->toThrow(InvalidArgumentException::class, 'nope')
        ->and(fn () => ($this->service)()->update($this->order, ['services' => ['oil' => ['quantity' => 1, 'unit_price' => 1]]]))
        ->toThrow(InvalidArgumentException::class, 'oil');
});

it('keeps service lines when the order is cancelled, and there is no stock to give back', function () {
    ($this->service)()->update($this->order, ['services' => ['labour' => ['quantity' => 1, 'unit_price' => 100000]]]);
    ($this->service)()->update($this->order, ['status' => OrderStatus::CANCELLED]);

    expect(($this->lines)()->has('labour'))->toBeTrue()->and(StockMovement::count())->toBe(0);
});

it('prints service lines on the invoice like any other line', function () {
    ($this->service)()->update($this->order, ['services' => ['labour' => ['quantity' => 1, 'unit_price' => 100000]]]);
    $this->actingAs($this->customer)->get(route('orders.invoice', $this->order->fresh()))->assertOk()->assertSee('Labour');
});

it('edits the services on the order form: add, price, mechanic, remove', function () {
    $component = Livewire::test(OrderEdit::class, ['order' => $this->order])
        ->assertSee('data-services-editor', false)
        ->set('form.serviceToAdd', 'labour')
        ->call('addService')
        ->assertSet('form.services.labour', ['quantity' => 1, 'unit_price' => 0, 'mechanic_id' => null])
        ->assertSet('form.serviceToAdd', '')
        ->set('form.services.labour.quantity', '2')
        ->set('form.services.labour.unit_price', '150000')
        ->set('form.services.labour.mechanic_id', (string) $this->mechanic->id)
        ->assertSee('data-service-total="300000"', false);

    $component->call('save')->assertHasNoErrors();

    $line = ($this->lines)()->get('labour');
    expect((float) $line->total)->toBe(300000.0)->and($line->mechanic_id)->toBe($this->mechanic->id);

    Livewire::test(OrderEdit::class, ['order' => $this->order->fresh()])
        ->assertSet('form.services.labour.quantity', 2.0)
        ->call('removeService', 'labour')
        ->call('save');

    expect(($this->lines)()->has('labour'))->toBeFalse();
});

it('only offers active services, and only staff as mechanics', function () {
    $this->alignment->update(['is_active' => false]);
    $customer = User::factory()->create(['first_name' => 'Just', 'last_name' => 'Customer']);
    $customer->forceFill(['type' => UserType::Customer])->save();

    $form = Livewire::test(OrderEdit::class, ['order' => $this->order])->instance()->form;

    expect(array_keys($form->serviceOptions()))->toBe(['labour'])
        ->and($form->mechanicOptions())->toHaveKey($this->mechanic->id)->not->toHaveKey($customer->id);
});

it('never lets a service be put in a warehouse', function () {
    Livewire::test(Modules\Inventory\Livewire\WarehouseItems\WarehouseItemCreate::class, ['warehouse' => $this->warehouse])
        ->set('form.item', 'labour')
        ->set('form.quantity', '5')
        ->call('save')
        ->assertHasErrors(['form.item']);

    expect(WarehouseItem::where('item_id', $this->labour->id)->count())->toBe(0);
});
