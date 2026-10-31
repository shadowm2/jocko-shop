<?php

namespace Modules\User\Livewire;

use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Modules\User\Livewire\Forms\UserForm;

class UserCreate extends Component
{
    public UserForm $form;

    public function save(): void
    {
        $this->form->save();
        Flux::toast(__('user::messages.User created successfully'), variant: 'success');
        $this->redirectRoute('users.index', navigate: true);
    }

    public function render(): View
    {
        return view('user::livewire.user-add', [])
            ->layout('dashboard::layouts.app')
            ->layoutData([
                'title' => __('user::strings.Add User'),
            ]);
    }
}
