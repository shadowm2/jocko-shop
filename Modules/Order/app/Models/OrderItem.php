<?php

namespace Modules\Order\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\Unit;
use Modules\Order\Database\Factories\OrderItemFactory;

/**
 * @extends BaseModel<OrderItem>
 *
 * @property string $slug
 * @property int $order_id
 * @property int $item_id
 * @property ?int $unit_id
 * @property float $quantity
 * @property float $unit_price
 * @property float $total
 * @property-read Item $item
 */
#[Fillable(['slug', 'order_id', 'item_id', 'mechanic_id', 'unit_id', 'quantity', 'unit_price', 'total'])]
class OrderItem extends BaseModel
{
    /**
     * @use HasFactory<OrderItemFactory>
     */
    use HasFactory;

    protected static function newFactory(): OrderItemFactory
    {
        return OrderItemFactory::new();
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<Item, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * @return BelongsTo<Unit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
