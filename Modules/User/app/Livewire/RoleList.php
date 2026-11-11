<?php

namespace Modules\User\Livewire;

use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Inventory\Livewire\Concerns\InteractsWithTableFilters;
use Modules\User\Enums\UserRole;
use Spatie\Permission\Models\Role;

class RoleList extends Component
{
    use InteractsWithTableFilters;
    use WithPagination;

    /** @var array<string, mixed> */
    public array $filters = ['search' => ''];

    protected function defaultTableFilters(): array
    {
        return ['search' => ''];
    }

    public function delete(int $id): void
    {
        $role = Role::query()->withCount('users')->findOrFail($id);

        if (in_array($role->name, array_column(UserRole::cases(), 'value'), true)) {
            Flux::toast(__('user::messages.A built-in role cannot be deleted'), variant: 'danger');

            return;
        }

        if ($role->users_count > 0) {
            Flux::toast(__('user::messages.A role that staff still have cannot be deleted'), variant: 'danger');

            return;
        }

        $role->delete();
        Flux::toast(__('user::messages.Role deleted successfully'), variant: 'success');
    }

    public function render(): View
    {
        $columns = [
            ['label' => __('user::strings.Role')],
            ['label' => __('user::strings.Permissions')],
            ['label' => __('user::strings.Staff')],
            ['label' => ''],
        ];

        return view('user::livewire.roles-list', [
            'columns' => $columns,
            'rows' => Role::query()
                ->with('permissions')
                ->withCount('users')
                ->when($this->filters['search'] !== '', fn ($query) => $query->where('name', 'like', '%'.$this->filters['search'].'%'))
                ->orderBy('id')
                ->paginate(),
            'filterDefinitions' => [['name' => 'search', 'type' => 'search', 'label' => __('inventory::strings.Search'), 'placeholder' => __('user::strings.Search Roles')]],
        ])
            ->layout('dashboard::layouts.app')
            ->layoutData(['title' => __('user::strings.Roles')]);
    }
}
