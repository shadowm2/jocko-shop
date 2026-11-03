<?php

namespace Modules\Order\Livewire;

use Flux\Flux;
use Illuminate\View\View;
use Livewire\Component;
use Modules\Order\Livewire\Concerns\ManagesOrderItems;
use Modules\Order\Models\Order;
use Modules\User\Services\UserCarService;
use Modules\User\Services\UserService;

class OrderEdit extends Component
{
    use ManagesOrderItems;

    public Order $order;

    public function mount(Order $order): void
    {
        $this->order = $order;
        $this->form->setOrder($order);
    }

    public function save(): void
    {
        $this->form->save();

        Flux::toast(__('order::messages.Order Updated Successfully'), variant: 'success');
        $this->redirectRoute('orders.index', navigate: true);
    }

    public function render(
        UserCarService $userCarService,
        UserService $userService
    ): View {
        $users = $userService->getUsers(paginate: false);
        $userCars = $userCarService->getUserCars($this->form->user_id);
        $itemCatalog = $this->form->selectedCatalog();

        return view('order::livewire.order-edit', [
            ...compact('userCars', 'users', 'itemCatalog'),
            // What is saved, not what is being typed: the order's lines and payments as they stand.
            'total' => (float) $this->order->items()->sum('total'),
            'paid' => (float) $this->order->payments()->sum('amount'),
        ])
            ->layout('dashboard::layouts.app')
            ->layoutData([
                'title' => __('order::strings.Edit Order'),
            ]);
    }
}
