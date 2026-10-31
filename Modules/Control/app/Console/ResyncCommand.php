<?php

namespace Modules\Control\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Modules\Control\Services\ControlClient;
use Modules\Control\Services\OutboxSnapshot;

#[Signature('control:resync {entity?* : Only these entity types, e.g. order payment}')]
#[Description('Queue a fresh copy of the current data for Jocko Control (safe to repeat)')]
class ResyncCommand extends Command
{
    public function handle(ControlClient $client, OutboxSnapshot $snapshot): int
    {
        if (! $client->isConfigured()) {
            $this->components->error('Set JOCKO_CONTROL_URL and JOCKO_CONTROL_TOKEN in .env first.');

            return self::FAILURE;
        }

        $queued = $snapshot->enqueue(array_values((array) $this->argument('entity')));

        foreach ($queued as $type => $count) {
            $this->components->twoColumnDetail($type, (string) $count);
        }

        $this->components->info(array_sum($queued).' event(s) queued; `control:push` (or the scheduler) sends them.');

        return self::SUCCESS;
    }
}
