<?php

namespace Modules\Order\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\Unit;

/**
 * @extends BaseModel<QuoteItem>
 *
 * @property string $slug
 * @property int $quote_id
 * @property int $item_id
 * @property ?int $unit_id
 * @property ?int $mechanic_id
 * @property float $quantity
 * @property float $unit_price
 * @property float $total
 * @property-read Item $item
 * @property-read ?Unit $unit
 */
#[Fillable(['slug', 'quote_id', 'item_id', 'unit_id', 'mechanic_id', 'quantity', 'unit_price', 'total'])]
class QuoteItem extends BaseModel
{
    /** @return BelongsTo<Quote, $this> */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    /** @return BelongsTo<Item, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /** @return BelongsTo<Unit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
