<?php

namespace Modules\Dashboard\Services;

use Illuminate\Support\Facades\DB;
use Modules\Inventory\Enums\StockMovementType;
use Modules\Inventory\Models\StockMovement;
use Modules\Order\Enums\OrderStatus;

/**
 * What the shop earned on what it sold, estimated from what it paid.
 *
 * The cost of a part is its average purchase cost (everything received through
 * purchases and receipts, per base unit); the shop does not record what a sale was
 * bought for, so this is an estimate, and a part that was never purchased has no cost
 * and is reported apart rather than counted as pure profit. Services have no cost
 * here: their revenue is reported on its own (the shop does not track wages).
 *
 * @phpstan-type ItemProfit array{name: string, revenue: float, cost: float, profit: float, margin: float}
 */
class ProfitService
{
    /**
     * @return array{
     *     parts_revenue: float,
     *     parts_cost: float,
     *     parts_profit: float,
     *     margin: ?float,
     *     services_revenue: float,
     *     unknown_cost_revenue: float,
     *     items: list<ItemProfit>
     * }
     */
    public function between(?string $from, ?string $to, int $limit = 10): array
    {
        $averageCost = StockMovement::query()
            ->whereIn('type', [StockMovementType::PURCHASE->value, StockMovementType::RECEIPT->value])
            ->where('quantity', '>', 0)
            ->selectRaw('item_id, sum(total_cost) / sum(quantity) as average_cost')
            ->groupBy('item_id');

        $lines = DB::table('order_items as line')
            ->join('orders as sale', 'sale.id', '=', 'line.order_id')
            ->join('items as item', 'item.id', '=', 'line.item_id')
            ->leftJoin('units as sold_unit', 'sold_unit.id', '=', 'line.unit_id')
            ->leftJoin('units as base_unit', fn ($join) => $join->on('base_unit.unit_group_id', '=', 'item.unit_group_id')->where('base_unit.is_base', true)->whereNull('base_unit.deleted_at'))
            ->leftJoinSub($averageCost, 'cost', 'cost.item_id', '=', 'line.item_id')
            ->whereNull('line.deleted_at')
            ->whereNull('sale.deleted_at')
            ->where('sale.status', '!=', OrderStatus::CANCELLED->value)
            ->when($from, fn ($query) => $query->whereDate('sale.created_at', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('sale.created_at', '<=', $to))
            ->groupBy('line.item_id', 'item.name', 'item.is_service')
            ->selectRaw('line.item_id, item.name, item.is_service, sum(line.total) as revenue, max(cost.average_cost) as average_cost, sum(line.quantity * coalesce(sold_unit.conversion_factor, base_unit.conversion_factor, 1) / coalesce(base_unit.conversion_factor, 1)) as base_quantity')
            ->get();

        $report = ['parts_revenue' => 0.0, 'parts_cost' => 0.0, 'parts_profit' => 0.0, 'margin' => null, 'services_revenue' => 0.0, 'unknown_cost_revenue' => 0.0, 'items' => []];
        $items = [];

        foreach ($lines as $line) {
            $revenue = (float) $line->revenue;

            if ($line->is_service) {
                $report['services_revenue'] += $revenue;

                continue;
            }

            if ($line->average_cost === null) {
                $report['unknown_cost_revenue'] += $revenue;

                continue;
            }

            $cost = round((float) $line->base_quantity * (float) $line->average_cost, 2);
            $report['parts_revenue'] += $revenue;
            $report['parts_cost'] += $cost;
            $items[] = [
                'name' => (string) $line->name,
                'revenue' => round($revenue, 2),
                'cost' => $cost,
                'profit' => round($revenue - $cost, 2),
                'margin' => $revenue > 0 ? round(($revenue - $cost) / $revenue * 100, 1) : 0.0,
            ];
        }

        usort($items, fn (array $a, array $b): int => [$b['profit'], $a['name']] <=> [$a['profit'], $b['name']]);

        $report['parts_revenue'] = round($report['parts_revenue'], 2);
        $report['parts_cost'] = round($report['parts_cost'], 2);
        $report['parts_profit'] = round($report['parts_revenue'] - $report['parts_cost'], 2);
        $report['margin'] = $report['parts_revenue'] > 0 ? round($report['parts_profit'] / $report['parts_revenue'] * 100, 1) : null;
        $report['services_revenue'] = round($report['services_revenue'], 2);
        $report['unknown_cost_revenue'] = round($report['unknown_cost_revenue'], 2);
        $report['items'] = array_slice($items, 0, $limit);

        return $report;
    }
}
