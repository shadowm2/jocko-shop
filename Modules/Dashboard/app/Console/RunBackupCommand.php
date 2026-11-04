<?php

namespace Modules\Dashboard\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Modules\Dashboard\Services\Backup\BackupException;
use Modules\Dashboard\Services\Backup\BackupService;

#[Signature('backup:run {--no-prune : Keep every older backup too}')]
#[Description('Take an encrypted backup of the database (and delete the oldest beyond BACKUP_KEEP)')]
class RunBackupCommand extends Command
{
    public function handle(BackupService $backups): int
    {
        try {
            $result = $backups->create();
        } catch (BackupException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info(sprintf('%s: %d tables, %d rows, %s.', basename($result['path']), $result['tables'], $result['rows'], number_format($result['size'] / 1024).' KB'));

        if (! $this->option('no-prune') && ($removed = $backups->prune()) > 0) {
            $this->components->info("Removed {$removed} old backup(s).");
        }

        return self::SUCCESS;
    }
}
