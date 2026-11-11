<?php

namespace Modules\User\Livewire;

use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Modules\User\Livewire\Forms\RoleForm;

class RoleCreate extends Component
{
    public RoleForm $form;

    public function save(): void
    {
        $this->form->save();
        Flux::toast(__('user::messages.Role created successfully'), variant: 'success');
        $this->redirectRoute('roles.index', navigate: true);
    }

    public function render(): View
    {
        return view('user::livewire.role-add')
            ->layout('dashboard::layouts.app')
            ->layoutData(['title' => __('user::strings.Add Role')]);
    }
}
