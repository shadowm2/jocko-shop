@php use App\Helpers\Utils; @endphp
<div class="flex flex-col gap-5">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <flux:heading size="xl" level="1">{{ __('order::strings.Debts') }}</flux:heading>
            <flux:subheading>{{ __('order::strings.Debts Description') }}</flux:subheading>
        </div>
        <div class="text-end" data-total-owed="{{ $totalOwed }}">
            <div class="text-xs text-zinc-500">{{ __('order::strings.Total Owed') }}</div>
            <div class="text-2xl font-semibold tabular-nums text-red-600 dark:text-red-400">{{ Utils::pDigits(Utils::formatQuantity($totalOwed, 0, ',')) }}</div>
        </div>
    </div>

    @if ($rows->isNotEmpty())
        @php
            $limits = \Modules\Order\Services\CustomerBalanceService::AGING_DAYS;
            $names = [
                __('order::strings.Aging Up To', ['days' => Utils::pDigits((string) $limits[0])]),
                __('order::strings.Aging Between', ['from' => Utils::pDigits((string) ($limits[0] + 1)), 'to' => Utils::pDigits((string) $limits[1])]),
                __('order::strings.Aging Between', ['from' => Utils::pDigits((string) ($limits[1] + 1)), 'to' => Utils::pDigits((string) $limits[2])]),
                __('order::strings.Aging Over', ['days' => Utils::pDigits((string) $limits[2])]),
            ];
        @endphp
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4" data-aging>
            @foreach ($aging as $i => $amount)
                <flux:card class="space-y-1" data-aging-bracket="{{ $i }}" data-amount="{{ $amount }}">
                    <div class="text-xs text-zinc-500">{{ $names[$i] }}</div>
                    <div @class(['text-xl font-semibold tabular-nums', 'text-zinc-400' => $amount <= 0, 'text-amber-600 dark:text-amber-400' => $amount > 0 && $i === 2, 'text-red-600 dark:text-red-400' => $amount > 0 && $i === 3])>{{ Utils::pDigits(Utils::formatQuantity($amount, 0, ',')) }}</div>
                    @if ($totalOwed > 0)
                        <div class="text-xs text-zinc-500">{{ Utils::pDigits((string) round($amount / $totalOwed * 100)) }}%</div>
                    @endif
                </flux:card>
            @endforeach
        </div>
    @endif

    <x-table.data-table
        :$columns
        :$rows
        :title="__('order::strings.Customers Who Owe')"
        row-component="order::components.debtor-row"
    >
        <x-slot name="action">
            @if ($rows->isNotEmpty())
                <flux:button icon:trailing="chat-bubble-left-ellipsis" wire:click="remindAll" wire:confirm="{{ __('order::strings.Remind Everyone Confirm') }}" data-remind-all>
                    {{ __('order::strings.Remind Everyone') }}
                </flux:button>
            @endif
        </x-slot>
    </x-table.data-table>

    @if ($rows->isEmpty())
        <flux:callout variant="success" icon="check-circle" data-no-debts>
            <flux:callout.text>{{ __('order::strings.No Debts') }}</flux:callout.text>
        </flux:callout>
    @endif
</div>
