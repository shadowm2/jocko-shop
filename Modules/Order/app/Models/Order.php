<?php

namespace Modules\Order\Models;

use App\Models\BaseModel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Car\Models\UserCar;
use Modules\Inventory\Models\Warehouse;
use Modules\Order\Database\Factories\OrderFactory;
use Modules\Order\Enums\OrderStatus;
use Modules\Order\Enums\PaymentStatus;
use Modules\User\Models\User;

/**
 * @extends BaseModel<Order>
 *
 * @property int $user_id
 * @property int $user_car_id
 * @property int $warehouse_id
 * @property string $slug
 * @property string $order_number
 * @property OrderStatus $status
 * @property ?string $description
 * @property ?int $odometer
 * @property ?CarbonImmutable $next_service_at
 * @property ?CarbonImmutable $service_reminded_at
 * @property-read ?float $items_sum_total
 * @property-read ?float $payments_sum_amount
 * @property-read Warehouse $warehouse
 */
#[Fillable(['slug', 'user_id', 'user_car_id', 'warehouse_id', 'order_number', 'status', 'description', 'odometer', 'next_service_at', 'service_reminded_at'])]
class Order extends BaseModel
{
    /**
     * @use HasFactory<OrderFactory>
     */
    use HasFactory;

    protected $casts = [
        'status' => OrderStatus::class,
        'next_service_at' => 'immutable_date',
        'service_reminded_at' => 'immutable_datetime',
    ];

    protected static function booted(): void
    {
        parent::booted();

        // A new service date is a new promise: remind about it again.
        static::saving(function (Order $order): void {
            if ($order->isDirty('next_service_at') && ! $order->isDirty('service_reminded_at')) {
                $order->service_reminded_at = null;
            }
        });
    }

    protected static function newFactory(): OrderFactory
    {
        return OrderFactory::new();
    }

    /**
     * ponytail: max id + 1 can collide under concurrent creates (unique
     * index turns that into an error); use a counter table if it ever bites.
     */
    public static function nextNumber(): string
    {
        return 'ORD-'.str_pad((string) ((int) static::withTrashed()->max('id') + 1), 5, '0', STR_PAD_LEFT);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<UserCar, $this>
     */
    public function userCar(): BelongsTo
    {
        return $this->belongsTo(UserCar::class);
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * @return HasMany<PaymentAttempt, $this>
     */
    public function paymentAttempts(): HasMany
    {
        return $this->hasMany(PaymentAttempt::class);
    }

    /**
     * Uses the eager loaded sums when the list query has them.
     */
    /**
     * By order number, or by the customer's name or mobile.
     *
     * @param  Builder<Order>  $query
     */
    #[Scope]
    protected function matching(Builder $query, string $term): void
    {
        $query->where(fn (Builder $query) => $query
            ->where('order_number', 'like', "%{$term}%")
            ->orWhereHas('user', fn (Builder $user) => $user
                ->where('first_name', 'like', "%{$term}%")
                ->orWhere('last_name', 'like', "%{$term}%")
                ->orWhere('mobile', 'like', "%{$term}%")));
    }

    /**
     * The same rule as PaymentStatus::for(), computed in SQL so a list can filter on it.
     *
     * @param  Builder<Order>  $query
     */
    #[Scope]
    protected function paymentStatusIs(Builder $query, PaymentStatus $status): void
    {
        $total = '(select coalesce(sum(total), 0) from order_items where order_items.order_id = orders.id and order_items.deleted_at is null)';
        $paid = '(select coalesce(sum(amount), 0) from payments where payments.order_id = orders.id and payments.deleted_at is null and (payments.cheque_status is null or payments.cheque_status != \'bounced\'))';

        $query->whereRaw(match ($status) {
            PaymentStatus::UNPAID => "round({$paid}, 2) <= 0",
            PaymentStatus::PAID => "round({$paid}, 2) > 0 and round({$paid}, 2) >= round({$total}, 2)",
            PaymentStatus::PARTIAL => "round({$paid}, 2) > 0 and round({$paid}, 2) < round({$total}, 2)",
        });
    }

    public function paymentStatus(): PaymentStatus
    {
        return PaymentStatus::for(
            (float) ($this->items_sum_total ?? $this->items()->sum('total')),
            (float) ($this->payments_sum_amount ?? $this->payments()->sum('amount')),
        );
    }
}
