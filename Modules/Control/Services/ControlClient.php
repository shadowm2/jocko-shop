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
     * Push a batch of events. The answer says, per event, whether it was
     * accepted, already known, or refused: match results by event_id.
     *
     * @param  list<array<string, mixed>>  $events
     * @return array{accepted?: list<array<string, mixed>>, already_accepted?: list<array<string, mixed>>, rejected?: list<array<string, mixed>>}
     *
     * @throws ControlException
     */
    public function pushEvents(array $events): array
    {
        return $this->data($this->send('post', 'sync/events', ['events' => $events]));
    }

    /**
     * Upload the preview of one picture (see MediaUploader). Repeating the same
     * file is harmless.
     *
     * @return 'stored'|'already_stored'
     *
     * @throws ControlException
     */
    public function pushFile(int $mediaId, string $sha256, string $mime, string $bytes, string $kind = 'preview'): string
    {
        try {
            // Not JSON: a JSON content type would survive into the multipart request.
            $response = $this->http(json: false)
                ->attach('file', $bytes, $sha256.match ($mime) {
                    'image/png' => '.png',
                    'image/webp' => '.webp',
                    default => '.jpg',
                }, ['Content-Type' => $mime])
                ->post('sync/files', ['media_id' => (string) $mediaId, 'sha256' => $sha256, 'kind' => $kind]);
        } catch (ConnectionException $e) {
            throw ControlException::unreachable($e);
        }

        $this->ensureSucceeded($response);

        return $this->data($response)['status'] === 'already_stored' ? 'already_stored' : 'stored';
    }

    /**
     * How many records of each entity Jocko Control can give back. Refused
     * (403) unless an administrator has allowed a restore.
     *
     * @return array{allowed_until?: string|null, entities?: array<string, int>}
     *
     * @throws ControlException
     */
    public function restoreSummary(): array
    {
        return $this->data($this->send('get', 'restore'));
    }

    /**
     * One page of an entity's records, after the given local id.
     *
     * @return array{records: list<array<string, mixed>>, next: int|null}
     *
     * @throws ControlException
     */
    public function restoreRecords(string $entity, int $after = 0): array
    {
        $data = $this->data($this->send('get', "restore/{$entity}", ['after' => $after]));

        return ['records' => array_values(array_filter((array) ($data['records'] ?? []), 'is_array')), 'next' => isset($data['next']) ? (int) $data['next'] : null];
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

        $this->ensureSucceeded($response);

        return $response;
    }

    /**
     * @throws ControlException
     */
    private function ensureSucceeded(Response $response): void
    {
        if ($response->failed()) {
            throw new ControlException(
                (string) ($response->json('error.message') ?? "Jocko Control answered HTTP {$response->status()}."),
                (string) ($response->json('error.code') ?? 'http_error'),
                $response->status(),
            );
        }
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
    private function http(bool $json = true): PendingRequest
    {
        if (! $this->isConfigured()) {
            throw ControlException::notConfigured();
        }

        $url = rtrim((string) config('control.url'), '/');

        // The token must not cross the internet in clear text.
        if (! str_starts_with($url, 'https://') && app()->isProduction()) {
            throw ControlException::insecureUrl();
        }

        $request = Http::baseUrl($url.'/api/v1/')
            ->withToken((string) config('control.token'))
            ->acceptJson()
            ->withUserAgent('jocko-shop')
            ->timeout((int) config('control.timeout'))
            ->connectTimeout((int) config('control.connect_timeout'))
            // A redirect means the URL is wrong; never follow it with the token.
            ->withoutRedirecting();

        return $json ? $request->asJson() : $request;
    }
}
