<?php

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;
use Modules\Order\Http\Controllers\GatewayReturnController;
use Modules\Order\Livewire\OrderCreate;
use Modules\Order\Livewire\OrderEdit;
use Modules\Order\Livewire\OrderInvoice;
use Modules\Order\Livewire\OrderPay;
use Modules\Order\Livewire\OrdersList;
use Modules\Order\Livewire\PaymentCreate;
use Modules\Order\Livewire\PaymentEdit;
use Modules\Order\Livewire\PaymentsList;

// The customer's browser comes back here from the gateway, signed in to nothing. Some
// gateways POST, so no CSRF token; what it carries is never trusted anyway.
Route::match(['get', 'post'], 'payments/online/{attempt}/return', GatewayReturnController::class)
    ->whereUuid('attempt')
    ->middleware('throttle:60,1')
    ->withoutMiddleware(ValidateCsrfToken::class)
    ->name('payments.online.return');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::prefix('orders')->as('orders.')->group(function () {
        Route::livewire('/list', OrdersList::class)->name('index');
        Route::livewire('/new', OrderCreate::class)->name('create');
        Route::livewire('/{order}/edit', OrderEdit::class)->name('edit');
        Route::livewire('/{order}/invoice', OrderInvoice::class)->name('invoice');
        Route::livewire('/{order}/pay', OrderPay::class)->name('pay');
    });

    Route::prefix('payments')->as('payments.')->group(function () {
        Route::livewire('/list', PaymentsList::class)->name('index');
        Route::livewire('/new', PaymentCreate::class)->name('create');
        Route::livewire('/{payment}/edit', PaymentEdit::class)->name('edit');
    });
});
