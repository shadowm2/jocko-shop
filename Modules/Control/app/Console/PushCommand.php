<?php

namespace Modules\Control\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Modules\Control\Exceptions\ControlException;
use Modules\Control\Services\ControlClient;
use Modules\Control\Services\OutboxPusher;

#[Signature('control:push')]
#[Description('Send the changes recorded in the outbox to Jocko Control')]
class PushCommand extends Command
{
    public function handle(ControlClient $client, OutboxPusher $pusher): int
    {
        if (! $client->isConfigured()) {
            $this->components->info('Jocko Control is not configured (JOCKO_CONTROL_URL / JOCKO_CONTROL_TOKEN); nothing to do.');

            return self::SUCCESS;
        }

        try {
            $result = $pusher->push();
        } catch (ControlException $e) {
            $this->components->warn($e->getMessage());

            // Offline is normal for this kind of shop: the events simply wait.
            return $e->needsAttention() ? self::FAILURE : self::SUCCESS;
        }

        $this->components->info("Pushed {$result['pushed']} event(s), {$result['rejected']} refused, {$result['remaining']} still waiting.");

        return self::SUCCESS;
    }
}
