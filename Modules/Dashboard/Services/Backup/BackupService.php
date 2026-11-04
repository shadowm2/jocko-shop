<?php

namespace Modules\Dashboard\Services\Backup;

use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Backs up the shop's database to one encrypted file and puts it back.
 *
 * It is done in PHP, table by table, so it needs no mysqldump and works the same
 * on MySQL and SQLite. A restore replaces the rows of every table that is in the
 * backup, all in one transaction (so a failure leaves the shop as it was); the
 * structure is left to the migrations, and a backup from a newer version than the
 * installed one is refused.
 */
#[Singleton]
class BackupService
{
    public const FORMAT = 1;

    private const ROWS_PER_LINE = 200;

    public const LAST_OK = 'backup.last_ok';

    public const LAST_ERROR = 'backup.last_error';

    /**
     * Take a backup now.
     *
     * @return array{path: string, size: int, tables: int, rows: int}
     *
     * @throws BackupException
     */
    public function create(string $suffix = ''): array
    {
        $directory = (string) config('backup.directory');
        $path = $directory.'/jocko-'.now()->format('Ymd-His').$suffix.'.jocko-backup';
        $plain = tempnam(sys_get_temp_dir(), 'jb');
        $gz = tempnam(sys_get_temp_dir(), 'jb');

        try {
            File::ensureDirectoryExists($directory, 0700);
            $counts = $this->dump($plain);
            $this->compress($plain, $gz);
            BackupFile::seal($gz, $path, $this->secret());
        } catch (Throwable $e) {
            File::delete($path);
            Cache::forever(self::LAST_ERROR, ['at' => now()->toIso8601String(), 'message' => $e->getMessage()]);

            throw $e instanceof BackupException ? $e : new BackupException('The backup failed: '.$e->getMessage(), previous: $e);
        } finally {
            File::delete([$plain, $gz]);
        }

        $copy = config('backup.copy_to');

        if (is_string($copy) && $copy !== '') {
            File::ensureDirectoryExists($copy);
            File::copy($path, rtrim($copy, '/').'/'.basename($path));
        }

        Cache::forever(self::LAST_OK, ['at' => now()->toIso8601String(), 'file' => basename($path), 'size' => filesize($path)]);
        Cache::forget(self::LAST_ERROR);

        return ['path' => $path, 'size' => (int) filesize($path), ...$counts];
    }

    /**
     * Delete all but the newest $keep backups (and their copies).
     */
    public function prune(?int $keep = null): int
    {
        $keep ??= (int) config('backup.keep');
        $removed = 0;

        foreach (array_slice($this->all(), max(1, $keep)) as $file) {
            File::delete($file['path']);
            $copy = config('backup.copy_to');

            if (is_string($copy) && $copy !== '') {
                File::delete(rtrim($copy, '/').'/'.$file['name']);
            }

            $removed++;
        }

        return $removed;
    }

    /**
     * The backups on disk, newest first.
     *
     * @return list<array{name: string, path: string, size: int, at: int}>
     */
    public function all(): array
    {
        $files = [];

        foreach (File::glob(rtrim((string) config('backup.directory'), '/').'/jocko-*.jocko-backup') ?: [] as $path) {
            $files[] = ['name' => basename($path), 'path' => $path, 'size' => (int) filesize($path), 'at' => (int) filemtime($path)];
        }

        usort($files, fn (array $a, array $b): int => [$b['name']] <=> [$a['name']]);

        return $files;
    }

    /**
     * The path of a backup by file name, or null. The name comes from the browser, so
     * only the exact form of our own file names is accepted.
     */
    public function find(string $name): ?string
    {
        if (preg_match('/^jocko-\d{8}-\d{6}(-pre-restore)?\.jocko-backup$/', $name) !== 1) {
            return null;
        }

        $path = rtrim((string) config('backup.directory'), '/').'/'.$name;

        return is_file($path) ? $path : null;
    }

    /**
     * Put a backup back: a safety backup of the present state first, then every table
     * in the backup is emptied and refilled in one transaction.
     *
     * @return array{tables: int, rows: int, safety: string}
     *
     * @throws BackupException
     */
    public function restore(string $path, ?string $passphrase = null): array
    {
        $plain = tempnam(sys_get_temp_dir(), 'jb');
        $gz = tempnam(sys_get_temp_dir(), 'jb');

        try {
            // Read and verify the whole backup before touching anything.
            BackupFile::open($path, $gz, $passphrase ?? $this->secret());
            $this->decompress($gz, $plain);
            $header = $this->header($plain);
            $this->assertCompatible($header);

            $safety = basename($this->create('-pre-restore')['path']);

            return [...$this->load($plain, $header), 'safety' => $safety];
        } finally {
            File::delete([$plain, $gz]);
        }
    }

    /**
     * Prove a backup can be read, without touching the database: decrypt it, check
     * every chunk, decompress it and count what is inside.
     *
     * @return array{created_at: string, tables: int, rows: int, compatible: bool}
     *
     * @throws BackupException
     */
    public function verify(string $path, ?string $passphrase = null): array
    {
        $plain = tempnam(sys_get_temp_dir(), 'jb');
        $gz = tempnam(sys_get_temp_dir(), 'jb');

        try {
            BackupFile::open($path, $gz, $passphrase ?? $this->secret());
            $this->decompress($gz, $plain);
            $header = $this->header($plain);

            $rows = 0;
            $in = $this->open($plain);
            fgets($in);

            while (($line = fgets($in)) !== false) {
                $data = json_decode($line, true);

                if (! is_array($data) || ! isset($data['t'], $data['rows'])) {
                    throw new BackupException('The backup is damaged.');
                }

                $rows += count($data['rows']);
            }

            fclose($in);

            return [
                'created_at' => (string) ($header['created_at'] ?? ''),
                'tables' => count($header['tables']),
                'rows' => $rows,
                'compatible' => ($header['format'] ?? null) === self::FORMAT && array_diff($header['migrations'] ?? [], $this->migrationNames()) === [],
            ];
        } finally {
            File::delete([$plain, $gz]);
        }
    }

    /**
     * @return array{tables: int, rows: int}
     */
    private function dump(string $plainPath): array
    {
        $out = fopen($plainPath, 'wb');

        if ($out === false) {
            throw new BackupException('The backup could not be written.');
        }

        $tables = $this->tables();
        $rows = 0;

        try {
            $this->line($out, [
                'format' => self::FORMAT,
                'created_at' => now()->toIso8601String(),
                'app' => (string) config('app.name'),
                'driver' => DB::getDriverName(),
                'migrations' => $this->migrationNames(),
                'tables' => $tables,
            ]);

            // One consistent view, even while the shop is being used.
            DB::transaction(function () use ($out, $tables, &$rows): void {
                foreach ($tables as $table) {
                    $buffer = [];

                    foreach (DB::table($table)->cursor() as $row) {
                        $buffer[] = (array) $row;
                        $rows++;

                        if (count($buffer) >= self::ROWS_PER_LINE) {
                            $this->line($out, ['t' => $table, 'rows' => $buffer]);
                            $buffer = [];
                        }
                    }

                    if ($buffer !== []) {
                        $this->line($out, ['t' => $table, 'rows' => $buffer]);
                    }
                }
            });
        } finally {
            fclose($out);
        }

        return ['tables' => count($tables), 'rows' => $rows];
    }

    /**
     * @param  array<string, mixed>  $header
     * @return array{tables: int, rows: int}
     */
    private function load(string $plainPath, array $header): array
    {
        $existing = $this->tables();
        $inBackup = array_values(array_intersect($header['tables'], $existing));
        $columns = [];
        $rows = 0;

        $this->withoutForeignKeyChecks(function () use ($plainPath, $inBackup, &$columns, &$rows): void {
            DB::transaction(function () use ($plainPath, $inBackup, &$columns, &$rows): void {
                foreach ($inBackup as $table) {
                    DB::table($table)->delete();
                    $columns[$table] = Schema::getColumnListing($table);
                }

                $in = $this->open($plainPath);
                fgets($in);

                while (($line = fgets($in)) !== false) {
                    $data = json_decode($line, true);
                    $table = $data['t'] ?? null;

                    if (! is_string($table) || ! isset($columns[$table])) {
                        continue;
                    }

                    // A column that no longer exists is dropped; the rest is kept as it was.
                    $batch = array_map(fn (array $row): array => array_intersect_key($row, array_flip($columns[$table])), $data['rows']);

                    foreach (array_chunk($batch, 100) as $chunk) {
                        DB::table($table)->insert($chunk);
                    }

                    $rows += count($batch);
                }

                fclose($in);
            });
        });

        return ['tables' => count($inBackup), 'rows' => $rows];
    }

    /**
     * @param  callable(): void  $work
     */
    private function withoutForeignKeyChecks(callable $work): void
    {
        if (DB::getDriverName() === 'sqlite') {
            // Inside the transaction SQLite can only postpone the check to its end, which is all a full restore needs.
            DB::transaction(function () use ($work): void {
                DB::statement('PRAGMA defer_foreign_keys = ON');
                $work();
            });

            return;
        }

        Schema::disableForeignKeyConstraints();

        try {
            $work();
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    /**
     * @param  array<string, mixed>  $header
     *
     * @throws BackupException
     */
    private function assertCompatible(array $header): void
    {
        if (($header['format'] ?? null) !== self::FORMAT) {
            throw new BackupException('This backup was made by another version of the backup tool.');
        }

        $unknown = array_diff($header['migrations'] ?? [], $this->migrationNames());

        if ($unknown !== []) {
            throw new BackupException('This backup is from a newer version of the shop. Update the shop first, then restore.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function header(string $plainPath): array
    {
        $in = $this->open($plainPath);
        $header = json_decode((string) fgets($in), true);
        fclose($in);

        if (! is_array($header) || ! isset($header['tables'])) {
            throw new BackupException('The backup is damaged.');
        }

        return $header;
    }

    /**
     * Every table of this database that is worth backing up.
     *
     * @return list<string>
     */
    private function tables(): array
    {
        // On MySQL an unqualified listing covers every database on the server.
        $all = DB::getDriverName() === 'sqlite'
            ? Schema::getTableListing(schemaQualified: false)
            : Schema::getTableListing(DB::getDatabaseName(), schemaQualified: false);

        $keep = array_filter($all, function (string $table): bool {
            foreach ((array) config('backup.exclude_prefixes') as $prefix) {
                if (str_starts_with($table, $prefix)) {
                    return false;
                }
            }

            return ! in_array($table, (array) config('backup.exclude_tables'), true);
        });

        sort($keep);

        return $keep;
    }

    /**
     * @return list<string>
     */
    private function migrationNames(): array
    {
        return Schema::hasTable('migrations') ? array_values(DB::table('migrations')->orderBy('id')->pluck('migration')->map(fn (mixed $name): string => (string) $name)->all()) : [];
    }

    /**
     * @param  resource  $out
     * @param  array<string, mixed>  $data
     */
    private function line($out, array $data): void
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_INVALID_UTF8_SUBSTITUTE);

        if ($json === false) {
            throw new BackupException('A row could not be written to the backup.');
        }

        fwrite($out, $json."\n");
    }

    private function compress(string $from, string $to): void
    {
        $in = $this->open($from);
        $out = gzopen($to, 'wb6');

        if ($out === false) {
            throw new BackupException('The backup could not be compressed.');
        }

        while (! feof($in)) {
            gzwrite($out, (string) fread($in, 1048576));
        }

        fclose($in);
        gzclose($out);
    }

    private function decompress(string $from, string $to): void
    {
        $in = gzopen($from, 'rb');
        $out = $this->open($to, 'wb');

        if ($in === false) {
            throw new BackupException('The backup could not be read.');
        }

        while (! gzeof($in)) {
            fwrite($out, (string) gzread($in, 1048576));
        }

        gzclose($in);
        fclose($out);
    }

    /**
     * @return resource
     *
     * @throws BackupException
     */
    private function open(string $path, string $mode = 'rb')
    {
        return fopen($path, $mode) ?: throw new BackupException('A backup file could not be opened.');
    }

    private function secret(): string
    {
        $passphrase = config('backup.passphrase');

        return is_string($passphrase) && $passphrase !== '' ? $passphrase : (string) config('app.key');
    }
}
