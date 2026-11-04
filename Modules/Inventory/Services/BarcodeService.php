<?php

namespace Modules\Inventory\Services;

use App\Helpers\Utils;
use Modules\Inventory\Models\ItemBrand;
use Modules\Inventory\Models\Warehouse;
use Modules\Inventory\Models\WarehouseItem;

/**
 * Finds the stock a scanned (or typed) code stands for: a brand's barcode, SKU or part
 * number, in a given warehouse.
 */
class BarcodeService
{
    /**
     * @return array{status: 'found', warehouse_item: WarehouseItem, sale_price: ?float}|array{status: 'unknown'|'not_stocked', warehouse_item: null, sale_price: null}
     */
    public function lookup(string $code, string $warehouseSlug): array
    {
        $code = trim(Utils::eDigits($code));

        $brands = $code === '' ? collect() : ItemBrand::query()
            ->where('is_active', true)
            ->where(fn ($query) => $query->where('barcode', $code)->orWhere('sku', $code)->orWhere('part_number', $code))
            ->orderBy('id')
            ->get();

        if ($brands->isEmpty()) {
            return ['status' => 'unknown', 'warehouse_item' => null, 'sale_price' => null];
        }

        $warehouse = Warehouse::query()->where('slug', $warehouseSlug)->first();

        foreach ($brands as $brand) {
            $warehouseItem = $warehouse === null ? null : WarehouseItem::query()
                ->where('warehouse_id', $warehouse->id)
                ->where('item_id', $brand->item_id)
                ->first();

            if ($warehouseItem !== null) {
                return ['status' => 'found', 'warehouse_item' => $warehouseItem, 'sale_price' => $brand->sale_price === null ? null : (float) $brand->sale_price];
            }
        }

        return ['status' => 'not_stocked', 'warehouse_item' => null, 'sale_price' => null];
    }
}
