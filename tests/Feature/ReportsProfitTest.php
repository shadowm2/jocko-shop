<?php

use App\Helpers\Utils;
use Livewire\Livewire;
use Modules\Dashboard\Livewire\Reports;
use Modules\Dashboard\Services\ProfitService;
use Modules\Inventory\Enums\StockMovementType;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\StockMovement;
use Modules\Inventory\Models\Unit;
use Modules\Inventory\Models\UnitGroup;
use Modules\Order\Enums\OrderStatus;
use Modules\Order\Models\Order;
use Modules\Order\Models\OrderItem;
use Modules\User\Models\User;
use Morilog\Jalali\Jalalian;

// Bare rows only: foreign key checks are deferred.
beforeEach(function () {
    deferForeignKeys();
    $this->actingAs($this->user = User::factory()->create());

    $group = UnitGroup::forceCreate(['name' => 'Count', 'slug' => 'count']);
    $this->piece = Unit::forceCreate(['name' => 'Piece', 'symbol' => 'pc', 'unit_group_id' => $group->id, 'conversion_factor' => 1, 'is_base' => true, 'slug' => 'piece']);
    $this->box = Unit::forceCreate(['name' => 'Box', 'symbol' => 'box', 'unit_group_id' => $group->id, 'conversion_factor' => 12, 'is_base' => false, 'slug' => 'box']);
    $item = fn (string $slug, bool $service = false) => Item::forceCreate(['name' => ucfirst($slug), 'slug' => $slug, 'unit_group_id' => $group->id, 'category_id' => 1, 'is_service' => $service]);
    $this->pad = $item('pad');
    $this->oil = $item('oil');
    $this->labour = $item('labour', service: true);

    $this->n = 0;
    $movement = fn (Item $item, StockMovementType $type, float $quantity, float $totalCost) => StockMovement::forceCreate([
        'slug' => 'm'.++$this->n, 'item_id' => $item->id, 'warehouse_item_id' => 1, 'type' => $type, 'quantity' => $quantity, 'unit_cost' => $quantity > 0 ? $totalCost / $quantity : 0, 'total_cost' => $totalCost,
    ]);
    // Bought at 100 a piece, then at 140: the average is 120. Sales and adjustments are not purchases.
    $movement($this->pad, StockMovementType::PURCHASE, 10, 1000);
    $movement($this->pad, StockMovementType::RECEIPT, 10, 1400);
    $movement($this->pad, StockMovementType::SALE, 50, 99999);
    $movement($this->pad, StockMovementType::ADJUSTMENT, 5, 0);

    $sell = function (Item $item, float $quantity, float $total, Unit $unit, OrderStatus $status = OrderStatus::COMPLETED, int $daysAgo = 0) {
        $n = ++$this->n;
        $order = Order::forceCreate(['slug' => "o{$n}", 'user_id' => $this->user->id, 'user_car_id' => 1, 'warehouse_id' => 1, 'order_number' => "ORD-{$n}", 'status' => $status, 'created_at' => now()->subDays($daysAgo)]);

        return OrderItem::forceCreate(['slug' => "oi{$n}", 'order_id' => $order->id, 'item_id' => $item->id, 'unit_id' => $unit->id, 'quantity' => $quantity, 'unit_price' => $total / $quantity, 'total' => $total]);
    };
    $sell($this->pad, 2, 400, $this->piece);                                         // cost 240
    $sell($this->pad, 1, 2000, $this->box, OrderStatus::PENDING, daysAgo: 40);        // a box of 12: cost 1 440
    $sell($this->pad, 100, 9000, $this->piece, OrderStatus::CANCELLED);               // not a sale
    $sell($this->pad, 5, 9000, $this->piece)->delete();                              // removed line
    $sell($this->oil, 4, 500, $this->piece);                                         // never bought
    $sell($this->labour, 1, 300, $this->piece);                                      // labour
});

it('works out profit on parts from their average purchase cost, in base units', function () {
    $report = resolve(ProfitService::class)->between(null, null);

    expect($report['parts_revenue'])->toBe(2400.0)
        ->and($report['parts_cost'])->toBe(1680.0)
        ->and($report['parts_profit'])->toBe(720.0)
        ->and($report['margin'])->toBe(30.0)
        ->and($report['services_revenue'])->toBe(300.0)
        ->and($report['unknown_cost_revenue'])->toBe(500.0)
        ->and($report['items'])->toBe([['name' => 'Pad', 'revenue' => 2400.0, 'cost' => 1680.0, 'profit' => 720.0, 'margin' => 30.0]]);
});

it('limits the profit to the date range', function () {
    $day = Utils::pDigits(Jalalian::fromDateTime(now()->subDays(7))->format('Y/m/d'));
    $report = resolve(ProfitService::class)->between(Utils::jalaliToDate($day), null);

    expect($report['parts_revenue'])->toBe(400.0)
        ->and($report['parts_cost'])->toBe(240.0)
        ->and($report['margin'])->toBe(40.0);
});

it('has no margin when nothing with a known cost was sold', function () {
    OrderItem::query()->where('item_id', $this->pad->id)->get()->each->delete();

    $report = resolve(ProfitService::class)->between(null, null);

    expect($report['margin'])->toBeNull()
        ->and($report['parts_profit'])->toBe(0.0)
        ->and($report['items'])->toBe([])
        ->and($report['unknown_cost_revenue'])->toBe(500.0);
});

it('shows the profit on the reports page', function () {
    Livewire::test(Reports::class)
        ->assertSeeHtml('data-parts-profit="720"')
        ->assertSeeHtml('data-parts-cost="1680"')
        ->assertSeeHtml('data-services-revenue="300"')
        ->assertSeeHtml('data-unknown-cost="500"')
        ->assertSeeHtml('data-profit-item="Pad"');

    $this->get(route('reports.index'))->assertOk()->assertSee(__('dashboard::strings.Profit'));
});
