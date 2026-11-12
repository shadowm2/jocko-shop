<?php

namespace Modules\Order\Livewire;

use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Dashboard\Exceptions\SmsException;
use Modules\Dashboard\Facades\Sms;
use Modules\Order\Enums\QuoteStatus;
use Modules\Order\Models\Quote;
use Modules\Order\Services\QuoteService;
use Morilog\Jalali\Jalalian;

/**
 * The quotes given to customers: answer them, text them, print them, or open one in the
 * order form to change it or turn it into an order.
 */
class Quotes extends Component
{
    use WithPagination;

    public string $search = '';

    public string $status = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function setStatus(int $id, string $status, QuoteService $quotes): void
    {
        $new = QuoteStatus::tryFrom($status);

        if ($new !== null) {
            $quotes->setStatus(Quote::query()->findOrFail($id), $new);
        }
    }

    /**
     * Open the quote in the order form, where it can be changed or saved as an order.
     */
    public function openInOrderForm(int $id): void
    {
        $quote = Quote::query()->findOrFail($id);

        if ($quote->status === QuoteStatus::CONVERTED || $quote->status === QuoteStatus::REJECTED) {
            return;
        }

        session()->put('order_from_quote', $quote->id);
        $this->redirectRoute('orders.create', navigate: true);
    }

    public function sendSms(int $id, QuoteService $quotes): void
    {
        $quote = Quote::query()->with(['user', 'userCar.car'])->findOrFail($id);
        $mobile = trim((string) $quote->user->mobile);

        if ($mobile === '') {
            Flux::toast(__('order::messages.Quote Customer Has No Mobile'), variant: 'danger');

            return;
        }

        try {
            Sms::send($mobile, __('order::strings.Quote Sms', [
                'shop' => config('app.name'),
                'name' => $quote->user->fullName(),
                'number' => $quote->quote_number,
                'car' => $quote->userCar->car->name,
                'total' => \App\Helpers\Utils::pDigits(number_format($quotes->total($quote))),
                'date' => $quote->valid_until === null ? '-' : \App\Helpers\Utils::pDigits(Jalalian::fromDateTime($quote->valid_until)->format('Y/m/d')),
            ]));
        } catch (SmsException $e) {
            Flux::toast($e->getMessage(), variant: 'danger');

            return;
        }

        if ($quote->status === QuoteStatus::DRAFT) {
            $quotes->setStatus($quote, QuoteStatus::SENT);
        }

        Flux::toast(__('order::messages.Quote Sms Sent'), variant: 'success');
    }

    public function delete(int $id): void
    {
        $quote = Quote::query()->findOrFail($id);

        if ($quote->status !== QuoteStatus::CONVERTED) {
            $quote->delete();
        }
    }

    public function render(): View
    {
        $term = trim($this->search);

        $quotes = Quote::query()
            ->with(['user', 'userCar.car'])
            ->withSum('items', 'total')
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->when($term !== '', fn ($query) => $query->where(function ($inner) use ($term): void {
                $inner->where('quote_number', 'like', "%{$term}%")
                    ->orWhereHas('user', fn ($user) => $user->where('first_name', 'like', "%{$term}%")->orWhere('last_name', 'like', "%{$term}%")->orWhere('mobile', 'like', "%{$term}%"));
            }))
            ->latest('id')
            ->paginate(15);

        return view('order::livewire.quotes', ['quotes' => $quotes])
            ->layout('dashboard::layouts.app')
            ->layoutData(['title' => __('order::strings.Quotes')]);
    }
}
