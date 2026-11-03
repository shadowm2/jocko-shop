<?php

use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\Inventory\Enums\StockMovementType;
use Modules\Inventory\Livewire\StockMovements\StockAdjustmentCreate;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\StockMovement;
use Modules\Inventory\Models\UnitGroup;
use Modules\Inventory\Models\Warehouse;
use Modules\Inventory\Models\WarehouseItem;
use Modules\Inventory\Services\StockMovementService;
use Modules\Inventory\Services\WarehouseItemService;
use Modules\User\Models\User;

// Bare rows only (see OrderStockTest): foreign key checks are deferred.
beforeEach(function () {
    deferForeignKeys();

    $group = UnitGroup::forceCreate(['name' => 'Count', 'slug' => 'count']);
    $this->item = Item::forceCreate(['name' => 'Oil', 'slug' => 'oil', 'unit_group_id' => $group->id, 'category_id' => 1]);
    $this->warehouse = Warehouse::forceCreate(['name' => 'Main', 'slug' => 'main']);
    $this->stock = WarehouseItem::forceCreate(['slug' => 'main-oil', 'warehouse_id' => $this->warehouse->id, 'item_id' => $this->item->id, 'quantity' => 30]);

    $this->onHand = fn () => (float) $this->stock->fresh()->quantity;
    $this->adjust = fn (float $delta, StockMovementType $type = StockMovementType::ADJUSTMENT) => resolve(StockMovementService::class)->adjust($this->stock, $delta, $type);
    $this->form = fn (array $state) => Livewire::test(StockAdjustmentCreate::class)
        ->set('form.warehouse_item', 'main-oil')
        ->set('form.type', $state['type'])
        ->set('form.quantity', $state['quantity'])
        ->call('save');
});

it('adds and removes stock and records which way it went', function () {
    $in = ($this->adjust)(5, StockMovementType::RETURN);
    $out = ($this->adjust)(-12, StockMovementType::DAMAGE);

    expect(($this->onHand)())->toBe(23.0)
        ->and($in->direction())->toBe(1)
        ->and((float) $in->balance_after)->toBe(35.0)
        ->and($out->direction())->toBe(-1)
        ->and((float) $out->quantity)->toBe(12.0)
        ->and((float) $out->balance_after)->toBe(23.0)
        ->and($out->type)->toBe(StockMovementType::DAMAGE);
});

it('records nothing for a zero change', function () {
    expect(($this->adjust)(0))->toBeNull()
        ->and(StockMovement::count())->toBe(0);
});

it('refuses to take out more than is on hand and changes nothing', function () {
    expect(fn () => ($this->adjust)(-31))->toThrow(ValidationException::class);

    expect(($this->onHand)())->toBe(30.0)->and(StockMovement::count())->toBe(0);
});

it('sets the stock to the counted quantity on a recount', function () {
    ($this->form)(['type' => 'adjustment', 'quantity' => '22.5'])
        ->assertHasNoErrors()
        ->assertRedirect(route('stock-movements.index'));

    $movement = StockMovement::sole();
    expect(($this->onHand)())->toBe(22.5)
        ->and($movement->direction())->toBe(-1)
        ->and((float) $movement->quantity)->toBe(7.5);

    // A recount may end at zero.
    ($this->form)(['type' => 'adjustment', 'quantity' => '0'])->assertHasNoErrors();
    expect(($this->onHand)())->toBe(0.0);
});

it('takes damaged and consumed stock out and puts returns and receipts back', function (string $type, string $quantity, float $expected) {
    ($this->form)(['type' => $type, 'quantity' => $quantity])->assertHasNoErrors();

    expect(($this->onHand)())->toBe($expected)
        ->and(StockMovement::sole()->type->value)->toBe($type);
})->with([
    'damage' => ['damage', '4', 26.0],
    'consumption' => ['consumption', '10', 20.0],
    'return' => ['return', '3', 33.0],
    'receipt' => ['receipt', '5', 35.0],
]);

it('shows form errors instead of changing stock', function (string $type, string $quantity, string $error) {
    ($this->form)(['type' => $type, 'quantity' => $quantity])->assertHasErrors($error);

    expect(($this->onHand)())->toBe(30.0)->and(StockMovement::count())->toBe(0);
})->with([
    'more than on hand' => ['damage', '31', 'form.quantity'],
    'recount equals stock' => ['adjustment', '30', 'form.quantity'],
    'zero damage' => ['damage', '0', 'form.quantity'],
    'zero receipt' => ['receipt', '0', 'form.quantity'],
    'no quantity' => ['adjustment', '', 'form.quantity'],
    'negative count' => ['adjustment', '-1', 'form.quantity'],
    'unknown reason' => ['sale', '1', 'form.type'],
]);

it('records the warehouse item form quantities as movements', function () {
    // Repositories keep their filters on the instance, so resolve a fresh service per call like a request does.
    $service = fn () => resolve(WarehouseItemService::class);
    $group = UnitGroup::first();
    $other = Item::forceCreate(['name' => 'Filter', 'slug' => 'filter', 'unit_group_id' => $group->id, 'category_id' => 1]);

    // New to the warehouse: initial stock.
    $created = $service()->create(['item' => 'filter', 'warehouse' => 'main', 'quantity' => 8]);
    expect((float) $created->quantity)->toBe(8.0)
        ->and(StockMovement::where('item_id', $other->id)->sole()->type)->toBe(StockMovementType::INITIAL_STOCK);

    // Adding an item that is already there tops up the stock.
    $service()->create(['item' => 'oil', 'warehouse' => 'main', 'quantity' => 4]);
    expect(($this->onHand)())->toBe(34.0)
        ->and(StockMovement::where('item_id', $this->item->id)->sole()->type)->toBe(StockMovementType::ADJUSTMENT);

    // Editing sets the stock to the entered value; an unchanged value records nothing.
    $service()->update($created->id, ['quantity' => 5]);
    $service()->update($created->id, ['quantity' => 5]);

    expect((float) $created->fresh()->quantity)->toBe(5.0)
        ->and(StockMovement::where('item_id', $other->id)->count())->toBe(2)
        ->and(StockMovement::where('item_id', $other->id)->latest('id')->first()->direction())->toBe(-1);
});

it('shows the adjustment page to a signed in user', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('stock-movements.create'))->assertOk()->assertSee('Oil');
});
