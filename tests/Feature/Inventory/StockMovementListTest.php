<?php

use Livewire\Livewire;
use Modules\Inventory\Enums\StockMovementType;
use Modules\Inventory\Livewire\StockMovements\StockMovementList;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\StockMovement;
use Modules\Inventory\Models\UnitGroup;
use Modules\Inventory\Models\Warehouse;
use Modules\Inventory\Services\StockMovementService;
use Modules\User\Models\User;

// Bare rows only (see OrderStockTest): foreign key checks are deferred.
beforeEach(function () {
    deferForeignKeys();

    $group = UnitGroup::forceCreate(['name' => 'Count', 'slug' => 'count']);
    $oil = Item::forceCreate(['name' => 'Oil', 'slug' => 'oil', 'unit_group_id' => $group->id, 'category_id' => 1]);
    $filter = Item::forceCreate(['name' => 'Filter', 'slug' => 'filter', 'unit_group_id' => $group->id, 'category_id' => 1]);
    $this->main = Warehouse::forceCreate(['name' => 'Main', 'slug' => 'main']);
    $this->spare = Warehouse::forceCreate(['name' => 'Spare', 'slug' => 'spare']);

    $move = fn (Item $item, StockMovementType $type, ?Warehouse $from, ?Warehouse $to, float $quantity) => StockMovement::forceCreate([
        'slug' => 'sm'.StockMovement::count(), 'item_id' => $item->id, 'warehouse_item_id' => 1, 'type' => $type,
        'from_warehouse_id' => $from?->id, 'to_warehouse_id' => $to?->id, 'quantity' => $quantity, 'balance_after' => $quantity,
    ]);
    $this->in = $move($oil, StockMovementType::PURCHASE, null, $this->main, 10);
    $this->out = $move($oil, StockMovementType::SALE, $this->main, null, 4);
    $this->transfer = $move($filter, StockMovementType::TRANSFER, $this->main, $this->spare, 2);

    $this->list = fn (array $filters) => resolve(StockMovementService::class)->getStockMovements($filters, paginate: false);
});

it('knows which way stock moved', function () {
    expect($this->in->direction())->toBe(1)
        ->and($this->out->direction())->toBe(-1)
        ->and($this->transfer->direction())->toBe(0);
});

it('filters movements by item name and type, ignoring unknown types', function () {
    expect(($this->list)([]))->toHaveCount(3)
        ->and(($this->list)(['search' => 'Oil']))->toHaveCount(2)
        ->and(($this->list)(['type' => 'sale']))->toHaveCount(1)
        ->and(($this->list)(['type' => 'nope']))->toHaveCount(3);
});

it('matches a warehouse on either side of the movement', function () {
    expect(($this->list)(['warehouse_id' => $this->main->id]))->toHaveCount(3)
        ->and(($this->list)(['warehouse_id' => $this->spare->id])->pluck('id')->all())->toBe([$this->transfer->id]);
});

it('shows the movements page to a signed in user', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('stock-movements.index'))->assertOk()->assertSee('Oil');

    Livewire::test(StockMovementList::class)
        ->set('filters.type', 'sale')
        ->assertViewHas('rows', fn ($rows) => $rows->count() === 1);
});
