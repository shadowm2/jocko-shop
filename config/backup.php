<?php

return [
    /*
    | Where backups are written (`php artisan backup:run`, scheduled daily). Outside
    | public/, so they cannot be fetched from the web.
    */
    'directory' => env('BACKUP_DIRECTORY', storage_path('app/private/backups')),

    // A second copy of every backup, e.g. a USB drive or network folder. Blank = none.
    'copy_to' => env('BACKUP_COPY_PATH'),

    // How many backups to keep (the newest). Older ones are deleted after a new one succeeds.
    'keep' => (int) env('BACKUP_KEEP', 14),

    /*
    | Backups are encrypted. With no passphrase the key comes from APP_KEY, which means
    | a backup can only be read by an install that has the same APP_KEY: keep it safe.
    | A passphrase lets you restore on a machine with another APP_KEY; keep that safe too.
    */
    'passphrase' => env('BACKUP_PASSPHRASE'),

    // Local time the scheduler takes the daily backup.
    'at' => env('BACKUP_AT', '02:15'),

    // Not worth keeping: they rebuild themselves or only describe the past minutes.
    'exclude_tables' => ['migrations', 'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs'],
    'exclude_prefixes' => ['pulse_', 'telescope_'],
];
