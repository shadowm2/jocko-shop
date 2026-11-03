<?php

namespace Modules\Order\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Order\Enums\PaymentAttemptStatus;

/**
 * One try at collecting money for an order, online or on a card terminal.
 * `reference` is the gateway's own token for it (Zarinpal's authority, NextPay's
 * trans_id, Zibal's trackId); `public_id` is what the customer's browser carries.
 *
 * @property int $id
 * @property string $public_id
 * @property int $order_id
 * @property 'gateway'|'pos' $kind
 * @property string $driver
 * @property float $amount
 * @property string $currency
 * @property PaymentAttemptStatus $status
 * @property ?string $reference
 * @property ?string $pay_url
 * @property ?string $result_reference
 * @property ?string $card_pan
 * @property ?string $failure
 * @property ?int $payment_id
 * @property \Illuminate\Support\Carbon $expires_at
 * @property ?\Illuminate\Support\Carbon $last_checked_at
 * @property ?\Illuminate\Support\Carbon $completed_at
 * @property ?\Illuminate\Support\Carbon $refunded_at
 * @property ?int $refund_payment_id
 * @property-read Order $order
 */
#[Fillable(['public_id', 'order_id', 'user_id', 'kind', 'driver', 'amount', 'currency', 'status', 'reference', 'pay_url', 'result_reference', 'card_pan', 'failure', 'payment_id', 'expires_at', 'last_checked_at', 'completed_at', 'refunded_at', 'refund_payment_id'])]
class PaymentAttempt extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PaymentAttemptStatus::class,
            'amount' => 'decimal:4',
            'expires_at' => 'datetime',
            'last_checked_at' => 'datetime',
            'completed_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function isOpen(): bool
    {
        return $this->status === PaymentAttemptStatus::PENDING;
    }

    public function isOnline(): bool
    {
        return $this->kind === 'gateway';
    }
}
