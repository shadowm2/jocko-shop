@php
    use App\Helpers\Utils;
    use Morilog\Jalali\Jalalian;

    $money = fn ($value) => Utils::pDigits(Utils::formatQuantity($value, 0, ','));
    $date = fn ($value) => Utils::pDigits(Jalalian::fromDateTime($value)->format('Y/m/d'));
    $overdue = $nextServiceAt !== null && $nextServiceAt->isBefore(today());
@endphp
<div class="space-y-6">
    <div>
        <flux:heading size="xl" level="1">{{ __('order::strings.Vehicle History') }}</flux:heading>
        <flux:subheading>
            {{ $userCar->car->company?->name }} {{ $userCar->car->name }}
            · {{ $user->fullName() }}
        </flux:subheading>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-4" data-vehicle-summary>
        <flux:card>
            <div class="text-sm text-zinc-500">{{ __('order::strings.Visits') }}</div>
            <div class="text-2xl font-semibold tabular-nums" data-visits="{{ $visits }}">{{ Utils::pDigits((string) $visits) }}</div>
        </flux:card>
        <flux:card>
            <div class="text-sm text-zinc-500">{{ __('order::attributes.Odometer') }}</div>
            <div class="text-2xl font-semibold tabular-nums" data-odometer="{{ $odometer }}">
                {{ $odometer === null ? '—' : Utils::pDigits(number_format($odometer)).' '.__('order::strings.Km') }}
            </div>
        </flux:card>
        <flux:card>
            <div class="text-sm text-zinc-500">{{ __('order::attributes.Next Service') }}</div>
            <div @class(['text-2xl font-semibold tabular-nums', 'text-red-600' => $overdue]) data-next-service="{{ $nextServiceAt?->toDateString() }}">
                {{ $nextServiceAt === null ? '—' : $date($nextServiceAt) }}
            </div>
            @if ($overdue)
                <div class="text-xs text-red-600">{{ __('order::strings.Overdue') }}</div>
            @endif
        </flux:card>
        <flux:card>
            <div class="text-sm text-zinc-500">{{ __('order::strings.Spent Here') }}</div>
            <div class="text-2xl font-semibold tabular-nums">{{ $money($spent) }}</div>
        </flux:card>
    </div>

    <flux:card>
        <div class="space-y-4" data-vehicle-orders>
            @forelse ($orders as $order)
                <div wire:key="visit-{{ $order->id }}" class="flex flex-wrap items-start justify-between gap-3 border-t border-zinc-100 pt-4 first:border-t-0 first:pt-0 dark:border-zinc-800" data-visit="{{ $order->order_number }}">
                    <div class="min-w-0 space-y-1">
                        <div class="flex items-center gap-2">
                            <a class="font-medium underline" dir="ltr" href="{{ route('orders.edit', $order) }}" wire:navigate>{{ $order->order_number }}</a>
                            <flux:badge size="sm" :color="$order->status->color()">{{ $order->status->label() }}</flux:badge>
                            <span class="text-sm text-zinc-500">{{ $date($order->created_at) }}</span>
                        </div>
                        <div class="text-sm text-zinc-600 dark:text-zinc-300">
                            {{ $order->items->map(fn ($line) => $line->item?->name)->filter()->implode('، ') ?: '—' }}
                        </div>
                        @if ($order->odometer !== null)
                            <div class="text-xs text-zinc-500">{{ Utils::pDigits(number_format($order->odometer)) }} {{ __('order::strings.Km') }}</div>
                        @endif
                    </div>
                    <div class="text-end">
                        <div class="font-medium tabular-nums">{{ $money($order->items_sum_total ?? 0) }}</div>
                        @if ($order->next_service_at)
                            <div class="text-xs text-zinc-500">{{ __('order::attributes.Next Service') }}: {{ $date($order->next_service_at) }}</div>
                        @endif
                    </div>
                </div>
            @empty
                <flux:text class="py-6 text-center text-zinc-500">{{ __('order::strings.No Orders Yet') }}</flux:text>
            @endforelse
        </div>
    </flux:card>
</div>
