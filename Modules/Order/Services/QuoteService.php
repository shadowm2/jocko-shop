<?php

namespace Modules\Order\Services;

use App\Helpers\Utils;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\Warehouse;
use Modules\Inventory\Models\WarehouseItem;
use Modules\Order\Enums\QuoteStatus;
use Modules\Order\Models\Order;
use Modules\Order\Models\Quote;
use Modules\Order\Models\QuoteItem;

/**
 * Quotes are saved from the order form's data and handed back to it to become an order.
 */
class QuoteService
{
    /** How long a new quote stays valid. */
    public const VALID_DAYS = 7;

    /**
     * Create a quote from validated order-form data, or replace the lines and customer
     * of an existing one. An answered quote (accepted, rejected, converted) is not edited.
     *
     * @param  array<string, mixed>  $data  as OrderForm::validatedData() returns it
     */
    public function save(array $data, ?Quote $quote = null): Quote
    {
        if ($quote !== null && $quote->status !== QuoteStatus::DRAFT && $quote->status !== QuoteStatus::SENT) {
            throw new InvalidArgumentException('Only a quote nobody has answered yet can be changed.');
        }

        return DB::transaction(function () use ($data, $quote): Quote {
            $warehouse = Warehouse::query()->where('slug', $data['warehouse'])->firstOrFail();

            $values = [
                'user_id' => $data['user_id'],
                'user_car_id' => $data['user_car_id'],
                'warehouse_id' => $warehouse->id,
                'description' => $data['description'] ?? null,
            ];

            if ($quote === null) {
                $number = Quote::nextNumber();
                $quote = Quote::create($values + [
                    'slug' => Utils::generateUniqueSlug($number, Quote::class),
                    'quote_number' => $number,
                    'status' => QuoteStatus::getDefault(),
                    'valid_until' => now()->addDays(self::VALID_DAYS)->toDateString(),
                ]);
            } else {
                $quote->update($values);
            }

            $this->syncLines($quote, $warehouse, (array) ($data['items'] ?? []), (array) ($data['services'] ?? []));

            return $quote->refresh();
        });
    }

    /**
     * Replace the quote's lines with the given ones.
     *
     * @param  array<string, array<string, mixed>>  $items  by warehouse item slug
     * @param  array<string, array<string, mixed>>  $services  by service slug
     */
    private function syncLines(Quote $quote, Warehouse $warehouse, array $items, array $services): void
    {
        $quote->items()->forceDelete();

        $warehouseItems = WarehouseItem::query()->where('warehouse_id', $warehouse->id)->whereIn('slug', array_keys($items))->get()->keyBy('slug');

        foreach ($items as $slug => $line) {
            $warehouseItem = $warehouseItems->get($slug) ?? throw new InvalidArgumentException("Warehouse item not found in the quote warehouse: {$slug}");

            $this->addLine($quote, $warehouseItem->item_id, $line, filled($line['unit_id'] ?? null) ? (int) $line['unit_id'] : null);
        }

        $serviceItems = Item::query()->where('is_service', true)->whereIn('slug', array_keys($services))->get()->keyBy('slug');

        foreach ($services as $slug => $line) {
            $service = $serviceItems->get($slug) ?? throw new InvalidArgumentException("Service not found: {$slug}");

            $this->addLine($quote, $service->id, $line, null, filled($line['mechanic_id'] ?? null) ? (int) $line['mechanic_id'] : null);
        }
    }

    /**
     * @param  array<string, mixed>  $line
     */
    private function addLine(Quote $quote, int $itemId, array $line, ?int $unitId, ?int $mechanicId = null): void
    {
        $quantity = (float) $line['quantity'];
        $unitPrice = (float) $line['unit_price'];

        $quote->items()->create([
            'slug' => Utils::generateUniqueSlug('qi '.$quote->slug.' '.$itemId, QuoteItem::class),
            'item_id' => $itemId,
            'unit_id' => $unitId,
            'mechanic_id' => $mechanicId,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total' => round($quantity * $unitPrice, 2),
        ]);
    }

    public function total(Quote $quote): float
    {
        return (float) $quote->items()->sum('total');
    }

    /**
     * The quote as the order form takes it, plus how many lines could not come along
     * (an item that is no longer stocked in the quote's warehouse).
     *
     * @return array{form: array{user_id: int, user_car_id: int, warehouse: string, description: ?string, items: array<string, array<string, mixed>>, services: array<string, array<string, mixed>>}, dropped: int}
     */
    public function toOrderForm(Quote $quote): array
    {
        $quote->loadMissing(['warehouse', 'items.item']);

        $warehouseItems = WarehouseItem::query()->where('warehouse_id', $quote->warehouse_id)
            ->whereIn('item_id', $quote->items->pluck('item_id')->all())->get()->keyBy('item_id');

        $items = [];
        $services = [];
        $dropped = 0;

        foreach ($quote->items as $line) {
            $stocked = $warehouseItems->get($line->item_id);

            if ($line->item->is_service) {
                $services[$line->item->slug] = ['quantity' => (float) $line->quantity, 'unit_price' => (float) $line->unit_price, 'mechanic_id' => $line->mechanic_id];
            } elseif ($stocked !== null) {
                $items[$stocked->slug] = ['quantity' => (float) $line->quantity, 'unit_price' => (float) $line->unit_price, 'unit_id' => $line->unit_id];
            } else {
                $dropped++;
            }
        }

        return [
            'form' => [
                'user_id' => $quote->user_id,
                'user_car_id' => $quote->user_car_id,
                'warehouse' => $quote->warehouse->slug,
                'description' => $quote->description,
                'items' => $items,
                'services' => $services,
            ],
            'dropped' => $dropped,
        ];
    }

    public function markConverted(Quote $quote, Order $order): void
    {
        $quote->forceFill(['status' => QuoteStatus::CONVERTED, 'order_id' => $order->id])->save();
    }

    public function setStatus(Quote $quote, QuoteStatus $status): void
    {
        if ($quote->status === QuoteStatus::CONVERTED || $status === QuoteStatus::CONVERTED) {
            return;
        }

        $quote->forceFill(['status' => $status])->save();
    }
}
