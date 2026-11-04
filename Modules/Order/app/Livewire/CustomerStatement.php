<?php

namespace Modules\Order\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;
use Modules\Order\Services\CustomerBalanceService;
use Modules\User\Models\User;

/**
 * A customer's orders and payments and what they still owe, to print.
 */
class CustomerStatement extends Component
{
    public User $user;

    public function mount(User $user): void
    {
        $this->user = $user;
    }

    public function render(CustomerBalanceService $balances): View
    {
        $orders = $balances->orders($this->user)->load('payments');

        return view('order::livewire.customer-statement', [
            'orders' => $orders,
            'summary' => $balances->summary($this->user),
            'payments' => $orders->flatMap->payments->sortBy('paid_at')->values(),
        ])->layout('order::components.layouts.invoice')->layoutData(['title' => __('order::strings.Statement').' '.$this->user->fullName()]);
    }
}
