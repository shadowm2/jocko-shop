<?php

namespace Modules\Inventory\Services;

use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\PurchaseItem;
use Modules\Inventory\Models\Supplier;
use Modules\Inventory\Models\Warehouse;
use Modules\Inventory\Models\WarehouseItem;

/**
 * What to buy again: the items at or under their minimum stock, grouped the way a
 * purchase order is (one warehouse, one supplier). The supplier is the one the item
 * was last bought from, and the price is what it cost then.
 *
 * @phpstan-type Line array{warehouse_item: WarehouseItem, quantity: float, unit_price: float}
 * @phpstan-type Group array{key: string, warehouse: Warehouse, supplier: ?Supplier, lines: list<Line>}
 */
class ReorderSuggestions
{
    /**
     * @return list<Group>
     */
    public function groups(): array
    {
        $low = WarehouseItem::query()->lowStock()
            ->with(['item.unitGroup.units', 'warehouse'])
            ->orderBy('warehouse_id')
            ->get();

        if ($low->isEmpty()) {
            return [];
        }

        $lastLines = PurchaseItem::query()
            ->whereIn('item_id', $low->pluck('item_id')->unique()->all())
            ->whereHas('purchase', fn ($purchases) => $purchases->where('status', '!=', 'cancelled'))
            ->with(['purchase.supplier.user', 'unit'])
            ->orderByDesc('id')
            ->get()
            ->groupBy('item_id');

        $groups = [];

        foreach ($low as $warehouseItem) {
            /** @var PurchaseItem|null $last */
            $last = $lastLines->get($warehouseItem->item_id)?->first();
            $supplier = $last?->purchase?->supplier;
            $key = 'w'.$warehouseItem->warehouse_id.'-s'.($supplier?->getKey() ?? 0);

            $groups[$key] ??= ['key' => $key, 'warehouse' => $warehouseItem->warehouse, 'supplier' => $supplier, 'lines' => []];
            $groups[$key]['lines'][] = [
                'warehouse_item' => $warehouseItem,
                'quantity' => $this->quantityToBuy($warehouseItem),
                'unit_price' => $last === null ? 0.0 : $this->pricePerBaseUnit($last, $warehouseItem->item),
            ];
        }

        usort($groups, fn (array $a, array $b): int => [$a['supplier'] === null, (string) $a['supplier']?->user?->fullName(), $a['warehouse']->name]
            <=> [$b['supplier'] === null, (string) $b['supplier']?->user?->fullName(), $b['warehouse']->name]);

        return $groups;
    }

    /**
     * Enough to reach the item's maximum, or twice its minimum when it has no maximum.
     */
    public function quantityToBuy(WarehouseItem $warehouseItem): float
    {
        $target = $warehouseItem->max_quantity !== null && (float) $warehouseItem->max_quantity > (float) $warehouseItem->min_quantity
            ? (float) $warehouseItem->max_quantity
            : (float) $warehouseItem->min_quantity * 2;

        return max(1.0, ceil($target - (float) $warehouseItem->quantity));
    }

    /**
     * A line is priced per the unit it was bought in; stock is counted in the base unit.
     */
    private function pricePerBaseUnit(PurchaseItem $line, Item $item): float
    {
        $price = (float) $line->unit_price;

        if ($line->unit === null) {
            return $price;
        }

        $base = $item->unitGroup->units->first(fn ($unit): bool => (bool) $unit->is_base);

        if ($base === null || (float) $base->conversion_factor <= 0 || (float) $line->unit->conversion_factor <= 0) {
            return 0.0;
        }

        $baseUnitsPerPurchaseUnit = (float) $line->unit->conversion_factor / (float) $base->conversion_factor;

        return round($price / $baseUnitsPerPurchaseUnit, 2);
    }
}
