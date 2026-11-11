<?php

namespace Modules\User\Livewire\Forms;

use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Form;
use Modules\User\Enums\UserPermission;
use Modules\User\Enums\UserType;
use Modules\User\Models\User;
use Modules\User\Services\StaffRoles;
use Modules\User\Services\UserService;

class UserForm extends Form
{
    public ?User $user = null;

    public string $first_name;

    public string $last_name;

    public ?string $email;

    public ?string $mobile;

    public ?string $password;

    public ?string $password_confirmation;

    public ?string $role = null;

    /**
     * @return array<string, mixed>
     */
    public function getRules(): array
    {
        return [
            'first_name' => ['required', 'string'],
            'last_name' => ['required', 'string'],
            'email' => ['nullable', 'email', 'unique:Modules\User\Models\User,email'.($this->user ? (','.$this->user->id) : '')],
            'mobile' => ['nullable', 'string', 'unique:Modules\User\Models\User,mobile'.($this->user ? (','.$this->user->id) : '')],
            'password' => ['nullable', 'confirmed'],
            'role' => ['nullable', Rule::exists('roles', 'name')->where('guard_name', 'web')],
        ];
    }

    public function setUser(User $user): void
    {
        $this->user = $user;
        $this->first_name = $user->first_name;
        $this->last_name = $user->last_name;
        $this->email = $user->email;
        $this->mobile = $user->mobile;
        $this->role = $user->roles->first()?->getAttribute('name');
    }

    public function save(): void
    {
        $userService = resolve(UserService::class);
        $data = $this->validate();

        if (isset($this->user)) {
            // Update
            $role = $data['role'] ?? null;
            unset($data['role']);
            $userService->update($this->user, $data);

            if ($this->user->type === UserType::Admin && Gate::allows(UserPermission::ManageRoles->value)) {
                resolve(StaffRoles::class)->assign($this->user, $role);
            }

        } else {
            // Create
            unset($data['role']);
            $data = [
                ...$data,
                'type' => UserType::Customer->value,
            ];

            $userService->create($data);
        }
    }
}
