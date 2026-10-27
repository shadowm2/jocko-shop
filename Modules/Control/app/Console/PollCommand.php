<?php

namespace Modules\Control\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Modules\Control\Exceptions\ControlException;
use Modules\Control\Services\ControlAgent;
use Modules\Control\Services\ControlClient;

#[Signature('control:poll')]
#[Description('Fetch, run and acknowledge the remote commands queued for this device')]
class PollCommand extends Command
{
    public function handle(ControlClient $client, ControlAgent $agent): int
    {
        if (! $client->isConfigured()) {
            $this->components->error('Jocko Control is not configured (JOCKO_CONTROL_URL / JOCKO_CONTROL_TOKEN).');

            return self::FAILURE;
        }

        try {
            $handled = $agent->processCommands();
        } catch (ControlException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info("{$handled} command(s) handled.");

        return self::SUCCESS;
    }
}
