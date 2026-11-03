<?php

use App\Helpers\Utils;
use Livewire\Livewire;
use Modules\Dashboard\Livewire\Reports;
use Modules\Inventory\Enums\PurchaseStatus;
use Modules\Inventory\Models\Purchase;
use Modules\Inventory\Models\PurchaseItem;
use Modules\Inventory\Models\Supplier;
use Modules\User\Models\User;
use Morilog\Jalali\Jalalian;

// Bare rows only (see OrderStockTest): foreign key checks are deferred.
beforeEach(function () {
    deferForeignKeys();

    $ali = Supplier::forceCreate(['slug' => 'ali', 'user_id' => User::factory()->create(['first_name' => 'Ali', 'last_name' => 'Rezaei'])->id]);
    $sara = Supplier::forceCreate(['slug' => 'sara', 'user_id' => User::factory()->create(['first_name' => 'Sara', 'last_name' => 'Karimi'])->id]);

    $purchase = function (string $number, Supplier $supplier, PurchaseStatus $status, array $lines, ?int $orderedDaysAgo, int $createdDaysAgo = 0) {
        $purchase = Purchase::forceCreate([
            'slug' => $number, 'supplier_id' => $supplier->id, 'warehouse_id' => 1, 'order_number' => $number, 'status' => $status,
            'ordered_at' => $orderedDaysAgo === null ? null : now()->subDays($orderedDaysAgo), 'created_at' => now()->subDays($createdDaysAgo),
        ]);
        foreach ($lines as $i => $total) {
            PurchaseItem::forceCreate(['slug' => "{$number}-{$i}", 'purchase_id' => $purchase->id, 'item_id' => 1, 'quantity' => 1, 'unit_price' => $total, 'total' => $total]);
        }

        return $purchase;
    };

    $purchase('P1', $ali, PurchaseStatus::RECEIVED, [1000, 500, 800], orderedDaysAgo: 2)->items()->latest('id')->first()->delete();
    $purchase('P2', $ali, PurchaseStatus::ORDERED, [2000], orderedDaysAgo: null, createdDaysAgo: 1);
    $purchase('P3', $sara, PurchaseStatus::PARTIALLY_RECEIVED, [700], orderedDaysAgo: 40, createdDaysAgo: 40);
    // None of these is spending: a draft, a cancelled purchase and a deleted one.
    $purchase('P4', $sara, PurchaseStatus::DRAFT, [9000], orderedDaysAgo: 1);
    $purchase('P5', $sara, PurchaseStatus::CANCELLED, [9000], orderedDaysAgo: 1);
    $purchase('P6', $ali, PurchaseStatus::RECEIVED, [5000], orderedDaysAgo: 1)->delete();

    $this->range = fn (string $from = '', string $to = '') => Livewire::test(Reports::class)
        ->set('filters.from', $from)
        ->set('filters.to', $to);
    $this->spent = fn ($rows) => $rows->map(fn ($row) => [$row->supplier->user->fullName(), (float) $row->spent, (int) $row->purchases])->all();
});

it('adds up what was committed to each supplier, biggest first', function () {
    ($this->range)()->assertViewHas('bySupplier', fn ($rows) => ($this->spent)($rows) === [['Ali Rezaei', 3500.0, 2], ['Sara Karimi', 700.0, 1]]);
});

it('limits purchases to the date range, dating an unordered purchase by when it was created', function () {
    $day = fn (int $daysAgo) => Jalalian::fromDateTime(now()->subDays($daysAgo))->format('Y/m/d');

    ($this->range)(Utils::pDigits($day(7)))
        ->assertViewHas('bySupplier', fn ($rows) => ($this->spent)($rows) === [['Ali Rezaei', 3500.0, 2]]);

    ($this->range)('', $day(30))
        ->assertViewHas('bySupplier', fn ($rows) => ($this->spent)($rows) === [['Sara Karimi', 700.0, 1]]);

    // Only P2, which has no order date, falls on this day.
    ($this->range)($day(1), $day(1))
        ->assertViewHas('bySupplier', fn ($rows) => ($this->spent)($rows) === [['Ali Rezaei', 2000.0, 1]]);

    ($this->range)(Jalalian::fromDateTime(now()->addDays(2))->format('Y/m/d'))
        ->assertViewHas('bySupplier', fn ($rows) => $rows->isEmpty());
});

it('shows the suppliers on the reports page', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('reports.index'))->assertOk()->assertSee(['Ali Rezaei', 'Sara Karimi']);
});
