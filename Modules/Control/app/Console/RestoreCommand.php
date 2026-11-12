<?php

namespace Modules\Control\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Modules\Control\Exceptions\ControlException;
use Modules\Control\Services\ControlClient;
use Modules\Control\Services\ShopRestorer;

#[Signature('control:restore {--force : Restore even though this installation already has data} {--yes : Do not ask for confirmation}')]
#[Description('Rebuild a lost installation from the copy Jocko Control keeps')]
class RestoreCommand extends Command
{
    public function handle(ControlClient $client, ShopRestorer $restorer): int
    {
        if (! $client->isConfigured()) {
            $this->components->error('Set JOCKO_CONTROL_URL and JOCKO_CONTROL_TOKEN in .env first.');

            return self::FAILURE;
        }

        try {
            $available = $restorer->available();
        } catch (ControlException $e) {
            $this->components->error($e->status === 403
                ? 'Jocko Control has not allowed a restore. Ask an administrator to press "Allow restore" on this shop in Jocko Control, then run this again within 24 hours.'
                : $e->getMessage());

            return self::FAILURE;
        }

        if (array_sum($available) === 0) {
            $this->components->warn('Jocko Control has nothing to restore for this shop.');

            return self::FAILURE;
        }

        foreach ($available as $type => $count) {
            $this->components->twoColumnDetail($type, (string) $count);
        }

        $existing = $restorer->existingData();

        if ($existing !== [] && ! $this->option('force')) {
            $this->components->error('This installation already has data ('.implode(', ', array_keys($existing)).'). A restore is meant for an empty installation. Use --force to restore over it anyway.');

            return self::FAILURE;
        }

        $this->components->warn('Only business data comes back. Passwords (sign in with the SMS code), settings, roles and the picture files are not kept in Jocko Control.');

        if (! $this->option('yes') && ! $this->confirm('Restore '.array_sum($available).' record(s) now?')) {
            return self::FAILURE;
        }

        try {
            $written = $restorer->restore(available: $available);
        } catch (ControlException $e) {
            $this->components->error('The restore stopped: '.$e->getMessage().' It is safe to run it again.');

            return self::FAILURE;
        }

        $this->components->info(array_sum($written).' record(s) restored.');

        return self::SUCCESS;
    }
}
