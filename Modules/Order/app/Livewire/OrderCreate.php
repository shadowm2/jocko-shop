<?php

namespace Modules\Order\Livewire;

use Flux\Flux;
use Illuminate\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Modules\Order\Livewire\Concerns\ManagesOrderItems;
use Modules\Order\Models\Quote;
use Modules\Order\Services\QuoteService;
use Modules\User\Services\UserCarService;
use Modules\User\Services\UserService;

class OrderCreate extends Component
{
    use ManagesOrderItems;

    /** The quote this form was opened from; saving the order marks it converted. */
    #[Locked]
    public ?int $quoteId = null;

    public function mount(): void
    {
        $this->applyQuote();
        $this->form->useFixedWarehouse();
    }

    /**
     * Open from a quote (picked on the quotes page): its customer, car and lines fill the form.
     */
    private function applyQuote(): void
    {
        $id = session()->pull('order_from_quote');
        $quote = is_int($id) ? Quote::query()->find($id) : null;

        if ($quote === null || $quote->status->value === 'converted') {
            return;
        }

        $prefill = resolve(QuoteService::class)->toOrderForm($quote);

        $this->quoteId = $quote->id;
        $this->form->fillFromQuote($prefill['form']);

        if ($prefill['dropped'] > 0) {
            Flux::toast(__('order::messages.Quote Lines Dropped', ['count' => $prefill['dropped']]), variant: 'warning');
        }
    }

    public function save(): void
    {
        $order = $this->form->save();

        if ($this->quoteId !== null && ($quote = Quote::query()->find($this->quoteId)) !== null) {
            resolve(QuoteService::class)->markConverted($quote, $order);
        }

        Flux::toast(__('order::messages.Order Created Successfully'), variant: 'success');
        $this->redirectRoute('orders.index', navigate: true);
    }

    /**
     * Keep this form as a quote instead of an order: nothing is taken from stock.
     */
    public function saveQuote(): void
    {
        $data = $this->form->validatedData();
        $quote = $this->quoteId === null ? null : Quote::query()->find($this->quoteId);

        try {
            resolve(QuoteService::class)->save($data, $quote);
        } catch (InvalidArgumentException $e) {
            Flux::toast($e->getMessage(), variant: 'danger');

            return;
        }

        Flux::toast(__('order::messages.Quote Saved Successfully'), variant: 'success');
        $this->redirectRoute('quotes.index', navigate: true);
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
