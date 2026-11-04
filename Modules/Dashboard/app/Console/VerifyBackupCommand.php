<?php

namespace Modules\Dashboard\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Modules\Dashboard\Services\Backup\BackupException;
use Modules\Dashboard\Services\Backup\BackupService;

#[Signature('backup:verify {file? : A backup file name or path (default: the newest)} {--ask-passphrase : Ask for the passphrase the backup was made with}')]
#[Description('Check that a backup can be read and say what is in it, without touching the database')]
class VerifyBackupCommand extends Command
{
    public function handle(BackupService $backups): int
    {
        $given = $this->argument('file');
        $path = is_string($given) ? (is_file($given) ? $given : $backups->find(basename($given))) : ($backups->all()[0]['path'] ?? null);

        if ($path === null) {
            $this->components->error('No such backup. See backup:list.');

            return self::FAILURE;
        }

        $passphrase = $this->option('ask-passphrase') ? $this->secret('Passphrase') : null;

        try {
            $result = $backups->verify($path, is_string($passphrase) && $passphrase !== '' ? $passphrase : null);
        } catch (BackupException $e) {
            $this->components->error(basename($path).': '.$e->getMessage());

            return self::FAILURE;
        }

        $this->components->info(sprintf('%s is readable: %d tables, %d rows, taken %s.', basename($path), $result['tables'], $result['rows'], $result['created_at']));

        if (! $result['compatible']) {
            $this->components->warn('It is from a newer version of the shop than this one: update before restoring it.');
        }

        return self::SUCCESS;
    }
}
