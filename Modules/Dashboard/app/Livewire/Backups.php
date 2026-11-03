<?php

namespace Modules\Dashboard\Livewire;

use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;
use Modules\Dashboard\Services\Backup\BackupException;
use Modules\Dashboard\Services\Backup\BackupService;

class Backups extends Component
{
    public function create(BackupService $backups): void
    {
        try {
            $backups->create();
            $backups->prune();
        } catch (BackupException $e) {
            Flux::toast($e->getMessage(), variant: 'danger');

            return;
        }

        Flux::toast(__('dashboard::messages.Backup Created'), variant: 'success');
    }

    public function render(BackupService $backups): View
    {
        return view('dashboard::livewire.backups', [
            'files' => $backups->all(),
            'lastOk' => Cache::get(BackupService::LAST_OK),
            'lastError' => Cache::get(BackupService::LAST_ERROR),
            'keep' => (int) config('backup.keep'),
            'copyTo' => config('backup.copy_to'),
        ])->layout('dashboard::layouts.app')->layoutData(['title' => __('dashboard::strings.Backups')]);
    }
}
