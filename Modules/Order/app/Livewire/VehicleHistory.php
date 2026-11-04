<?php

namespace Modules\Order\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;
use Modules\Car\Models\UserCar;
use Modules\Order\Enums\OrderStatus;
use Modules\Order\Models\Order;
use Modules\User\Models\User;

/**
 * Every visit of one customer's car: what was done, the odometer, and when it is due next.
 */
class VehicleHistory extends Component
{
    public User $user;

    public UserCar $userCar;

    public function mount(User $user, UserCar $userCar): void
    {
        abort_unless($userCar->user_id === $user->id, 404);

        $this->user = $user;
        $this->userCar = $userCar;
    }

    public function render(): View
    {
        $orders = Order::query()
            ->where('user_car_id', $this->userCar->id)
            ->with('items.item')
            ->withSum('items', 'total')
            ->latest('created_at')
            ->latest('id')
            ->get();

        $visits = $orders->where('status', '!=', OrderStatus::CANCELLED);
        $latest = $visits->first();

        return view('order::livewire.vehicle-history', [
            'orders' => $orders,
            'visits' => $visits->count(),
            'odometer' => $visits->whereNotNull('odometer')->sortByDesc('odometer')->first()?->odometer,
            'nextServiceAt' => $latest?->next_service_at,
            'spent' => (float) $visits->sum('items_sum_total'),
        ])
            ->layout('dashboard::layouts.app')
            ->layoutData(['title' => __('order::strings.Vehicle History')]);
    }
}
