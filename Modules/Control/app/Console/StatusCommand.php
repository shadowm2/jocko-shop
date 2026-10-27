<?php

namespace Modules\Control\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Modules\Control\Exceptions\ControlException;
use Modules\Control\Services\ControlClient;
use Modules\Control\Services\ControlLink;

#[Signature('control:status')]
#[Description('Check the connection to Jocko Control and show how this device is registered')]
class StatusCommand extends Command
{
    public function handle(ControlClient $client, ControlLink $link): int
    {
        $this->components->twoColumnDetail('URL', (string) (config('control.url') ?: 'not set'));
        $this->components->twoColumnDetail('Token', filled(config('control.token')) ? 'set (hidden)' : 'not set');

        if (! $client->isConfigured()) {
            $this->components->error('Set JOCKO_CONTROL_URL and JOCKO_CONTROL_TOKEN in .env.');

            return self::FAILURE;
        }

        try {
            $device = $client->me();
        } catch (ControlException $e) {
            $this->components->error("{$e->errorCode}: {$e->getMessage()}");

            return self::FAILURE;
        }

        $this->components->twoColumnDetail('Shop', (string) ($device['shop']['name'] ?? '?'));
        $this->components->twoColumnDetail('Device', (string) ($device['name'] ?? '?'));
        $this->components->twoColumnDetail('Server time', (string) ($device['server_time'] ?? '?'));
        $this->components->twoColumnDetail('Last successful heartbeat', $link->lastContactAt() ?? 'never');

        if (($error = $link->lastError()) !== null) {
            $this->components->twoColumnDetail('Last problem', "{$error['code']} since {$error['since']}");
        }

        $this->components->info('Connected.');

        return self::SUCCESS;
    }
}
