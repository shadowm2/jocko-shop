<?php

namespace Modules\Dashboard\Livewire;

use App\Helpers\Utils;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Modules\Control\Exceptions\ControlException;
use Modules\Control\Services\ControlClient;
use Modules\Control\Services\ShopRestorer;
use Modules\Dashboard\Services\SettingService;

class JockoControlSettings extends Component
{
    public string $url = '';

    public string $token = '';

    /** @var array<string, int>|null entity type => records Control can give back; null until checked */
    public ?array $restorable = null;

    /** @var array<string, int> entity type => rows this shop already has */
    public array $existingData = [];

    public ?string $restoreError = null;

    public bool $restoreOverExisting = false;

    public function mount(SettingService $settings): void
    {
        $this->url = $settings->all()['jocko_control.url'] ?? (string) config('control.url', '');
        $this->token = $settings->all()['jocko_control.token'] ?? (string) config('control.token', '');
    }

    public function save(SettingService $settings): void
    {
        $this->validate([
            'url' => 'nullable|url|max:255',
            'token' => 'nullable|string|max:4096',
        ]);

        $settings->set('jocko_control.url', trim($this->url));
        if (filled($this->token)) {
            $settings->set('jocko_control.token', trim($this->token));
        }
        $this->token = '';

        Flux::toast(__('dashboard::messages.Settings Saved Successfully'), variant: 'success');
    }

    public function clearToken(SettingService $settings): void
    {
        $settings->forget('jocko_control.token');
        Flux::toast(__('dashboard::messages.Setting Cleared Successfully'), variant: 'success');
    }

    /**
     * Asks Control what it could give back, without changing anything here.
     */
    public function checkRestore(ControlClient $client, ShopRestorer $restorer): void
    {
        $this->restoreOverExisting = false;
        $this->askControlWhatItHas($client, $restorer);
    }

    private function askControlWhatItHas(ControlClient $client, ShopRestorer $restorer): void
    {
        $this->restorable = null;
        $this->restoreError = null;

        if (! $client->isConfigured()) {
            $this->restoreError = __('dashboard::messages.Restore Not Configured');

            return;
        }

        try {
            $available = $restorer->available();
        } catch (ControlException $e) {
            $this->restoreError = $e->status === 403 ? __('dashboard::messages.Restore Not Allowed') : $e->getMessage();

            return;
        }

        if (array_sum($available) === 0) {
            $this->restoreError = __('dashboard::messages.Restore Nothing');

            return;
        }

        $this->restorable = $available;
        $this->existingData = $restorer->existingData();
    }

    /**
     * Rebuilds this shop from Control's copy: what `php artisan control:restore` does.
     */
    public function restore(ControlClient $client, ShopRestorer $restorer): void
    {
        $this->askControlWhatItHas($client, $restorer);

        if ($this->restorable === null) {
            return;
        }

        if ($this->existingData !== [] && ! $this->restoreOverExisting) {
            $this->restoreError = __('dashboard::messages.Restore Needs Confirmation');

            return;
        }

        set_time_limit(0);

        try {
            $written = $restorer->restore();
        } catch (ControlException $e) {
            $this->restoreError = __('dashboard::messages.Restore Stopped', ['message' => $e->getMessage()]);

            return;
        }

        $this->restorable = null;
        $this->existingData = [];
        $this->restoreOverExisting = false;

        Flux::toast(__('dashboard::messages.Restore Done', ['count' => Utils::pDigits((string) array_sum($written))]), variant: 'success');
    }

    public function render(SettingService $settings): View
    {
        return view('dashboard::livewire.jocko-control-settings', [
            'tokenIsSet' => filled($settings->all()['jocko_control.token'] ?? null) || filled(config('control.token')),
        ])->layout('dashboard::layouts.app')->layoutData(['title' => __('dashboard::strings.Jocko Control')]);
    }
}
