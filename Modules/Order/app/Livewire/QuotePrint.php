<?php

namespace Modules\Order\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;
use Modules\Order\Models\Quote;

class QuotePrint extends Component
{
    public Quote $quote;

    public function mount(Quote $quote): void
    {
        $this->quote = $quote->load(['user', 'userCar.car', 'warehouse', 'items.item', 'items.unit']);
    }

    public function render(): View
    {
        return view('order::livewire.quote-print', [
            'total' => (float) $this->quote->items->sum('total'),
        ])
            ->layout('order::components.layouts.invoice')
            ->layoutData(['title' => __('order::strings.Quote').' '.$this->quote->quote_number]);
    }
}
