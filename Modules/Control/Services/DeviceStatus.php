<?php

namespace Modules\Control\Services;

use Composer\InstalledVersions;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Facades\DB;
use Modules\Control\Models\OutboxEvent;
use Throwable;

/**
 * What this installation can truthfully say about itself.
 */
#[Singleton]
class DeviceStatus
{
    public function __construct(private readonly ControlLink $link) {}

    public function appVersion(): string
    {
        if (filled(config('control.app_version'))) {
            return (string) config('control.app_version');
        }

        $root = InstalledVersions::getRootPackage();
        $reference = $root['reference'] ?? null;

        return $root['pretty_version'].($reference ? '@'.substr($reference, 0, 7) : '');
    }

    /**
     * The newest migration that has run, which identifies the schema.
     */
    public function schemaVersion(): ?string
    {
        try {
            $migration = DB::table('migrations')->orderByDesc('id')->value('migration');

            return is_string($migration) ? $migration : null;
        } catch (Throwable) {
            return null;
        }
    }

    public function databaseOk(): bool
    {
        try {
            DB::select('select 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public function diskFreeMb(): ?int
    {
        $bytes = @disk_free_space(base_path());

        return $bytes === false ? null : (int) floor($bytes / 1048576);
    }

    /**
     * The heartbeat body. `sync` is only present when a push happened since the
     * last heartbeat, so Jocko Control never hears an outcome that is not new.
     *
     * @return array<string, mixed>
     */
    public function heartbeatPayload(): array
    {
        $databaseOk = $this->databaseOk();

        return array_filter([
            'app_version' => $this->appVersion(),
            'database_schema_version' => $this->schemaVersion(),
            'local_status' => array_filter([
                'state' => $databaseOk ? 'idle' : 'error',
                'pending_events' => $databaseOk ? $this->pendingEvents() : null,
                'database_ok' => $databaseOk,
                'disk_free_mb' => $this->diskFreeMb(),
            ], fn (mixed $value) => $value !== null),
            'sync' => $this->link->pullPushReport(),
        ], fn (mixed $value) => $value !== null);
    }

    public function pendingEvents(): ?int
    {
        try {
            return OutboxEvent::query()->pending()->count();
        } catch (Throwable) {
            return null;
        }
    }
}
