<?php

namespace Modules\Dashboard\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Dashboard\Services\Backup\BackupService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BackupDownloadController extends Controller
{
    public function __invoke(string $name, BackupService $backups): BinaryFileResponse
    {
        $path = $backups->find($name);

        abort_if($path === null, 404);

        // Encrypted, but still the whole shop: never cached and never sniffed.
        $response = response()->download($path, $name, ['X-Content-Type-Options' => 'nosniff', 'Content-Type' => 'application/octet-stream']);

        // The framework marks a download "public"; this one must never be kept by a shared cache.
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
