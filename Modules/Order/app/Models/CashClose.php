<?php

namespace Modules\Order\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * The till closed for one day: what the day's payments added up to, per method, and the
 * cash that was actually counted.
 *
 * @property int $id
 * @property CarbonImmutable $business_date
 * @property array<string, float> $totals
 * @property float $expected_cash
 * @property float $counted_cash
 * @property float $difference
 * @property ?string $note
 * @property ?int $closed_by
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['business_date', 'totals', 'expected_cash', 'counted_cash', 'difference', 'note', 'closed_by'])]
class CashClose extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'business_date' => 'immutable_date',
            'totals' => 'array',
            'expected_cash' => 'float',
            'counted_cash' => 'float',
            'difference' => 'float',
        ];
    }
}
