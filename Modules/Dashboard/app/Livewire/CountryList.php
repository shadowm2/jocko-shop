<?php

namespace Modules\Dashboard\Livewire;

use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Dashboard\Models\Country;
use Modules\Dashboard\Services\CountryService;
use Modules\Inventory\Livewire\Concerns\InteractsWithTableFilters;
use Modules\Inventory\Livewire\Concerns\InteractsWithTableSorting;

class CountryList extends Component
{
    use InteractsWithTableFilters;
    use InteractsWithTableSorting;
    use WithPagination;

    /** @var array<string, mixed> */
    public array $filters = ['search' => '', 'is_active' => []];

    public string $sortBy = 'name';

    public string $sortDirection = 'asc';

    protected function defaultTableFilters(): array
    {
        return ['search' => '', 'is_active' => []];
    }

    /** @return array<int, array<string, mixed>> */
    private function filterDefinitions(): array
    {
        return [
            ['name' => 'search', 'type' => 'search', 'label' => __('inventory::strings.Search'), 'placeholder' => __('dashboard::strings.Search Countries')],
            ['name' => 'is_active', 'type' => 'checkboxes', 'label' => __('dashboard::attributes.Is Active'), 'options' => ['1' => __('strings.Yes'), '0' => __('strings.No')]],
        ];
    }

    /** @return array<string, string> */
    private function sortOptions(): array
    {
        return ['name' => __('dashboard::attributes.Country Name'), 'code' => __('dashboard::attributes.Country Code'), 'is_active' => __('dashboard::attributes.Is Active')];
    }

    /** @param string $countryCode */
    public function toggleIsActive($countryCode): void
    {
        $countryService = resolve(CountryService::class);
        /** @var Country $country */
        $country = $countryService->findByKey($countryCode);
        $countryService->update($country, [
            'is_active' => ! $country->is_active,
        ]);
        Flux::toast(__('dashboard::messages.Country Updated Successfully'), variant: 'success');
        $this->resetPage();
    }

    public function render(CountryService $countryService): View
    {
        $columns = [
            [
                'label' => __('dashboard::attributes.Country Name'),
            ],
            [
                'label' => __('dashboard::attributes.Country Code'),
            ],
            [
                'label' => __('dashboard::attributes.Country Code3'),
            ],
            [
                'label' => __('dashboard::attributes.Country Phone Code'),
            ],
            [
                'label' => __('dashboard::attributes.Is Active'),
            ],
            [
                'label' => '',
            ],
        ];
        $rows = $countryService->getCountries([...$this->filters, 'sort_by' => $this->sortBy, 'sort_direction' => $this->sortDirection]);

        return view('dashboard::livewire.countries-list', [
            ...compact('columns', 'rows'),
            'filterDefinitions' => $this->filterDefinitions(), 'sortOptions' => $this->sortOptions(),
        ])
            ->layout('dashboard::layouts.app')
            ->layoutData([
                'title' => __('dashboard::strings.Countries'),
            ]);
    }
}
