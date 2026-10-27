<?php

namespace Modules\Control\Services\Commands;

use Modules\Control\Services\DeviceStatus;

class CheckVersionHandler implements RemoteCommandHandler
{
    public function __construct(private readonly DeviceStatus $status) {}

    public function handle(array $payload): array
    {
        return [
            'app_version' => $this->status->appVersion(),
            'database_schema_version' => $this->status->schemaVersion(),
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
        ];
    }
}
