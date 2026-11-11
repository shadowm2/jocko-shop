<?php

namespace Modules\User\Livewire;

use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Inventory\Livewire\Concerns\InteractsWithTableFilters;
use Modules\User\Enums\UserPermission;
use Modules\User\Enums\UserType;
use Modules\User\Models\User;
use Spatie\Permission\Models\Role;

class StaffList extends Component
{
    use InteractsWithTableFilters;
    use WithPagination;

    /** @var array<string, mixed> */
    public array $filters = ['search' => ''];

    protected function defaultTableFilters(): array
    {
        return ['search' => ''];
    }

    /** Give a staff member a role ('' for none, which keeps full access). */
    public function setRole(int $userId, string $role): void
    {
        abort_unless(Gate::allows(UserPermission::ManageRoles->value), 403);

        $user = User::query()->where('type', UserType::Admin)->findOrFail($userId);

        if ($role !== '' && ! Role::query()->where('name', $role)->where('guard_name', 'web')->exists()) {
            return;
        }

        $user->syncRoles($role === '' ? [] : [$role]);
        Flux::toast(__('user::messages.User updated successfully'), variant: 'success');
    }

    public function render(): View
    {
        $search = trim((string) $this->filters['search']);

        $staff = User::query()
            ->where('type', UserType::Admin)
            ->with('roles')
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('mobile', 'like', "%{$search}%")))
            ->orderBy('last_name')->orderBy('id')
            ->paginate();

        return view('user::livewire.staff-list', [
            'columns' => [
                ['label' => __('user::attributes.First Name')],
                ['label' => __('user::attributes.Last Name')],
                ['label' => __('user::attributes.User Mobile')],
                ['label' => __('user::strings.Role')],
                ['label' => ''],
            ],
            'rows' => $staff,
            'roles' => Role::query()->where('guard_name', 'web')->orderBy('id')->pluck('name'),
            'canAssign' => Gate::allows(UserPermission::ManageRoles->value),
            'filterDefinitions' => [['name' => 'search', 'type' => 'search', 'label' => __('inventory::strings.Search'), 'placeholder' => __('user::strings.Search Staff')]],
        ])
            ->layout('dashboard::layouts.app')
            ->layoutData(['title' => __('user::strings.Staff')]);
    }
}
