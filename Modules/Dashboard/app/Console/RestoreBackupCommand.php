<?php

namespace Modules\Dashboard\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Modules\Dashboard\Services\Backup\BackupException;
use Modules\Dashboard\Services\Backup\BackupService;

#[Signature('backup:restore {file : A backup file name (see backup:list) or its path} {--force : Do not ask first} {--live : Do not put the shop in maintenance mode meanwhile} {--ask-passphrase : Ask for the passphrase the backup was made with}')]
#[Description('Replace the database contents with a backup (a safety backup of the present state is taken first)')]
class RestoreBackupCommand extends Command
{
    public function handle(BackupService $backups): int
    {
        $given = (string) $this->argument('file');
        $path = is_file($given) ? $given : $backups->find(basename($given));

        if ($path === null) {
            $this->components->error("No backup called [{$given}]. See backup:list.");

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm('This replaces everything in the database with the backup. Continue?')) {
            return self::FAILURE;
        }

        $passphrase = $this->option('ask-passphrase') ? $this->secret('Passphrase') : null;
        $maintenance = ! $this->option('live');

        if ($maintenance) {
            $this->callSilently('down');
        }

        try {
            $result = $backups->restore($path, is_string($passphrase) && $passphrase !== '' ? $passphrase : null);
        } catch (BackupException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        } finally {
            if ($maintenance) {
                $this->callSilently('up');
            }
        }

        $this->components->info("Restored {$result['tables']} tables, {$result['rows']} rows. The state before is saved as {$result['safety']}.");

        return self::SUCCESS;
    }
}
