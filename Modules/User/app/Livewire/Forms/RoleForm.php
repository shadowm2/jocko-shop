<?php

namespace Modules\User\Livewire\Forms;

use Illuminate\Validation\Rule;
use Livewire\Form;
use Modules\User\Enums\UserPermission;
use Modules\User\Enums\UserRole;
use Spatie\Permission\Models\Role;

class RoleForm extends Form
{
    public ?Role $role = null;

    public string $name = '';

    /** @var list<string> */
    public array $permissions = [];

    /**
     * @return array<string, mixed>
     */
    public function getRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:60', Rule::unique('roles', 'name')->where('guard_name', 'web')->ignore($this->role?->id)],
            'permissions' => ['array'],
            'permissions.*' => [Rule::in(UserPermission::values())],
        ];
    }

    public function setRole(Role $role): void
    {
        $this->role = $role;
        $this->name = $role->name;
        $this->permissions = array_values(array_map('strval', $role->permissions->pluck('name')->all()));
    }

    /** The manager keeps every permission and its name, so nobody can lock the shop out. */
    public function isManager(): bool
    {
        return $this->role?->name === UserRole::Manager->value;
    }

    public function isBuiltIn(): bool
    {
        return $this->role !== null && in_array($this->role->name, array_column(UserRole::cases(), 'value'), true);
    }

    public function save(): void
    {
        $data = $this->validate();

        $role = $this->role ?? new Role(['guard_name' => 'web']);

        if (! $this->isBuiltIn()) {
            $role->name = $data['name'];
        }

        $role->save();
        $role->syncPermissions($this->isManager() ? UserPermission::values() : $data['permissions']);
    }
}
