<?php

use Livewire\Livewire;
use Modules\Inventory\Enums\PurchaseStatus;
use Modules\Inventory\Livewire\Purchases\PurchaseCreate;
use Modules\Inventory\Livewire\Purchases\ReorderList;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\Purchase;
use Modules\Inventory\Models\PurchaseItem;
use Modules\Inventory\Models\Supplier;
use Modules\Inventory\Models\Unit;
use Modules\Inventory\Models\UnitGroup;
use Modules\Inventory\Models\Warehouse;
use Modules\Inventory\Models\WarehouseItem;
use Modules\Inventory\Services\ReorderSuggestions;
use Modules\User\Models\User;

beforeEach(function () {
    deferForeignKeys();
    $this->actingAs(User::factory()->create());

    $this->group = UnitGroup::forceCreate(['name' => 'Count', 'slug' => 'count']);
    $this->piece = Unit::forceCreate(['name' => 'Piece', 'slug' => 'piece', 'symbol' => 'pc', 'unit_group_id' => $this->group->id, 'conversion_factor' => 1, 'is_base' => true]);
    $this->carton = Unit::forceCreate(['name' => 'Carton', 'slug' => 'carton', 'symbol' => 'ct', 'unit_group_id' => $this->group->id, 'conversion_factor' => 12, 'is_base' => false]);
    $this->main = Warehouse::forceCreate(['name' => 'Main', 'slug' => 'main']);
    $this->ali = Supplier::forceCreate(['slug' => 'ali', 'user_id' => User::factory()->create(['first_name' => 'Ali', 'last_name' => 'Rezaei'])->id]);
    $this->sara = Supplier::forceCreate(['slug' => 'sara', 'user_id' => User::factory()->create(['first_name' => 'Sara', 'last_name' => 'Karimi'])->id]);

    $this->stock = function (string $name, float $quantity, ?float $min, ?float $max = null): WarehouseItem {
        $item = Item::forceCreate(['slug' => $name, 'name' => ucfirst($name), 'unit_group_id' => $this->group->id, 'category_id' => 1]);

        return WarehouseItem::forceCreate(['slug' => "main-{$name}", 'warehouse_id' => $this->main->id, 'item_id' => $item->id, 'quantity' => $quantity, 'min_quantity' => $min, 'max_quantity' => $max]);
    };
    $this->bought = function (WarehouseItem $stock, Supplier $supplier, float $price, PurchaseStatus $status = PurchaseStatus::RECEIVED, ?int $unitId = null) {
        static $n = 0;
        $n++;
        $purchase = Purchase::forceCreate(['slug' => "p{$n}", 'supplier_id' => $supplier->id, 'warehouse_id' => $this->main->id, 'order_number' => "P{$n}", 'status' => $status, 'ordered_at' => now()]);
        PurchaseItem::forceCreate(['slug' => "pi{$n}", 'purchase_id' => $purchase->id, 'item_id' => $stock->item_id, 'unit_id' => $unitId, 'quantity' => 1, 'unit_price' => $price, 'total' => $price]);

        return $purchase;
    };
    $this->groups = fn () => collect(resolve(ReorderSuggestions::class)->groups())
        ->mapWithKeys(fn (array $group) => [($group['supplier']?->slug ?? 'none') => collect($group['lines'])->mapWithKeys(fn (array $line) => [$line['warehouse_item']->slug => [$line['quantity'], $line['unit_price']]])->all()])
        ->all();
});

it('groups the items running low by the supplier they were last bought from', function () {
    $oil = ($this->stock)('oil', 3, 10);
    $filter = ($this->stock)('filter', 10, 10);
    $wiper = ($this->stock)('wiper', 2, 5);
    ($this->stock)('brake', 50, 10);                 // fine
    ($this->stock)('bulb', 0, null);                 // no minimum

    ($this->bought)($oil, $this->ali, 800);
    ($this->bought)($filter, $this->ali, 300);
    ($this->bought)($wiper, $this->sara, 120);

    $groups = ($this->groups)();

    expect(array_keys($groups))->toBe(['ali', 'sara'])
        ->and(array_keys($groups['ali']))->toBe(['main-oil', 'main-filter'])
        ->and($groups['ali']['main-oil'][1])->toBe(800.0)
        ->and($groups['sara']['main-wiper'][1])->toBe(120.0);
});

it('puts items that were never bought in a group of their own, last', function () {
    ($this->bought)(($this->stock)('oil', 1, 5), $this->ali, 100);
    ($this->stock)('wiper', 1, 5);

    $groups = ($this->groups)();

    expect(array_keys($groups))->toBe(['ali', 'none'])
        ->and($groups['none']['main-wiper'][1])->toBe(0.0);
});

it('uses the newest purchase that was not cancelled or deleted', function () {
    $oil = ($this->stock)('oil', 1, 5);

    ($this->bought)($oil, $this->ali, 100);
    ($this->bought)($oil, $this->sara, 150);
    ($this->bought)($oil, $this->ali, 999, PurchaseStatus::CANCELLED);
    ($this->bought)($oil, $this->ali, 888)->delete();

    expect(($this->groups)())->toBe(['sara' => ['main-oil' => [9.0, 150.0]]]);
});

it('suggests enough to reach the maximum, or twice the minimum, and at least one', function () {
    $withMax = ($this->stock)('with-max', 3, 10, 40);   // 40 - 3
    $noMax = ($this->stock)('no-max', 4, 10);            // 20 - 4
    $atMin = ($this->stock)('at-min', 10, 10, 10);       // a maximum no higher than the minimum is ignored: 20 - 10
    $over = ($this->stock)('over', 5, 5, 5.2);           // 5.2 - 5 rounds up to 1

    $suggest = resolve(ReorderSuggestions::class);

    expect($suggest->quantityToBuy($withMax))->toBe(37.0)
        ->and($suggest->quantityToBuy($noMax))->toBe(16.0)
        ->and($suggest->quantityToBuy($atMin))->toBe(10.0)
        ->and($suggest->quantityToBuy($over))->toBe(1.0);
});

it('prices per piece when the last purchase was by the carton', function () {
    $oil = ($this->stock)('oil', 1, 5);
    ($this->bought)($oil, $this->ali, 1200, unitId: $this->carton->id); // 12 pieces in a carton

    expect(($this->groups)()['ali']['main-oil'][1])->toBe(100.0);
});

it('hands a group to the purchase form with the quantities as edited', function () {
    $oil = ($this->stock)('oil', 3, 10);
    $filter = ($this->stock)('filter', 10, 10);
    ($this->bought)($oil, $this->ali, 800);
    ($this->bought)($filter, $this->ali, 300);

    Livewire::test(ReorderList::class)
        ->assertSee('data-reorder-group', false)
        ->assertSet('quantities.main-oil', 17.0)
        ->set('quantities.main-oil', '25')
        ->set('quantities.main-filter', '0')            // below one: the suggestion is kept
        ->call('startPurchase', 'w'.$this->main->id.'-s'.$this->ali->id)
        ->assertRedirect(route('purchases.create'));

    expect(session('purchase_prefill'))->toBe([
        'warehouse' => 'main',
        'supplier' => 'ali',
        'lines' => [
            'main-oil' => ['quantity' => 25.0, 'unit_price' => 800.0],
            'main-filter' => ['quantity' => 10.0, 'unit_price' => 300.0],
        ],
    ]);
});

it('opens the purchase form with those lines filled in, once', function () {
    session()->put('purchase_prefill', ['warehouse' => 'main', 'supplier' => 'ali', 'lines' => ['main-oil' => ['quantity' => 25.0, 'unit_price' => 800.0]]]);
    ($this->stock)('oil', 3, 10);

    Livewire::test(PurchaseCreate::class)
        ->assertSet('form.warehouse', 'main')
        ->assertSet('form.supplier', 'ali')
        ->assertSet('form.selected', ['main-oil'])
        ->assertSet('form.items.main-oil.quantity', 25.0)
        ->assertSet('form.items.main-oil.unit_price', 800.0)
        ->assertSet('form.items.main-oil.total', 20000.0);

    Livewire::test(PurchaseCreate::class)->assertSet('form.selected', []);
});

it('says so when nothing is running low', function () {
    ($this->stock)('oil', 50, 10);

    Livewire::test(ReorderList::class)->assertSee('data-reorder-empty', false)->assertDontSee('data-start-purchase', false);
});

it('ignores a start request for a group that is not on the list', function () {
    Livewire::test(ReorderList::class)->call('startPurchase', 'w1-s1')->assertNoRedirect();

    expect(session('purchase_prefill'))->toBeNull();
});
