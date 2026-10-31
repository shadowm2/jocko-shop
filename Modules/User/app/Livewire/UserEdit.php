<?php

namespace Modules\User\Livewire;

use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Modules\User\Livewire\Forms\UserForm;
use Modules\User\Models\User;
use Modules\User\Services\UserService;

class UserEdit extends Component
{
    public UserForm $form;

    public User $user;

    public function mount(): void
    {
        $this->form->setUser($this->user);
    }

    public function save(): void
    {
        $this->form->save();
        Flux::toast(__('user::messages.User updated successfully'), variant: 'success');
        $this->redirectRoute('users.index', navigate: true);
    }

    public function render(UserService $userService): View
    {
        return view('user::livewire.users-edit', [])
            ->layout('dashboard::layouts.app')
            ->layoutData([
                'title' => __('user::strings.Edit User'),
            ]);
    }
}
