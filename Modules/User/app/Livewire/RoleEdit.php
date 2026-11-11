<?php

namespace Modules\User\Livewire;

use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Modules\User\Livewire\Forms\RoleForm;
use Spatie\Permission\Models\Role;

class RoleEdit extends Component
{
    public RoleForm $form;

    public Role $role;

    public function mount(): void
    {
        $this->form->setRole($this->role);
    }

    public function save(): void
    {
        $this->form->save();
        Flux::toast(__('user::messages.Role updated successfully'), variant: 'success');
        $this->redirectRoute('roles.index', navigate: true);
    }

    public function render(): View
    {
        return view('user::livewire.role-edit')
            ->layout('dashboard::layouts.app')
            ->layoutData(['title' => __('user::strings.Edit Role')]);
    }
}
