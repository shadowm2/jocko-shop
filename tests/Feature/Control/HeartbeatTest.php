<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Control\Services\ControlLink;

beforeEach(function () {
    Http::preventStrayRequests();
    Cache::flush();
    config(['control.url' => 'https://control.test', 'control.token' => 'secret', 'control.app_version' => '9.9.9']);
});

function fakeControl(int $pending = 0, array $commands = []): void
{
    Http::fake([
        '*/device/heartbeat' => Http::response(['data' => ['received_at' => now()->toIso8601String(), 'last_sync_at' => null, 'pending_commands' => $pending]]),
        '*/ack' => Http::response(['data' => ['status' => 'completed', 'already_acknowledged' => false]]),
        '*/device/commands*' => Http::response(['data' => $commands]),
        '*/device/me' => Http::response(['data' => ['name' => 'Front desk', 'shop' => ['name' => 'Alpha'], 'server_time' => 'now', 'config' => ['heartbeat_interval_seconds' => 60]]]),
    ]);
}

test('it does nothing and stays quiet when not configured', function () {
    config(['control.url' => null, 'control.token' => null]);

    $this->artisan('control:heartbeat')->expectsOutputToContain('not configured')->assertSuccessful();

    Http::assertNothingSent();
});

test('the heartbeat reports version, schema and local health but never claims a sync', function () {
    fakeControl();

    $this->artisan('control:heartbeat')->assertSuccessful();

    Http::assertSent(function (Request $request) {
        $body = $request->data();

        return str_ends_with($request->url(), '/device/heartbeat')
            && $body['app_version'] === '9.9.9'
            && is_string($body['database_schema_version'])
            && $body['local_status']['database_ok'] === true
            && $body['local_status']['state'] === 'idle'
            && ! array_key_exists('sync', $body)
            && ! array_key_exists('pending_events', $body['local_status']);
    });
    expect(resolve(ControlLink::class)->lastContactAt())->not->toBeNull();
});

test('it is registered with the scheduler only when configured', function () {
    $this->artisan('schedule:list')->expectsOutputToContain('control:heartbeat');

    config(['control.url' => null]);
    $events = collect(app(Illuminate\Console\Scheduling\Schedule::class)->events())
        ->filter(fn ($event) => str_contains($event->command, 'control:heartbeat'));

    expect($events)->toHaveCount(1)->and($events->first()->filtersPass(app()))->toBeFalse();
});

test('pending commands are run and acknowledged', function () {
    fakeControl(1, [['id' => '11111111-1111-4111-8111-111111111111', 'type' => 'check_version', 'payload' => []]]);

    $this->artisan('control:heartbeat')->expectsOutputToContain('1 handled')->assertSuccessful();

    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/11111111-1111-4111-8111-111111111111/ack')
        && $r['status'] === 'completed'
        && $r['result']['app_version'] === '9.9.9'
        && array_key_exists('php_version', $r['result']));
});

test('commands are not fetched when none are pending', function () {
    fakeControl(0);

    $this->artisan('control:heartbeat')->assertSuccessful();

    Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '/device/commands'));
});

test('sync_now is reported as failed because nothing synchronizes yet', function () {
    fakeControl(1, [['id' => '22222222-2222-4222-8222-222222222222', 'type' => 'sync_now', 'payload' => ['full' => true]]]);

    $this->artisan('control:poll')->assertSuccessful();

    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/ack')
        && $r['status'] === 'failed'
        && str_contains($r['error'], 'not enabled'));
});

test('unknown command types are refused, never executed', function () {
    fakeControl(1, [
        ['id' => '33333333-3333-4333-8333-333333333333', 'type' => 'run_shell', 'payload' => ['cmd' => 'rm -rf /']],
        ['id' => '44444444-4444-4444-8444-444444444444', 'type' => '../../etc/passwd', 'payload' => []],
    ]);

    $this->artisan('control:poll')->assertSuccessful();

    Http::assertSentCount(3);
    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/ack') && $r['status'] === 'failed' && $r['error'] === 'Unsupported command type.');
});

test('refresh_config stores the published configuration', function () {
    fakeControl(1, [['id' => '55555555-5555-4555-8555-555555555555', 'type' => 'refresh_config', 'payload' => []]]);

    $this->artisan('control:poll')->assertSuccessful();

    expect(Cache::get('control.remote_config'))->toBe(['heartbeat_interval_seconds' => 60]);
});

test('diagnostics include recent problems from the log with credentials masked', function () {
    $log = storage_path('logs/laravel.log');
    $backup = is_file($log) ? file_get_contents($log) : null;
    $now = now(config('app.timezone'))->format('Y-m-d H:i:s');
    file_put_contents($log, implode("\n", [
        '[2000-01-01 00:00:00] testing.ERROR: ancient problem',
        "[{$now}] testing.INFO: not a problem",
        "[{$now}] testing.ERROR: Payment failed Authorization: Bearer abc.def password=hunter2 {\"x\":1}",
        '#0 /vendor/stack/frame.php(10): should be ignored',
    ])."\n");

    try {
        fakeControl(1, [['id' => '66666666-6666-4666-8666-666666666666', 'type' => 'collect_diagnostics', 'payload' => ['include_logs' => true, 'since_minutes' => 60]]]);
        $this->artisan('control:poll')->assertSuccessful();

        Http::assertSent(function (Request $r) {
            if (! str_ends_with($r->url(), '/ack')) {
                return false;
            }

            $logs = $r['result']['logs'];

            return $r['status'] === 'completed'
                && count($logs) === 1
                && $logs[0]['level'] === 'ERROR'
                && str_contains($logs[0]['message'], 'Payment failed')
                && ! str_contains($logs[0]['message'], 'abc.def')
                && ! str_contains($logs[0]['message'], 'hunter2')
                && $r['result']['database_ok'] === true;
        });
    } finally {
        $backup === null ? @unlink($log) : file_put_contents($log, $backup);
    }
});

test('diagnostics leave the logs out unless asked', function () {
    fakeControl(1, [['id' => '77777777-7777-4777-8777-777777777777', 'type' => 'collect_diagnostics', 'payload' => []]]);

    $this->artisan('control:poll')->assertSuccessful();

    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/ack') && ! array_key_exists('logs', $r['result']));
});

test('a failed acknowledgment leaves the command to be redelivered', function () {
    Http::fake([
        '*/ack' => Http::response(['error' => ['code' => 'command_expired', 'message' => 'The command has expired.']], 409),
        '*/device/commands*' => Http::response(['data' => [['id' => '88888888-8888-4888-8888-888888888888', 'type' => 'check_version', 'payload' => []]]]),
    ]);
    Log::spy();

    $this->artisan('control:poll')->expectsOutputToContain('0 command(s) handled')->assertSuccessful();

    Log::shouldHaveReceived('warning')->once();
});

test('an offline shop logs the outage once and the recovery once', function () {
    Log::spy();
    $online = false;
    Http::fake(function () use (&$online) {
        return $online
            ? Http::response(['data' => ['pending_commands' => 0]])
            : throw new Illuminate\Http\Client\ConnectionException('offline');
    });

    $this->artisan('control:heartbeat')->assertSuccessful();
    $this->artisan('control:heartbeat')->assertSuccessful();
    $this->artisan('control:heartbeat')->assertSuccessful();

    Log::shouldHaveReceived('warning')->once();

    $online = true;
    $this->artisan('control:heartbeat')->assertSuccessful();

    Log::shouldHaveReceived('info')->withArgs(fn (string $m) => str_contains($m, 'reachable again'))->once();
    expect(resolve(ControlLink::class)->lastError())->toBeNull();
});

test('a revoked token fails the command so someone notices', function () {
    Http::fake(['*' => Http::response(['error' => ['code' => 'unauthenticated', 'message' => 'Authentication is required.']], 401)]);

    $this->artisan('control:heartbeat')->assertFailed();

    expect(resolve(ControlLink::class)->lastError()['code'])->toBe('unauthenticated');
});

test('a suspended shop is an expected state, not a failure', function () {
    Http::fake(['*' => Http::response(['error' => ['code' => 'shop_suspended', 'message' => 'This shop is suspended.']], 403)]);

    $this->artisan('control:heartbeat')->assertSuccessful();
});

test('status shows the registration and hides the token', function () {
    fakeControl();

    $this->artisan('control:status')
        ->expectsOutputToContain('Alpha')
        ->expectsOutputToContain('Front desk')
        ->expectsOutputToContain('set (hidden)')
        ->assertSuccessful();
});

test('status fails clearly when not configured', function () {
    config(['control.url' => null, 'control.token' => null]);

    $this->artisan('control:status')->expectsOutputToContain('JOCKO_CONTROL_URL')->assertFailed();
});
