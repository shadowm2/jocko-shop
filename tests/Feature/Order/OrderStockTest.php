<?php

use Illuminate\Validation\ValidationException;
use Modules\Inventory\Enums\StockMovementType;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\StockMovement;
use Modules\Inventory\Models\Unit;
use Modules\Inventory\Models\UnitGroup;
use Modules\Inventory\Models\Warehouse;
use Modules\Inventory\Models\WarehouseItem;
use Modules\Order\Enums\OrderStatus;
use Modules\Order\Models\Order;
use Modules\Order\Services\OrderService;

// Bare rows only: users, cars and categories are irrelevant to stock, so
// foreign key checks are deferred (never reached, the test transaction rolls
// back) instead of building that whole graph.
beforeEach(function () {
    deferForeignKeys();

    $group = UnitGroup::forceCreate(['name' => 'Count', 'slug' => 'count']);
    $this->piece = Unit::forceCreate(['name' => 'Piece', 'symbol' => 'pc', 'unit_group_id' => $group->id, 'conversion_factor' => 1, 'is_base' => true, 'slug' => 'piece']);
    $this->dozen = Unit::forceCreate(['name' => 'Dozen', 'symbol' => 'dz', 'unit_group_id' => $group->id, 'conversion_factor' => 12, 'slug' => 'dozen']);
    $item = Item::forceCreate(['name' => 'Oil', 'slug' => 'oil', 'unit_group_id' => $group->id, 'category_id' => 1]);
    $warehouse = Warehouse::forceCreate(['name' => 'Main', 'slug' => 'main']);
    $this->stock = WarehouseItem::forceCreate(['slug' => 'main-oil', 'warehouse_id' => $warehouse->id, 'item_id' => $item->id, 'quantity' => 30]);
    $this->order = Order::forceCreate([
        'slug' => 'o1', 'user_id' => 1, 'user_car_id' => 1, 'warehouse_id' => $warehouse->id,
        'order_number' => 'ORD-00001', 'status' => OrderStatus::PENDING,
    ]);

    $this->setLines = fn (float $quantity, ?int $unitId = null, array $extra = []) => resolve(OrderService::class)->update($this->order, [
        'items' => ['main-oil' => ['quantity' => $quantity, 'unit_price' => 5, 'unit_id' => $unitId ?? $this->piece->id]],
        ...$extra,
    ]);
    $this->onHand = fn () => (float) $this->stock->fresh()->quantity;
});

it('takes the ordered quantity out of the warehouse, in the base unit', function () {
    ($this->setLines)(2, $this->dozen->id);

    expect(($this->onHand)())->toBe(6.0);

    $movement = StockMovement::sole();
    expect($movement->type)->toBe(StockMovementType::SALE)
        ->and((float) $movement->quantity)->toBe(24.0)
        ->and((float) $movement->balance_after)->toBe(6.0);
});

it('applies only the difference when the quantity changes', function () {
    ($this->setLines)(10);
    ($this->setLines)(4);

    expect(($this->onHand)())->toBe(26.0)
        ->and(StockMovement::count())->toBe(1);
});

it('gives the stock back when a line is removed or the order is cancelled', function () {
    ($this->setLines)(10);
    resolve(OrderService::class)->update($this->order, ['items' => []]);
    expect(($this->onHand)())->toBe(30.0)->and(StockMovement::count())->toBe(0);

    ($this->setLines)(10);
    resolve(OrderService::class)->update($this->order, ['status' => OrderStatus::CANCELLED->value]);
    expect(($this->onHand)())->toBe(30.0)->and(StockMovement::count())->toBe(0);

    // Re-opening the order takes it out again.
    resolve(OrderService::class)->update($this->order, ['status' => OrderStatus::PENDING->value]);
    expect(($this->onHand)())->toBe(20.0);
});

it('rejects a line larger than the stock on hand and changes nothing', function () {
    ($this->setLines)(10);

    expect(fn () => ($this->setLines)(31))->toThrow(ValidationException::class);

    expect(($this->onHand)())->toBe(20.0)
        ->and((float) StockMovement::sole()->quantity)->toBe(10.0)
        ->and((float) $this->order->items()->sole()->quantity)->toBe(10.0);
});

it('lets an edit use the stock its own order already holds', function () {
    ($this->setLines)(25);

    ($this->setLines)(30);

    expect(($this->onHand)())->toBe(0.0);
});
