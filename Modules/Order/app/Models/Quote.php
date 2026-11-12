<?php

namespace Modules\Order\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Car\Models\UserCar;
use Modules\Inventory\Models\Warehouse;
use Modules\Order\Database\Factories\QuoteFactory;
use Modules\Order\Enums\QuoteStatus;
use Modules\User\Models\User;

/**
 * A price offered to a customer before any work starts. It holds no stock and is
 * not revenue; accepting it means turning it into an order.
 *
 * @extends BaseModel<Quote>
 *
 * @property string $slug
 * @property string $quote_number
 * @property int $user_id
 * @property int $user_car_id
 * @property int $warehouse_id
 * @property QuoteStatus $status
 * @property ?\Carbon\CarbonImmutable $valid_until
 * @property ?string $description
 * @property ?int $order_id
 * @property-read ?float $items_sum_total
 * @property-read User $user
 * @property-read UserCar $userCar
 * @property-read Warehouse $warehouse
 */
#[Fillable(['slug', 'quote_number', 'user_id', 'user_car_id', 'warehouse_id', 'status', 'valid_until', 'description', 'order_id'])]
class Quote extends BaseModel
{
    /** @use HasFactory<QuoteFactory> */
    use HasFactory;

    protected $casts = [
        'status' => QuoteStatus::class,
        'valid_until' => 'immutable_date',
    ];

    protected static function newFactory(): QuoteFactory
    {
        return QuoteFactory::new();
    }

    /**
     * ponytail: max id + 1 can collide under concurrent creates (unique index
     * turns that into an error), as with order numbers.
     */
    public static function nextNumber(): string
    {
        return 'QT-'.str_pad((string) ((int) static::withTrashed()->max('id') + 1), 5, '0', STR_PAD_LEFT);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<UserCar, $this> */
    public function userCar(): BelongsTo
    {
        return $this->belongsTo(UserCar::class);
    }

    /** @return BelongsTo<Warehouse, $this> */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return HasMany<QuoteItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(QuoteItem::class);
    }

    /**
     * Past its valid-until day without an answer.
     */
    public function isExpired(): bool
    {
        return $this->status->isOpen() && $this->valid_until !== null && $this->valid_until->endOfDay()->isPast();
    }
}
