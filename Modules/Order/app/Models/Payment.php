<?php

namespace Modules\Order\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Order\Database\Factories\PaymentFactory;
use Modules\Order\Enums\ChequeStatus;
use Modules\Order\Enums\PaymentMethod;
use Morilog\Jalali\Jalalian;

/**
 * @extends BaseModel<Payment>
 *
 * @property string $slug
 * @property int $order_id
 * @property float $amount
 * @property PaymentMethod $method
 * @property \Illuminate\Support\Carbon $paid_at
 * @property ?string $description
 * @property ?string $cheque_number
 * @property ?string $cheque_bank
 * @property ?\Illuminate\Support\Carbon $cheque_due_at
 * @property ?ChequeStatus $cheque_status
 * @property-read Jalalian $paid_at_jalali
 * @property-read Order $order
 */
#[Fillable(['slug', 'order_id', 'amount', 'method', 'paid_at', 'description', 'cheque_number', 'cheque_bank', 'cheque_due_at', 'cheque_status'])]
class Payment extends BaseModel
{
    /**
     * @use HasFactory<PaymentFactory>
     */
    use HasFactory;

    protected $casts = [
        'method' => PaymentMethod::class,
        'paid_at' => 'date',
        'cheque_due_at' => 'date',
        'cheque_status' => ChequeStatus::class,
    ];

    /** Name of the scope that hides bounced cheques. */
    public const NOT_BOUNCED = 'notBounced';

    /**
     * A bounced cheque was never money: it stops counting toward what an order has
     * been paid, in every sum and list. The cheques page asks for it back explicitly.
     */
    protected static function booted(): void
    {
        parent::booted();

        static::addGlobalScope(self::NOT_BOUNCED, fn (Builder $query) => $query->where(
            fn (Builder $payments) => $payments->whereNull('payments.cheque_status')->orWhere('payments.cheque_status', '!=', ChequeStatus::BOUNCED->value)
        ));
    }

    protected static function newFactory(): PaymentFactory
    {
        return PaymentFactory::new();
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return Attribute<Jalalian, never> */
    public function paidAtJalali(): Attribute
    {
        return Attribute::get(fn () => Jalalian::fromDateTime($this->paid_at));
    }
}
