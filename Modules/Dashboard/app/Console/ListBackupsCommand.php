<?php

namespace Modules\Dashboard\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Modules\Dashboard\Services\Backup\BackupService;

#[Signature('backup:list')]
#[Description('List the backups on disk, newest first')]
class ListBackupsCommand extends Command
{
    public function handle(BackupService $backups): int
    {
        $rows = array_map(fn (array $file): array => [$file['name'], number_format($file['size'] / 1024).' KB', date('Y-m-d H:i', $file['at'])], $backups->all());

        $rows === [] ? $this->components->warn('No backups yet. Run: php artisan backup:run') : $this->table(['File', 'Size', 'Taken'], $rows);

        return self::SUCCESS;
    }
}
