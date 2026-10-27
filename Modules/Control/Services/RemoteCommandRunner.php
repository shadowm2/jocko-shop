<?php

namespace Modules\Control\Services;

use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Str;
use Modules\Control\Exceptions\CommandFailedException;
use Modules\Control\Services\Commands\CheckVersionHandler;
use Modules\Control\Services\Commands\CollectDiagnosticsHandler;
use Modules\Control\Services\Commands\RefreshConfigHandler;
use Modules\Control\Services\Commands\RemoteCommandHandler;
use Modules\Control\Services\Commands\SyncNowHandler;
use Throwable;

/**
 * Runs a command received from Jocko Control, but only if its type is in this
 * list. The list lives in code on purpose: what a central server may ask this
 * machine to do must not be changeable through configuration or by the server.
 */
#[Singleton]
class RemoteCommandRunner
{
    /** @var array<string, class-string<RemoteCommandHandler>> */
    public const HANDLERS = [
        'sync_now' => SyncNowHandler::class,
        'check_version' => CheckVersionHandler::class,
        'collect_diagnostics' => CollectDiagnosticsHandler::class,
        'refresh_config' => RefreshConfigHandler::class,
    ];

    /**
     * @param  array<string, mixed>  $command  one entry of GET /device/commands
     * @return array{status: 'completed'|'failed', result: array<string, mixed>|null, error: string|null}
     */
    public function run(array $command): array
    {
        $type = is_string($command['type'] ?? null) ? $command['type'] : '';
        $payload = is_array($command['payload'] ?? null) ? $command['payload'] : [];
        $handler = self::HANDLERS[$type] ?? null;

        if ($handler === null) {
            return ['status' => 'failed', 'result' => null, 'error' => 'Unsupported command type.'];
        }

        try {
            return ['status' => 'completed', 'result' => resolve($handler)->handle($payload), 'error' => null];
        } catch (CommandFailedException $e) {
            return ['status' => 'failed', 'result' => null, 'error' => Str::limit($e->getMessage(), 500)];
        } catch (Throwable $e) {
            report($e);

            return ['status' => 'failed', 'result' => null, 'error' => 'The command failed with '.class_basename($e).'.'];
        }
    }
}
