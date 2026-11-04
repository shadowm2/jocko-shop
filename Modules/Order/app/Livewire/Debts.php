<?php

namespace Modules\Order\Livewire;

use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Livewire\Component;
use Modules\Dashboard\Exceptions\SmsException;
use Modules\Order\Services\CustomerBalanceService;
use Modules\User\Models\User;

/**
 * Who owes the shop money, and a text to remind them.
 */
class Debts extends Component
{
    public function remind(int $userId, CustomerBalanceService $balances): void
    {
        $customer = User::query()->find($userId);

        if ($customer === null) {
            return;
        }

        try {
            $balances->remind($customer, Auth::user()?->getKey());
        } catch (InvalidArgumentException|SmsException $e) {
            Flux::toast($e->getMessage(), variant: 'warning');

            return;
        }

        Flux::toast(__('order::strings.Reminder Sent'), variant: 'success');
    }

    public function remindAll(CustomerBalanceService $balances): void
    {
        $result = $balances->remindAll(Auth::user()?->getKey());

        Flux::toast(__('order::strings.Reminders Done', $result), variant: $result['failed'] > 0 ? 'warning' : 'success');
    }

    public function render(CustomerBalanceService $balances): View
    {
        $debtors = $balances->debtors();

        return view('order::livewire.debts', [
            'columns' => [
                ['label' => __('order::strings.Customer')],
                ['label' => __('order::strings.Open Orders')],
                ['label' => __('order::strings.Balance Owed')],
                ['label' => __('order::strings.Oldest Unpaid')],
                ['label' => __('order::strings.Last Reminder')],
                [],
            ],
            'rows' => $debtors,
            'totalOwed' => round((float) $debtors->sum('balance'), 2),
        ])->layout('dashboard::layouts.app')->layoutData(['title' => __('order::strings.Debts')]);
    }
}
