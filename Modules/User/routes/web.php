<?php

use Illuminate\Support\Facades\Route;
use Modules\User\Livewire\RoleCreate;
use Modules\User\Livewire\RoleEdit;
use Modules\User\Livewire\RoleList;
use Modules\User\Livewire\UserCar\UserCarEdit;
use Modules\User\Livewire\UserCarsList;
use Modules\User\Livewire\UserCreate;
use Modules\User\Livewire\UserEdit;
use Modules\User\Livewire\UsersList;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::prefix('roles')->as('roles.')->middleware('can:roles.manage')->group(function () {
        Route::livewire('/list', RoleList::class)->name('index');
        Route::livewire('/new', RoleCreate::class)->name('create');
        Route::livewire('/{role}/edit', RoleEdit::class)->name('edit');
    });

    Route::prefix('users')->as('users.')->group(function () {
        Route::livewire('/list', UsersList::class)->name('index');
        Route::livewire('/new', UserCreate::class)->name('create');
        Route::livewire('/{user}/edit', UserEdit::class)->name('edit');

        Route::prefix('{user}/cars')->as('cars.')->group(function () {
            Route::livewire('/list', UserCarsList::class)->name('index');
            Route::livewire('/{userCar}/edit', UserCarEdit::class)->name('edit');
        });
    });
});
