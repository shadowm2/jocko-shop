<?php

namespace Modules\Dashboard\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;
use Modules\Dashboard\Services\SettingService;

class JockoControlSettings extends Component
{
    public string $url = '';

    public string $token = '';

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

        \Flux\Flux::toast(__('dashboard::messages.Settings Saved Successfully'), variant: 'success');
    }

    public function clearToken(SettingService $settings): void
    {
        $settings->forget('jocko_control.token');
        \Flux\Flux::toast(__('dashboard::messages.Setting Cleared Successfully'), variant: 'success');
    }

    public function render(SettingService $settings): View
    {
        return view('dashboard::livewire.jocko-control-settings', [
            'tokenIsSet' => filled($settings->all()['jocko_control.token'] ?? null) || filled(config('control.token')),
        ])->layout('dashboard::layouts.app')->layoutData(['title' => __('dashboard::strings.Jocko Control')]);
    }
}
