<?php

use Illuminate\Support\Facades\Route;
use Modules\Dashboard\Livewire\CategoryCreate;
use Modules\Dashboard\Livewire\CategoryEdit;
use Modules\Dashboard\Livewire\CategoryList;
use Modules\Dashboard\Livewire\CityCreate;
use Modules\Dashboard\Livewire\CityEdit;
use Modules\Dashboard\Livewire\CityList;
use Modules\Dashboard\Livewire\CountryCreate;
use Modules\Dashboard\Livewire\CountryEdit;
use Modules\Dashboard\Livewire\CountryList;
use Modules\Dashboard\Livewire\Overview;
use Modules\Dashboard\Livewire\ProvinceCreate;
use Modules\Dashboard\Livewire\ProvinceEdit;
use Modules\Dashboard\Livewire\ProvinceList;
use Modules\Dashboard\Livewire\Reports;
use Modules\Dashboard\Livewire\SystemSettings;
use Modules\Dashboard\Livewire\JockoControlSettings;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('/dashboard', Overview::class)->name('dashboard');
    Route::livewire('/reports', Reports::class)->name('reports.index');
    Route::livewire('/system-settings', SystemSettings::class)->name('system-settings.edit');
    Route::livewire('/settings/jocko-control', JockoControlSettings::class)->name('jocko-control-settings.edit');

    Route::prefix('countries')->as('countries.')->group(function () {
        Route::livewire('/list', CountryList::class)->name('index');
        Route::livewire('/new', CountryCreate::class)->name('create');
        Route::livewire('/{country}/edit', CountryEdit::class)->name('edit');
    });

    Route::prefix('provinces')->as('provinces.')->group(function () {
        Route::livewire('/list', ProvinceList::class)->name('index');
        Route::livewire('/new', ProvinceCreate::class)->name('create');
        Route::livewire('/{province}/edit', ProvinceEdit::class)->name('edit');
    });

    Route::prefix('cities')->as('cities.')->group(function () {
        Route::livewire('/list', CityList::class)->name('index');
        Route::livewire('/new', CityCreate::class)->name('create');
        Route::livewire('/{city}/edit', CityEdit::class)->name('edit');
    });

    Route::prefix('categories')->as('categories.')->group(function () {
        Route::livewire('/list', CategoryList::class)->name('index');
        Route::livewire('/new', CategoryCreate::class)->name('create');
        Route::livewire('/{category}/edit', CategoryEdit::class)->name('edit');
    });
});

Route::get('/401', fn () => view('errors.401'));
Route::get('/403', fn () => view('errors.403'));
Route::get('/404', fn () => view('errors.404'));
Route::get('/419', fn () => view('errors.419'));
Route::get('/429', fn () => view('errors.429'));
Route::get('/500', fn () => view('errors.500'));
Route::get('/503', fn () => view('errors.503'));

require __DIR__.'/settings.php';
