<?php

namespace Modules\Control\Services;

use Illuminate\Container\Attributes\Singleton;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Modules\Control\Exceptions\ControlException;

/**
 * The one place that talks to Jocko Control's device API (/api/v1).
 *
 * Calls are short and never retried here: the scheduler calls again a minute
 * later, and the shop must never wait on the internet. Every failure is a
 * ControlException. The bearer token is only ever put in the Authorization
 * header; it is never logged, returned or put in an exception message.
 */
#[Singleton]
class ControlClient
{
    public function isConfigured(): bool
    {
        resolve(\Modules\Dashboard\Services\SettingService::class)->applyToConfig();

        return filled(config('control.url')) && filled(config('control.token'));
    }

    /**
     * Identity and configuration of this device, as Jocko Control sees it.
     *
     * @return array<string, mixed>
     *
     * @throws ControlException
     */
    public function me(): array
    {
        return $this->data($this->send('get', 'device/me'));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed> received_at, last_sync_at, pending_commands
     *
     * @throws ControlException
     */
    public function heartbeat(array $payload): array
    {
        return $this->data($this->send('post', 'device/heartbeat', $payload));
    }

    /**
     * @return list<array<string, mixed>>
     *
     * @throws ControlException
     */
    public function commands(int $limit): array
    {
        $commands = $this->data($this->send('get', 'device/commands', ['limit' => $limit]), 'data', default: []);

        return array_values(array_filter($commands, 'is_array'));
    }

    /**
     * @param  'completed'|'failed'  $status
     * @param  array<string, mixed>|null  $result
     * @return array<string, mixed>
     *
     * @throws ControlException
     */
    public function acknowledge(string $commandId, string $status, ?array $result = null, ?string $error = null): array
    {
        return $this->data($this->send('post', "device/commands/{$commandId}/ack", array_filter([
            'status' => $status,
            'result' => $result,
            'error' => $error,
        ], fn (mixed $value) => $value !== null)));
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ControlException
     */
    private function send(string $method, string $path, array $data = []): Response
    {
        try {
            $response = $this->http()->{$method}($path, $data);
        } catch (ConnectionException $e) {
            throw ControlException::unreachable($e);
        }

        if ($response->failed()) {
            throw new ControlException(
                (string) ($response->json('error.message') ?? "Jocko Control answered HTTP {$response->status()}."),
                (string) ($response->json('error.code') ?? 'http_error'),
                $response->status(),
            );
        }

        return $response;
    }

    /**
     * @return array<array-key, mixed>
     *
     * @throws ControlException
     */
    private function data(Response $response, string $key = 'data', mixed $default = null): array
    {
        $value = $response->json($key, $default);

        if (! is_array($value)) {
            throw new ControlException('Jocko Control sent a response this version does not understand.', 'invalid_response', $response->status());
        }

        return $value;
    }

    /**
     * @throws ControlException
     */
    private function http(): PendingRequest
    {
        if (! $this->isConfigured()) {
            throw ControlException::notConfigured();
        }

        $url = rtrim((string) config('control.url'), '/');

        // The token must not cross the internet in clear text.
        if (! str_starts_with($url, 'https://') && app()->isProduction()) {
            throw ControlException::insecureUrl();
        }

        return Http::baseUrl($url.'/api/v1/')
            ->withToken((string) config('control.token'))
            ->acceptJson()
            ->asJson()
            ->withUserAgent('jocko-shop')
            ->timeout((int) config('control.timeout'))
            ->connectTimeout((int) config('control.connect_timeout'))
            // A redirect means the URL is wrong; never follow it with the token.
            ->withoutRedirecting();
    }
}
