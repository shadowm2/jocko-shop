<?php

namespace Modules\Control\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Modules\Control\Exceptions\ControlException;
use Modules\Control\Services\ControlAgent;
use Modules\Control\Services\ControlClient;

#[Signature('control:heartbeat')]
#[Description('Report to Jocko Control and run any commands it has queued for this device')]
class HeartbeatCommand extends Command
{
    public function handle(ControlClient $client, ControlAgent $agent): int
    {
        if (! $client->isConfigured()) {
            $this->components->info('Jocko Control is not configured (JOCKO_CONTROL_URL / JOCKO_CONTROL_TOKEN); nothing to do.');

            return self::SUCCESS;
        }

        try {
            $result = $agent->heartbeat();
        } catch (ControlException $e) {
            $this->components->warn($e->getMessage());

            // An unreachable server is normal for an offline-first shop. Only a
            // problem that needs a person (revoked token, bad URL) is a failure.
            return $e->needsAttention() ? self::FAILURE : self::SUCCESS;
        }

        $this->components->info("Heartbeat sent. {$result['pending_commands']} command(s) pending, {$result['handled']} handled.");

        return self::SUCCESS;
    }
}
