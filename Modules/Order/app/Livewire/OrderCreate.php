<?php

namespace Modules\Order\Livewire;

use Flux\Flux;
use Illuminate\View\View;
use Livewire\Component;
use Modules\Order\Livewire\Concerns\ManagesOrderItems;
use Modules\User\Services\UserCarService;
use Modules\User\Services\UserService;

class OrderCreate extends Component
{
    use ManagesOrderItems;

    public function mount(): void
    {
        $this->form->useFixedWarehouse();
    }

    public function save(): void
    {
        $this->form->save();

        Flux::toast(__('order::messages.Order Created Successfully'), variant: 'success');
        $this->redirectRoute('orders.index', navigate: true);
    }

    public function render(
        UserCarService $userCarService,
        UserService $userService
    ): View {
        $users = $userService->getUsers(paginate: false);
        $userCars = $userCarService->getUserCars($this->form->user_id);
        $itemCatalog = $this->form->selectedCatalog();

        return view('order::livewire.order-create', [
            ...compact('userCars', 'users', 'itemCatalog'),
        ])
            ->layout('dashboard::layouts.app')
            ->layoutData([
                'title' => __('order::strings.Submit Order'),
            ]);
    }
}
