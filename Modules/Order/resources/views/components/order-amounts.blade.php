@props(['total' => 0, 'paid' => 0, 'compact' => false, 'items' => null])
@php
    use App\Helpers\Utils;

    $money = fn ($value) => Utils::pDigits(Utils::formatQuantity($value, 2, ','));
    $remaining = round((float) $total - (float) $paid, 2);
    // Still owed: red. Settled: green. Paid more than the order is worth: amber.
    $remainingColor = $remaining > 0 ? 'text-red-600 dark:text-red-400' : ($remaining < 0 ? 'text-amber-600 dark:text-amber-400' : 'text-green-600 dark:text-green-400');
@endphp
@if ($compact)
    @php
        $percent = (float) $total > 0 ? min(100, max(0, round((float) $paid / (float) $total * 100))) : 0;
        // Settled: green. Overpaid: amber. Part paid: blue. Nothing paid: no fill, the grey track says it.
        $barColor = $remaining === 0.0 ? 'bg-green-500' : ($remaining < 0 ? 'bg-amber-500' : 'bg-sky-500');
    @endphp
    <div
        class="flex w-56 flex-col gap-1.5"
        data-amounts
    >
        <div class="flex items-baseline justify-between gap-3">
            <span
                class="text-sm font-semibold tabular-nums"
                data-total="{{ $total }}"
            >{{ $money($total) }}</span>
            @if ($items !== null)
                <span class="text-xs text-zinc-500">{{ Utils::pDigits((string) $items) }} {{ __('order::strings.Order Items') }}</span>
            @endif
        </div>

        <div
            class="h-1.5 w-full overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700"
            role="progressbar"
            aria-valuemin="0"
            aria-valuemax="100"
            aria-valuenow="{{ $percent }}"
            aria-label="{{ __('order::strings.Paid Total') }}"
        >
            <div
                class="h-full rounded-full {{ $barColor }}"
                style="width: {{ $percent }}%"
            ></div>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-0.5 text-xs tabular-nums">
            <span class="text-zinc-500">
                {{ __('order::strings.Paid Total') }}
                <span
                    class="font-medium text-zinc-700 dark:text-zinc-300"
                    data-paid="{{ $paid }}"
                >{{ $money($paid) }}</span>
            </span>
            <span class="inline-flex items-center gap-1 font-semibold {{ $remainingColor }}">
                @if ($remaining === 0.0)
                    <flux:icon.check-circle
                        variant="micro"
                        class="size-3.5"
                    />
                    <span data-remaining="{{ $remaining }}">{{ __('order::strings.Settled') }}</span>
                @else
                    {{ $remaining < 0 ? __('order::strings.Overpaid') : __('order::attributes.Balance') }}
                    <span data-remaining="{{ $remaining }}">{{ $money(abs($remaining)) }}</span>
                @endif
            </span>
        </div>
    </div>
@else
    <dl
        class="grid grid-cols-3 gap-3"
        data-amounts
    >
        <flux:card>
            <dt class="text-xs text-zinc-500">{{ __('order::attributes.Total') }}</dt>
            <dd class="text-lg font-semibold" data-total="{{ $total }}">{{ $money($total) }}</dd>
        </flux:card>
        <flux:card>
            <dt class="text-xs text-zinc-500">{{ __('order::strings.Paid Total') }}</dt>
            <dd class="text-lg font-semibold text-green-600" data-paid="{{ $paid }}">{{ $money($paid) }}</dd>
        </flux:card>
        <flux:card>
            <dt class="text-xs text-zinc-500">{{ __('order::attributes.Balance') }}</dt>
            <dd class="text-lg font-semibold {{ $remainingColor }}" data-remaining="{{ $remaining }}">{{ $money($remaining) }}</dd>
        </flux:card>
    </dl>
@endif
