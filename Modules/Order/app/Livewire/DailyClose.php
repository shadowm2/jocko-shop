<?php

namespace Modules\Order\Livewire;

use App\Helpers\Utils;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Modules\Order\Models\CashClose;
use Modules\Order\Services\CashCloseService;
use Morilog\Jalali\Jalalian;

/**
 * Close the till for a day: what came in per method, the cash counted, and the difference.
 */
class DailyClose extends Component
{
    /** The day, Jalali as typed; today when it is empty or not a date. */
    public string $date = '';

    public string $date_gregorian = '';

    public string $counted = '';

    public ?string $note = null;

    public function mount(): void
    {
        $this->date = Utils::pDigits(Jalalian::now()->format('Y/m/d')) ?? '';
        $this->date_gregorian = now()->format('Y/m/d');
        $this->loadClose();
    }

    public function updatedDate(): void
    {
        $this->loadClose();
    }

    /** Show the day's saved close in the form, or an empty form for a day not closed yet. */
    private function loadClose(): void
    {
        $close = CashClose::query()->whereDate('business_date', $this->day()->toDateString())->first();

        $this->counted = $close === null ? '' : Utils::formatQuantity($close->counted_cash, 2, '');
        $this->note = $close?->note;
    }

    public function day(): CarbonImmutable
    {
        $date = Utils::jalaliToDate($this->date);

        return $date === null ? CarbonImmutable::today() : CarbonImmutable::parse($date)->startOfDay();
    }

    /** The counted cash as typed (Persian digits and separators allowed), or null when it is not a number. */
    private function countedAmount(): ?float
    {
        $typed = str_replace(['٫', '٬', ','], ['.', '', ''], Utils::eDigits($this->counted));

        return is_numeric($typed) ? (float) $typed : null;
    }

    public function save(CashCloseService $closes): void
    {
        $this->counted = str_replace(['٫', '٬', ','], ['.', '', ''], Utils::eDigits($this->counted));

        $this->validate([
            'counted' => ['required', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:500'],
        ], attributes: ['counted' => __('order::strings.Counted Cash')]);

        $closes->close($this->day(), (float) $this->counted, $this->note, Auth::user()?->getKey());

        Flux::toast(__('order::strings.Day Closed'), variant: 'success');
    }

    public function render(CashCloseService $closes): View
    {
        $day = $this->day();
        $totals = $closes->totalsFor($day);
        $saved = CashClose::query()->whereDate('business_date', $day->toDateString())->first();
        $counted = $this->countedAmount();

        return view('order::livewire.daily-close', [
            'totals' => $totals,
            'saved' => $saved,
            // The day's payments changed after it was closed (a late or backdated payment).
            'changedSinceClose' => $saved !== null && round($saved->expected_cash, 2) !== $totals['cash'],
            'difference' => $counted === null ? null : round($counted - $totals['cash'], 2),
            'recent' => CashClose::query()->latest('business_date')->limit(30)->get(),
        ])->layout('dashboard::layouts.app')->layoutData(['title' => __('order::strings.Daily Close')]);
    }
}
