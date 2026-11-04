<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Modules\Dashboard\Livewire\Backups;
use Modules\Dashboard\Services\Backup\BackupException;
use Modules\Dashboard\Services\Backup\BackupFile;
use Modules\Dashboard\Services\Backup\BackupService;
use Modules\Inventory\Models\Brand;
use Modules\User\Models\User;

beforeEach(function () {
    deferForeignKeys();
    $this->dir = sys_get_temp_dir().'/jocko-backups-'.bin2hex(random_bytes(4));
    config(['backup.directory' => $this->dir, 'backup.copy_to' => null, 'backup.passphrase' => null, 'backup.keep' => 3]);
    Cache::flush();
    $this->service = fn () => resolve(BackupService::class);

    $this->brand = fn (string $name) => Brand::create(['slug' => str($name)->slug()->toString(), 'name' => $name]);
    $this->names = fn () => Brand::orderBy('id')->pluck('name')->all();
    /** The backup's JSON lines, decrypted: what is really inside. */
    $this->inside = function (string $path, ?string $secret = null): array {
        $gz = tempnam(sys_get_temp_dir(), 'bk');
        BackupFile::open($path, $gz, $secret ?? config('app.key'));
        $lines = array_map(fn (string $line) => json_decode($line, true), file('compress.zlib://'.$gz, FILE_IGNORE_NEW_LINES));
        File::delete($gz);

        return $lines;
    };
});

afterEach(fn () => File::deleteDirectory($this->dir));

it('takes an encrypted backup that holds the data but does not show it', function () {
    ($this->brand)('Bosch');
    ($this->brand)('Valeo');

    $backup = ($this->service)()->create();

    $raw = (string) file_get_contents($backup['path']);
    expect($backup['path'])->toStartWith($this->dir)->toEndWith('.jocko-backup')
        ->and($backup['tables'])->toBeGreaterThan(10)
        ->and($raw)->toStartWith(BackupFile::MAGIC)
        ->and($raw)->not->toContain('Bosch')->not->toContain('Valeo')->not->toContain('brands');

    $lines = ($this->inside)($backup['path']);
    expect($lines[0]['format'])->toBe(1)
        ->and($lines[0]['tables'])->toContain('brands', 'orders', 'users', 'payments', 'payment_attempts')
        ->and($lines[0]['tables'])->not->toContain('migrations', 'sessions', 'cache', 'jobs')
        ->and(collect($lines)->where('t', 'brands')->pluck('rows')->flatten(1)->pluck('name')->all())->toBe(['Bosch', 'Valeo']);
});

it('backs up only the tables of this database', function () {
    $lines = ($this->inside)(($this->service)()->create()['path']);
    $tables = $lines[0]['tables'];

    $own = DB::getDriverName() === 'sqlite'
        ? Schema::getTableListing(schemaQualified: false)
        : Schema::getTableListing(DB::getDatabaseName(), schemaQualified: false);

    expect(array_diff($tables, $own))->toBe([])->and(count($tables))->toBeLessThan(count($own));
});

it('puts everything back as it was, and keeps a safety backup of what it replaced', function () {
    ($this->brand)('Bosch');
    ($this->brand)('Valeo');
    $backup = ($this->service)()->create();

    Brand::query()->where('name', 'Bosch')->delete();
    Brand::query()->forceDelete();
    ($this->brand)('Hella');
    expect(($this->names)())->toBe(['Hella']);

    $result = ($this->service)()->restore($backup['path']);

    expect(($this->names)())->toBe(['Bosch', 'Valeo'])
        ->and($result['rows'])->toBeGreaterThan(1)
        ->and($result['safety'])->toEndWith('-pre-restore.jocko-backup')
        ->and(is_file($this->dir.'/'.$result['safety']))->toBeTrue();

    // The safety backup holds what was there before: restoring it brings Hella back.
    ($this->service)()->restore($this->dir.'/'.$result['safety']);
    expect(($this->names)())->toBe(['Hella']);
});

it('restores users, with their passwords, exactly', function () {
    $user = User::factory()->create(['mobile' => '09120000001']);
    $hash = $user->password;
    $backup = ($this->service)()->create();

    $user->update(['mobile' => '09999999999']);
    ($this->service)()->restore($backup['path']);

    $back = User::findOrFail($user->id);
    expect($back->mobile)->toBe('09120000001')->and($back->password)->toBe($hash);
});

it('refuses a wrong key or passphrase without touching the database', function () {
    ($this->brand)('Bosch');
    $backup = ($this->service)()->create();
    ($this->brand)('Later');

    config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);

    expect(fn () => ($this->service)()->restore($backup['path']))->toThrow(BackupException::class, 'wrong key');
    expect(($this->names)())->toBe(['Bosch', 'Later']);
});

it('works with a passphrase, so another machine can read it', function () {
    config(['backup.passphrase' => 'correct horse battery staple']);
    ($this->brand)('Bosch');
    $backup = ($this->service)()->create();

    config(['backup.passphrase' => null, 'app.key' => 'base64:'.base64_encode(random_bytes(32))]);
    expect(fn () => ($this->service)()->restore($backup['path']))->toThrow(BackupException::class);

    Brand::query()->forceDelete();
    ($this->service)()->restore($backup['path'], 'correct horse battery staple');
    expect(($this->names)())->toBe(['Bosch']);
});

it('refuses a backup that was changed, cut short or is not a backup at all', function () {
    ($this->brand)('Bosch');
    $backup = ($this->service)()->create();
    $bytes = (string) file_get_contents($backup['path']);
    $broken = fn (string $content) => tap($this->dir.'/jocko-20200101-000000.jocko-backup', fn (string $path) => file_put_contents($path, $content));

    $changed = $bytes;
    $changed[strlen($changed) - 40] = $changed[strlen($changed) - 40] === 'a' ? 'b' : 'a';

    expect(fn () => ($this->service)()->restore($broken($changed)))->toThrow(BackupException::class)
        ->and(fn () => ($this->service)()->restore($broken(substr($bytes, 0, (int) (strlen($bytes) / 2)))))->toThrow(BackupException::class, 'incomplete')
        ->and(fn () => ($this->service)()->restore($broken(substr($bytes, 0, -30))))->toThrow(BackupException::class)
        ->and(fn () => ($this->service)()->restore($broken($bytes.'x')))->toThrow(BackupException::class)
        ->and(fn () => ($this->service)()->restore($broken('this is not a backup')))->toThrow(BackupException::class, 'not a Jocko backup');

    expect(($this->names)())->toBe(['Bosch']);
});

it('will not move a chunk: every chunk is bound to its place', function () {
    // Enough rows to make several chunks.
    foreach (range(1, 30) as $i) {
        DB::table('brands')->insert(array_map(fn (int $n) => ['slug' => "b{$i}-{$n}", 'name' => str_repeat('x', 240).$n, 'created_at' => now(), 'updated_at' => now()], range(1, 200)));
    }
    $path = ($this->service)()->create()['path'];
    $bytes = (string) file_get_contents($path);

    // Swap the first two chunks.
    $offset = strlen(BackupFile::MAGIC);
    $lengths = [];
    $cursor = $offset;
    while ($cursor < strlen($bytes) && count($lengths) < 2) {
        $length = unpack('N', substr($bytes, $cursor, 4))[1];
        $lengths[] = [$cursor, 4 + 12 + $length + 16];
        $cursor += 4 + 12 + $length + 16;
    }

    if (count($lengths) === 2 && $lengths[1][1] > 0) {
        $swapped = substr($bytes, 0, $offset).substr($bytes, $lengths[1][0], $lengths[1][1]).substr($bytes, $lengths[0][0], $lengths[0][1]).substr($bytes, $cursor);
        file_put_contents($path, $swapped);

        expect(fn () => ($this->service)()->restore($path))->toThrow(BackupException::class);
    }
});

it('refuses a backup from a newer version of the shop', function () {
    ($this->brand)('Bosch');
    $backup = ($this->service)()->create();
    // Pretend the backup knows a migration this install has not got.
    DB::table('migrations')->orderByDesc('id')->limit(1)->delete();
    ($this->brand)('Later');

    expect(fn () => ($this->service)()->restore($backup['path']))->toThrow(BackupException::class, 'newer version')
        ->and(($this->names)())->toBe(['Bosch', 'Later']);
});

it('keeps only the newest backups and copies each one to a second place', function () {
    config(['backup.copy_to' => $this->dir.'/copy']);

    foreach (['20260101-010101', '20260102-010101', '20260103-010101', '20260104-010101', '20260105-010101'] as $stamp) {
        File::ensureDirectoryExists($this->dir);
        File::put("{$this->dir}/jocko-{$stamp}.jocko-backup", 'x');
        File::ensureDirectoryExists($this->dir.'/copy');
        File::put("{$this->dir}/copy/jocko-{$stamp}.jocko-backup", 'x');
    }

    expect(($this->service)()->prune())->toBe(2)
        ->and(array_column(($this->service)()->all(), 'name'))->toBe(['jocko-20260105-010101.jocko-backup', 'jocko-20260104-010101.jocko-backup', 'jocko-20260103-010101.jocko-backup'])
        ->and(File::exists($this->dir.'/copy/jocko-20260101-010101.jocko-backup'))->toBeFalse()
        ->and(File::exists($this->dir.'/copy/jocko-20260103-010101.jocko-backup'))->toBeTrue();

    $made = ($this->service)()->create();
    expect(File::exists($this->dir.'/copy/'.basename($made['path'])))->toBeTrue();
});

it('checks that a backup can be read without touching the database', function () {
    ($this->brand)('Bosch');
    $path = ($this->service)()->create()['path'];
    Brand::query()->forceDelete();

    $result = ($this->service)()->verify($path);
    expect($result['tables'])->toBeGreaterThan(10)->and($result['rows'])->toBeGreaterThan(0)->and($result['compatible'])->toBeTrue()
        ->and(($this->names)())->toBe([]);

    $this->artisan('backup:verify')->expectsOutputToContain('is readable')->assertSuccessful();

    config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
    expect(fn () => ($this->service)()->verify($path))->toThrow(BackupException::class);
    $this->artisan('backup:verify')->assertFailed();
});

it('only ever serves a backup by its exact name', function () {
    $path = ($this->service)()->create()['path'];

    expect(($this->service)()->find(basename($path)))->toBe($path)
        ->and(($this->service)()->find('../.env'))->toBeNull()
        ->and(($this->service)()->find('jocko-20200101-000000.jocko-backup'))->toBeNull()
        ->and(($this->service)()->find('../../etc/passwd'))->toBeNull()
        ->and(($this->service)()->find('jocko-20260101-010101.jocko-backup/../../.env'))->toBeNull();
});

it('records a failure and says so on the page', function () {
    $blocker = tempnam(sys_get_temp_dir(), 'blk');
    config(['backup.directory' => $blocker.'/nope']);

    expect(fn () => ($this->service)()->create())->toThrow(BackupException::class)->and(Cache::has(BackupService::LAST_ERROR))->toBeTrue();

    $this->actingAs(User::factory()->create());
    Livewire::test(Backups::class)->assertSee('data-backup-failed', false);
    File::delete($blocker);
});

it('takes a backup from the settings page and lets a signed in user download it', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(Backups::class)->assertSee(__('dashboard::strings.No Backup Yet'))->call('create')->assertSee('data-backup-file', false);

    $name = ($this->service)()->all()[0]['name'];
    $this->get(route('backups.download', $name))->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertHeader('X-Content-Type-Options', 'nosniff');
    $this->get(route('backups.download', 'jocko-20200101-000000.jocko-backup'))->assertNotFound();
    $this->get('/settings/backups/..%2F.env/download')->assertNotFound();

    auth()->logout();
    $this->get(route('backups.download', $name))->assertRedirect(route('login'));
});

it('runs from the command line and is scheduled every night', function () {
    ($this->brand)('Bosch');

    $this->artisan('backup:run')->expectsOutputToContain('tables')->assertSuccessful();
    $this->artisan('backup:list')->assertSuccessful();

    $name = ($this->service)()->all()[0]['name'];
    Brand::query()->forceDelete();

    $this->artisan("backup:restore {$name} --live")->expectsConfirmation('This replaces everything in the database with the backup. Continue?', 'no')->assertFailed();
    expect(($this->names)())->toBe([]);

    $this->artisan("backup:restore {$name} --live --force")->expectsOutputToContain('Restored')->assertSuccessful();
    expect(($this->names)())->toBe(['Bosch']);

    $this->artisan('backup:restore jocko-20200101-000000.jocko-backup --force --live')->assertFailed();

    $event = collect(app(Illuminate\Console\Scheduling\Schedule::class)->events())->first(fn ($event) => str_contains($event->command ?? '', 'backup:run'));
    expect($event)->not->toBeNull()->and($event->expression)->toBe('15 2 * * *');
});
