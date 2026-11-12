<?php

namespace Modules\Dashboard\Filters;

use App\Filters\BaseFilter;
use Illuminate\Database\Eloquent\Builder;
use Modules\Dashboard\Models\Country;

/** @extends BaseFilter<Country> */
class CountryFilter extends BaseFilter
{
    protected function filter(): void
    {
        $search = trim((string) ($this->filters['search'] ?? ''));
        if ($search !== '') {
            $this->builder->where(function (Builder $query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('code3', 'like', "%{$search}%")
                    ->orWhere('phone_code', 'like', "%{$search}%");
            });
        }
        $this->whereCheckboxes('is_active');
        $sortBy = $this->filters['sort_by'] ?? null;
        if (in_array($sortBy, ['name', 'code', 'is_active'], true)) {
            $this->builder->orderBy($sortBy, ($this->filters['sort_direction'] ?? '') === 'desc' ? 'desc' : 'asc')->orderBy('countries.id');
        }
    }
}
