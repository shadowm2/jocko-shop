<?php

namespace Modules\Order\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A text sent to a customer about what they owe.
 *
 * @property int $id
 * @property int $user_id
 * @property ?int $sent_by
 * @property float $amount
 * @property int $orders_count
 * @property 'sent'|'failed' $status
 * @property ?string $error
 * @property \Illuminate\Support\Carbon $created_at
 */
#[Fillable(['user_id', 'sent_by', 'amount', 'orders_count', 'status', 'error'])]
class PaymentReminder extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['amount' => 'decimal:4'];
    }
}
