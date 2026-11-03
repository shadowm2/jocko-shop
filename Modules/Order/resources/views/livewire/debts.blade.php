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
