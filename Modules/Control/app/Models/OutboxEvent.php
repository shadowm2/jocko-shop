<?php

namespace Modules\Control\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One change waiting to be sent to Jocko Control. Written in the same
 * transaction as the change itself, so a change is never lost and never
 * reported without having happened.
 *
 * @property int $id
 * @property string $event_id
 * @property string $event_type
 * @property string $aggregate_type
 * @property string $aggregate_id
 * @property array<string, mixed> $payload
 * @property \Carbon\CarbonImmutable $occurred_at
 * @property \Carbon\CarbonImmutable|null $pushed_at
 * @property string|null $rejected_code
 * @property int $attempts
 * @property string|null $last_error
 */
#[Fillable(['event_id', 'event_type', 'aggregate_type', 'aggregate_id', 'payload', 'occurred_at', 'pushed_at', 'rejected_code', 'attempts', 'last_error'])]
class OutboxEvent extends Model
{
    protected $table = 'control_outbox_events';

    /**
     * Keep microseconds: occurred_at orders the changes of one record.
     */
    protected $dateFormat = 'Y-m-d H:i:s.u';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'occurred_at' => 'datetime',
            'pushed_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    /**
     * @param  Builder<OutboxEvent>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->whereNull('pushed_at')->whereNull('rejected_code');
    }
}
