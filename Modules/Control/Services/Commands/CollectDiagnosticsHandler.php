<?php

namespace Modules\Control\Services\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Modules\Control\Services\ControlLink;
use Modules\Control\Services\DeviceStatus;
use Throwable;

/**
 * Read-only health snapshot. With `include_logs` it also returns the headline
 * of recent warnings and errors from the application log: no stack traces and
 * no request data, with anything that looks like a credential masked.
 */
class CollectDiagnosticsHandler implements RemoteCommandHandler
{
    private const LOG_LINE = '/^\[(?<time>\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] \S+?\.(?<level>WARNING|ERROR|CRITICAL|ALERT|EMERGENCY): (?<message>.*)$/';

    public function __construct(
        private readonly DeviceStatus $status,
        private readonly ControlLink $link,
    ) {}

    public function handle(array $payload): array
    {
        $result = [
            'app_version' => $this->status->appVersion(),
            'database_schema_version' => $this->status->schemaVersion(),
            'database_ok' => $this->status->databaseOk(),
            'disk_free_mb' => $this->status->diskFreeMb(),
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'environment' => app()->environment(),
            'queue_connection' => config('queue.default'),
            'last_control_contact_at' => $this->link->lastContactAt(),
        ];

        if (! empty($payload['include_logs'])) {
            $result['logs'] = $this->recentProblems((int) ($payload['since_minutes'] ?? 60));
        }

        return $result;
    }

    /**
     * @return list<array{at: string, level: string, message: string}>
     */
    private function recentProblems(int $sinceMinutes): array
    {
        $file = $this->latestLogFile();

        if ($file === null) {
            return [];
        }

        $since = CarbonImmutable::now(config('app.timezone'))->subMinutes(max(1, min($sinceMinutes, 1440)));
        $problems = [];

        foreach ($this->tail($file) as $line) {
            if (! preg_match(self::LOG_LINE, $line, $match)) {
                continue;
            }

            try {
                $at = CarbonImmutable::createFromFormat('Y-m-d H:i:s', $match['time'], config('app.timezone'));
            } catch (Throwable) {
                continue;
            }

            if ($at === null || $at->lessThan($since)) {
                continue;
            }

            $problems[] = [
                'at' => $at->toIso8601String(),
                'level' => $match['level'],
                'message' => Str::limit($this->mask($match['message']), (int) config('control.diagnostics.max_log_line_length')),
            ];
        }

        return array_slice($problems, -((int) config('control.diagnostics.max_log_lines')));
    }

    private function latestLogFile(): ?string
    {
        $files = glob(storage_path('logs/*.log')) ?: [];

        usort($files, fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));

        return $files[0] ?? null;
    }

    /**
     * @return list<string> the last part of the file, split into lines
     */
    private function tail(string $file): array
    {
        $bytes = (int) config('control.diagnostics.log_tail_bytes');
        $size = (int) filesize($file);
        $handle = fopen($file, 'rb');

        if ($handle === false) {
            return [];
        }

        fseek($handle, max(0, $size - $bytes));
        $chunk = (string) stream_get_contents($handle);
        fclose($handle);

        return preg_split('/\R/', $chunk) ?: [];
    }

    private function mask(string $text): string
    {
        return (string) preg_replace(
            ['/(Bearer\s+)\S+/i', '/\b\d+\|jkd_\S+/', '/((?:password|secret|token|api[_-]?key)["\']?\s*[:=]\s*["\']?)[^\s"\',}]+/i'],
            ['$1[masked]', '[masked]', '$1[masked]'],
            $text,
        );
    }
}
