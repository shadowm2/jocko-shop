<?php

namespace Modules\Order\Livewire;

use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Component;
use Modules\Order\Enums\ChequeStatus;
use Modules\Order\Models\Payment;

/**
 * The cheques the shop has been given: which are still to be cashed, which are due soon
 * and which bounced.
 */
class Cheques extends Component
{
    /** A status, or "all". */
    public string $status = ChequeStatus::PENDING->value;

    /** How many days ahead counts as "due soon". */
    public const SOON_DAYS = 7;

    public function mark(int $paymentId, string $status): void
    {
        $newStatus = ChequeStatus::tryFrom($status);
        $cheque = $this->cheques()->find($paymentId);

        if ($newStatus === null || $cheque === null) {
            return;
        }

        $cheque->update(['cheque_status' => $newStatus]);

        Flux::toast(__('order::strings.Cheque Marked', ['status' => $newStatus->label()]), variant: $newStatus === ChequeStatus::BOUNCED ? 'warning' : 'success');
    }

    /**
     * @return Builder<Payment>
     */
    private function cheques(): Builder
    {
        return Payment::query()->withoutGlobalScope(Payment::NOT_BOUNCED)->whereNotNull('cheque_status');
    }

    /**
     * What is still waiting to be cashed, in total, due soon and overdue.
     *
     * @return array{pending: float, soon: float, soon_count: int, overdue: float, overdue_count: int, bounced: float}
     */
    private function summary(): array
    {
        $pending = $this->cheques()->where('cheque_status', ChequeStatus::PENDING->value)->get(['amount', 'cheque_due_at']);
        $overdue = $pending->filter(fn (Payment $cheque): bool => $cheque->cheque_due_at?->isBefore(today()) === true);
        $soon = $pending->filter(fn (Payment $cheque): bool => $cheque->cheque_due_at !== null
            && ! $cheque->cheque_due_at->isBefore(today())
            && $cheque->cheque_due_at->lte(today()->addDays(self::SOON_DAYS)));

        return [
            'pending' => round((float) $pending->sum('amount'), 2),
            'soon' => round((float) $soon->sum('amount'), 2),
            'soon_count' => $soon->count(),
            'overdue' => round((float) $overdue->sum('amount'), 2),
            'overdue_count' => $overdue->count(),
            'bounced' => round((float) $this->cheques()->where('cheque_status', ChequeStatus::BOUNCED->value)->sum('amount'), 2),
        ];
    }

    /**
     * @return Collection<int, Payment>
     */
    private function rows(): Collection
    {
        return $this->cheques()
            ->when(ChequeStatus::tryFrom($this->status), fn (Builder $query, ChequeStatus $status) => $query->where('cheque_status', $status->value))
            ->with('order.user')
            ->orderBy('cheque_due_at')
            ->orderBy('id')
            ->get();
    }

    public function render(): View
    {
        return view('order::livewire.cheques', [
            'rows' => $this->rows(),
            'summary' => $this->summary(),
            'statuses' => ChequeStatus::cases(),
            'columns' => [
                ['label' => __('order::attributes.Cheque Number')],
                ['label' => __('order::strings.Customer')],
                ['label' => __('order::attributes.Order Number')],
                ['label' => __('order::attributes.Amount')],
                ['label' => __('order::attributes.Cheque Due At')],
                ['label' => __('order::attributes.Cheque Status')],
                [],
            ],
        ])->layout('dashboard::layouts.app')->layoutData(['title' => __('order::strings.Cheques')]);
    }
}
