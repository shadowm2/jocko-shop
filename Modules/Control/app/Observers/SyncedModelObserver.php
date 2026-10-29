<?php

namespace Modules\Control\Observers;

use Illuminate\Database\Eloquent\Model;
use Modules\Control\Services\OutboxRecorder;
use Modules\Control\Services\SyncEntities;
use Throwable;

/**
 * Turns every change to a synchronized model into an outbox event. A failure
 * here is reported but never stops the shop from saving its own data; the
 * `control:resync` command repairs any gap.
 */
class SyncedModelObserver
{
    public function __construct(
        private readonly OutboxRecorder $outbox,
        private readonly SyncEntities $entities,
    ) {}

    public function created(Model $model): void
    {
        $this->record($model, 'created');
    }

    public function updated(Model $model): void
    {
        // Logging in touches remember_token, an OTP changes otp_code, touch()
        // bumps updated_at, restoring clears deleted_at (and fires its own event):
        // none of that is a change to what is sent.
        if (! $model->wasChanged($this->entities->businessFieldsOf($model))) {
            return;
        }

        $this->record($model, 'updated');
    }

    public function deleted(Model $model): void
    {
        // A hard delete fires `deleted` and then `forceDeleted`: record it once.
        if (method_exists($model, 'isForceDeleting') && $model->isForceDeleting()) {
            return;
        }

        $this->record($model, 'deleted');
    }

    public function forceDeleted(Model $model): void
    {
        $this->record($model, 'deleted');
    }

    public function restored(Model $model): void
    {
        $this->record($model, 'restored');
    }

    /**
     * @param  'created'|'updated'|'deleted'|'restored'  $action
     */
    private function record(Model $model, string $action): void
    {
        if (! $this->outbox->isRecording()) {
            return;
        }

        try {
            $this->outbox->record($model, $action);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
