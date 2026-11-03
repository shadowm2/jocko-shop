<?php

namespace Modules\Dashboard\Livewire;

use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Modules\Dashboard\Services\SettingService;
use Modules\Inventory\Services\WarehouseService;

/**
 * Whether the shop works with several warehouses, and the one to use when it
 * does not (no warehouse pickers, no transfers, no warehouses menu).
 */
class WarehouseSettings extends Component
{
    public bool $multiple = false;

    public string $default = '';

    public function mount(WarehouseService $warehouses): void
    {
        $this->multiple = $warehouses->isMultiple();
        $default = $warehouses->defaultWarehouse();
        $this->default = $default === null ? '' : (string) $default->id;
    }

    public function save(SettingService $settings, WarehouseService $warehouses): void
    {
        $this->validate([
            'multiple' => ['boolean'],
            // With one warehouse in use, every record needs to know which one.
            'default' => [Rule::requiredIf(! $this->multiple), 'nullable', 'integer', 'exists:warehouses,id'],
        ]);

        $settings->set(WarehouseService::SETTING_MULTIPLE, $this->multiple ? '1' : '0');
        $settings->set(WarehouseService::SETTING_DEFAULT, $this->default === '' ? null : $this->default);

        Flux::toast(__('dashboard::messages.Settings Saved Successfully'), variant: 'success');
    }

    /** @return array<string, string> */
    public function validationAttributes(): array
    {
        return ['default' => __('dashboard::strings.Default Warehouse')];
    }

    public function render(WarehouseService $warehouses): View
    {
        return view('dashboard::livewire.warehouse-settings', [
            'warehouseOptions' => $warehouses->getWarehouses(paginate: false),
        ])->layout('dashboard::layouts.app')->layoutData(['title' => __('dashboard::strings.Warehouse Settings')]);
    }
}
