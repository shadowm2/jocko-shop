<?php

use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\Inventory\Enums\StockMovementType;
use Modules\Inventory\Livewire\StockMovements\StockTransferCreate;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\StockMovement;
use Modules\Inventory\Models\UnitGroup;
use Modules\Inventory\Models\Warehouse;
use Modules\Inventory\Models\WarehouseItem;
use Modules\Inventory\Services\StockMovementService;
use Modules\User\Models\User;

// Bare rows only (see OrderStockTest): foreign key checks are deferred.
beforeEach(function () {
    deferForeignKeys();

    $group = UnitGroup::forceCreate(['name' => 'Count', 'slug' => 'count']);
    $this->item = Item::forceCreate(['name' => 'Oil', 'slug' => 'oil', 'unit_group_id' => $group->id, 'category_id' => 1]);
    $this->main = Warehouse::forceCreate(['name' => 'Main', 'slug' => 'main']);
    $this->spare = Warehouse::forceCreate(['name' => 'Spare', 'slug' => 'spare']);
    $this->stock = WarehouseItem::forceCreate(['slug' => 'main-oil', 'warehouse_id' => $this->main->id, 'item_id' => $this->item->id, 'quantity' => 30]);

    $this->at = fn (Warehouse $warehouse) => WarehouseItem::where('warehouse_id', $warehouse->id)->where('item_id', $this->item->id)->first();
    $this->transfer = fn (float $quantity, ?Warehouse $to = null) => resolve(StockMovementService::class)->transfer($this->stock, $to ?? $this->spare, $quantity);
    $this->form = fn (string $warehouse, string $quantity) => Livewire::test(StockTransferCreate::class)
        ->set('form.warehouse_item', 'main-oil')
        ->set('form.warehouse', $warehouse)
        ->set('form.quantity', $quantity)
        ->call('save');
});

it('moves stock to a warehouse that never had the item, as one movement', function () {
    $movement = ($this->transfer)(12);

    expect((float) ($this->at)($this->main)->quantity)->toBe(18.0)
        ->and((float) ($this->at)($this->spare)->quantity)->toBe(12.0)
        ->and(StockMovement::count())->toBe(1)
        ->and($movement->type)->toBe(StockMovementType::TRANSFER)
        ->and($movement->direction())->toBe(0)
        ->and($movement->from_warehouse_id)->toBe($this->main->id)
        ->and($movement->to_warehouse_id)->toBe($this->spare->id)
        ->and((float) $movement->quantity)->toBe(12.0)
        ->and((float) $movement->balance_after)->toBe(18.0);
});

it('tops up the destination when the item is already stocked there', function () {
    WarehouseItem::forceCreate(['slug' => 'spare-oil', 'warehouse_id' => $this->spare->id, 'item_id' => $this->item->id, 'quantity' => 5]);

    ($this->transfer)(30);

    expect((float) ($this->at)($this->main)->quantity)->toBe(0.0)
        ->and((float) ($this->at)($this->spare)->quantity)->toBe(35.0)
        ->and(WarehouseItem::count())->toBe(2);
});

it('refuses more than is on hand and changes nothing', function () {
    expect(fn () => ($this->transfer)(31))->toThrow(ValidationException::class);

    expect((float) ($this->at)($this->main)->quantity)->toBe(30.0)
        ->and(($this->at)($this->spare))->toBeNull()
        ->and(StockMovement::count())->toBe(0);
});

it('refuses a transfer into the same warehouse', function () {
    expect(fn () => ($this->transfer)(1, $this->main))->toThrow(ValidationException::class);

    expect(StockMovement::count())->toBe(0);
});

it('transfers from the form and redirects to the history', function () {
    ($this->form)('spare', '7.5')
        ->assertHasNoErrors()
        ->assertRedirect(route('stock-movements.index'));

    expect((float) ($this->at)($this->main)->quantity)->toBe(22.5)
        ->and((float) ($this->at)($this->spare)->quantity)->toBe(7.5);
});

it('shows form errors instead of moving stock', function (string $warehouse, string $quantity, string $error) {
    ($this->form)($warehouse, $quantity)->assertHasErrors($error);

    expect((float) ($this->at)($this->main)->quantity)->toBe(30.0)->and(StockMovement::count())->toBe(0);
})->with([
    'more than on hand' => ['spare', '31', 'form.quantity'],
    'same warehouse' => ['main', '1', 'form.warehouse'],
    'unknown warehouse' => ['nowhere', '1', 'form.warehouse'],
    'zero' => ['spare', '0', 'form.quantity'],
    'no quantity' => ['spare', '', 'form.quantity'],
]);

it('shows the transfer page to a signed in user', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('stock-movements.transfer'))->assertOk()->assertSee('Oil');
    $this->get(route('stock-movements.index'))->assertOk();
});
