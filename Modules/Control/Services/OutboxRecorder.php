<?php

namespace Modules\Control\Services;

use Carbon\CarbonInterface;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Modules\Control\Models\OutboxEvent;

/**
 * Writes changes to the outbox. It does nothing while this installation is not
 * linked to Jocko Control, so a stand-alone shop collects nothing.
 */
#[Singleton]
class OutboxRecorder
{
    public function __construct(
        private readonly SyncEntities $entities,
        private readonly ControlClient $client,
    ) {}

    public function isRecording(): bool
    {
        return $this->client->isConfigured();
    }

    /**
     * @param  'created'|'updated'|'deleted'|'restored'|'snapshot'  $action
     * @param  bool  $reload  read the row back first: a model just created holds
     *                        only what was assigned, not the database defaults
     */
    public function record(Model $model, string $action, ?CarbonInterface $occurredAt = null, bool $reload = true): OutboxEvent
    {
        $type = $this->entities->typeOf($model);
        $model = $reload ? $this->persistedState($model) : $model;

        return OutboxEvent::create([
            'event_id' => (string) Str::uuid(),
            'event_type' => "{$type}.{$action}",
            'aggregate_type' => $type,
            'aggregate_id' => $this->entities->aggregateId($type, $model->getKey()),
            'payload' => $this->entities->payloadOf($model),
            'occurred_at' => $occurredAt ?? now(),
        ]);
    }

    /**
     * The row as stored, soft-deleted or not. A hard-deleted record is gone,
     * so the model in memory is all there is.
     */
    private function persistedState(Model $model): Model
    {
        return $this->entities->queryWithDeleted($model::class)->whereKey($model->getKey())->first() ?? $model;
    }
}
