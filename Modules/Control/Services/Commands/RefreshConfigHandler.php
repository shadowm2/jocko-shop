<?php

namespace Modules\Control\Services\Commands;

use Illuminate\Support\Facades\Cache;
use Modules\Control\Exceptions\CommandFailedException;
use Modules\Control\Services\ControlClient;

/**
 * Pulls the configuration Jocko Control publishes for this device and keeps it
 * in the cache as `control.remote_config`. Nothing in the shop reads it yet.
 */
class RefreshConfigHandler implements RemoteCommandHandler
{
    public function __construct(private readonly ControlClient $client) {}

    public function handle(array $payload): array
    {
        $config = $this->client->me()['config'] ?? null;

        if (! is_array($config)) {
            throw new CommandFailedException('Jocko Control did not publish a configuration.');
        }

        Cache::forever('control.remote_config', $config);

        return ['config' => $config];
    }
}
