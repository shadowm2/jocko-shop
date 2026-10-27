<?php

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Control\Exceptions\ControlException;
use Modules\Control\Services\ControlClient;

const CONTROL_TOKEN = '7|jkd_super-secret-token-value';

beforeEach(function () {
    Http::preventStrayRequests();
    Cache::flush();
    config(['control.url' => 'https://control.test', 'control.token' => CONTROL_TOKEN]);
});

test('it sends the bearer token to the versioned api and never follows redirects', function () {
    Http::fake(['*' => Http::response(['data' => ['name' => 'Front desk']])]);

    expect(resolve(ControlClient::class)->me())->toBe(['name' => 'Front desk']);

    Http::assertSent(fn (Request $request) => $request->url() === 'https://control.test/api/v1/device/me'
        && $request->hasHeader('Authorization', 'Bearer '.CONTROL_TOKEN)
        && $request->hasHeader('Accept', 'application/json'));
});

test('it is not configured without both url and token', function (?string $url, ?string $token) {
    config(['control.url' => $url, 'control.token' => $token]);

    expect(resolve(ControlClient::class)->isConfigured())->toBeFalse();
    expect(fn () => resolve(ControlClient::class)->me())->toThrow(ControlException::class, 'not both set');

    Http::assertNothingSent();
})->with([[null, null], ['https://control.test', null], [null, 'x']]);

test('plain http is refused in production so the token is never sent in clear text', function () {
    config(['control.url' => 'http://control.test']);
    app()->detectEnvironment(fn () => 'production');

    try {
        resolve(ControlClient::class)->me();
        $this->fail('Expected an exception.');
    } catch (ControlException $e) {
        expect($e->errorCode)->toBe('insecure_url');
    }

    Http::assertNothingSent();
});

test('server errors become exceptions carrying the server code but never the token', function () {
    Http::fake(['*' => Http::response(['error' => ['code' => 'shop_suspended', 'message' => 'This shop is suspended.']], 403)]);

    try {
        resolve(ControlClient::class)->heartbeat([]);
        $this->fail('Expected an exception.');
    } catch (ControlException $e) {
        expect($e->errorCode)->toBe('shop_suspended')
            ->and($e->status)->toBe(403)
            ->and($e->needsAttention())->toBeFalse()
            ->and($e->getMessage())->not->toContain(CONTROL_TOKEN);
    }
});

test('a revoked token needs attention', function () {
    Http::fake(['*' => Http::response(['error' => ['code' => 'unauthenticated', 'message' => 'Authentication is required.']], 401)]);

    expect(fn () => resolve(ControlClient::class)->me())->toThrow(fn (ControlException $e) => $e->needsAttention());
});

test('network failures are reported as unreachable', function () {
    Http::fake(fn () => throw new ConnectionException('cURL error 6: could not resolve host'));

    try {
        resolve(ControlClient::class)->me();
        $this->fail('Expected an exception.');
    } catch (ControlException $e) {
        expect($e->errorCode)->toBe('unreachable')->and($e->needsAttention())->toBeFalse();
    }
});

test('an unexpected body is rejected', function () {
    Http::fake(['*' => Http::response('<html>proxy error</html>', 200)]);

    expect(fn () => resolve(ControlClient::class)->me())->toThrow(ControlException::class, 'does not understand');
});

test('the token is never written to the log, even while the server is down', function () {
    Log::spy();
    Http::fake(fn () => throw new ConnectionException('timeout'));

    $this->artisan('control:heartbeat')->assertSuccessful();

    Log::shouldHaveReceived('warning')->once()->withArgs(function (string $message, array $context = []) {
        return ! str_contains($message.json_encode($context), 'jkd_');
    });
});
