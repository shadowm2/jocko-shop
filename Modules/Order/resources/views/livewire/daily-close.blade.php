@php
    use App\Helpers\Utils;
    use Modules\Order\Enums\PaymentMethod;
    use Morilog\Jalali\Jalalian;

    $money = fn (float $value): string => Utils::pDigits(Utils::formatQuantity($value, 0, ','));
    $date = fn ($value): string => Utils::pDigits(Jalalian::fromDateTime($value)->format('Y/m/d'));
@endphp
<div class="flex flex-col gap-6">
    <div>
        <flux:heading size="xl" level="1">{{ __('order::strings.Daily Close') }}</flux:heading>
        <flux:subheading>{{ __('order::strings.Daily Close Description') }}</flux:subheading>
    </div>

    <flux:card class="space-y-5">
        <flux:field class="max-w-xs">
            <flux:label>{{ __('order::strings.Day') }}</flux:label>
            <input
                class="persian-datepicker
                            appearance-none [:where(&)]:w-full ps-3 pe-10 block h-10 py-2 text-base sm:text-sm leading-[1.375rem]
                            rounded-lg shadow-xs border bg-white dark:bg-white/10 dark:disabled:bg-white/[7%]
                            text-zinc-700 dark:text-zinc-300 disabled:text-zinc-500 dark:disabled:text-zinc-400
                            dark:[&>option]:bg-zinc-700 dark:[&>option]:text-white disabled:shadow-none border
                            border-zinc-200 border-b-zinc-300/80 dark:border-white/10"
                wire:model.live="date"
                value="{{ $date_gregorian }}"
                autocomplete="off"
                data-close-date
            />
        </flux:field>

        <div data-close-totals>
            @foreach (PaymentMethod::cases() as $method)
                @php $row = $totals['methods'][$method->value]; @endphp
                @continue($row['count'] === 0)
                <div class="flex items-center justify-between gap-3 border-t border-zinc-100 py-2 first:border-t-0 dark:border-zinc-800" data-method="{{ $method->value }}">
                    <div>
                        <span class="font-medium">{{ $method->label() }}</span>
                        <span class="text-sm text-zinc-500">· {{ __('order::strings.Payments Count', ['count' => Utils::pDigits((string) $row['count'])]) }}</span>
                    </div>
                    <span class="tabular-nums" data-method-total="{{ $row['total'] }}">{{ $money($row['total']) }}</span>
                </div>
            @endforeach

            @if ($totals['count'] === 0)
                <flux:text class="py-4 text-center text-zinc-500" data-no-payments>{{ __('order::strings.No Payments That Day') }}</flux:text>
            @endif

            <div class="flex items-center justify-between gap-3 border-t border-zinc-300 pt-3 text-lg font-semibold dark:border-zinc-600">
                <span>{{ __('order::strings.Day Total') }}</span>
                <span class="tabular-nums" data-day-total="{{ $totals['total'] }}">{{ $money($totals['total']) }}</span>
            </div>
        </div>
    </flux:card>

    <flux:card class="space-y-4">
        <div class="flex items-center justify-between gap-3">
            <flux:heading>{{ __('order::strings.Count The Cash') }}</flux:heading>
            @if ($saved)
                <flux:badge color="green" size="sm" data-closed>{{ __('order::strings.Closed At', ['time' => $date($saved->updated_at)]) }}</flux:badge>
            @endif
        </div>

        <div class="flex items-center justify-between gap-3">
            <span>{{ __('order::strings.Expected Cash') }}</span>
            <span class="text-xl font-semibold tabular-nums" data-expected="{{ $totals['cash'] }}">{{ $money($totals['cash']) }}</span>
        </div>

        <x-dashboard::numeric-input
            wire:model.live.debounce.300ms="counted"
            error="counted"
            :label="__('order::strings.Counted Cash')"
            data-counted
        />

        @if ($difference !== null)
            <div @class([
                'flex items-center justify-between gap-3 rounded-lg px-3 py-2',
                'bg-green-50 text-green-700 dark:bg-green-900/20 dark:text-green-400' => $difference === 0.0,
                'bg-amber-50 text-amber-700 dark:bg-amber-900/20 dark:text-amber-400' => $difference > 0,
                'bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-400' => $difference < 0,
            ]) data-difference="{{ $difference }}">
                <span>{{ $difference === 0.0 ? __('order::strings.Cash Matches') : ($difference > 0 ? __('order::strings.Cash Over') : __('order::strings.Cash Short')) }}</span>
                <span class="font-semibold tabular-nums">{{ $money(abs($difference)) }}</span>
            </div>
        @endif

        @if ($changedSinceClose)
            <flux:callout variant="warning" icon="exclamation-triangle" data-changed-since-close>
                <flux:callout.text>{{ __('order::strings.Changed Since Close', ['was' => $money($saved->expected_cash)]) }}</flux:callout.text>
            </flux:callout>
        @endif

        <flux:field>
            <flux:label>{{ __('order::attributes.Description') }}</flux:label>
            <flux:textarea wire:model="note" rows="2" />
            <flux:error name="note" />
        </flux:field>

        <div>
            <flux:button variant="primary" wire:click="save" data-close-day>
                {{ $saved ? __('order::strings.Update Close') : __('order::strings.Close The Day') }}
            </flux:button>
        </div>
    </flux:card>

    <flux:card class="space-y-2" data-recent-closes>
        <flux:heading>{{ __('order::strings.Recent Closes') }}</flux:heading>
        @forelse ($recent as $close)
            <div wire:key="close-{{ $close->id }}" class="flex items-center justify-between gap-3 border-t border-zinc-100 py-2 first:border-t-0 dark:border-zinc-800" data-close="{{ $close->business_date->toDateString() }}">
                <span>{{ $date($close->business_date) }}</span>
                <span class="tabular-nums text-sm text-zinc-500">{{ $money($close->expected_cash) }} → {{ $money($close->counted_cash) }}</span>
                <span @class(['tabular-nums text-sm font-medium', 'text-green-600' => $close->difference == 0, 'text-amber-600' => $close->difference > 0, 'text-red-600' => $close->difference < 0])>
                    {{ $close->difference == 0 ? '✓' : ($close->difference > 0 ? '+' : '−').$money(abs($close->difference)) }}
                </span>
            </div>
        @empty
            <flux:text class="py-4 text-center text-zinc-500">{{ __('order::strings.No Closes Yet') }}</flux:text>
        @endforelse
    </flux:card>
</div>
