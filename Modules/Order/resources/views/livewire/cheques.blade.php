@php use App\Helpers\Utils; @endphp
<div class="flex flex-col gap-5">
    <div>
        <flux:heading size="xl" level="1">{{ __('order::strings.Cheques') }}</flux:heading>
        <flux:subheading>{{ __('order::strings.Cheques Description') }}</flux:subheading>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-4" data-cheque-summary>
        <flux:card>
            <div class="text-sm text-zinc-500">{{ __('order::strings.Cheques Pending') }}</div>
            <div class="text-2xl font-semibold tabular-nums" data-pending="{{ $summary['pending'] }}">{{ Utils::pDigits(Utils::formatQuantity($summary['pending'], 0, ',')) }}</div>
        </flux:card>
        <flux:card>
            <div class="text-sm text-zinc-500">{{ __('order::strings.Cheques Due Soon', ['days' => Utils::pDigits((string) \Modules\Order\Livewire\Cheques::SOON_DAYS)]) }}</div>
            <div class="text-2xl font-semibold tabular-nums text-amber-600" data-soon="{{ $summary['soon'] }}">{{ Utils::pDigits(Utils::formatQuantity($summary['soon'], 0, ',')) }}</div>
            <div class="text-xs text-zinc-500">{{ __('order::strings.Cheques Count', ['count' => Utils::pDigits((string) $summary['soon_count'])]) }}</div>
        </flux:card>
        <flux:card>
            <div class="text-sm text-zinc-500">{{ __('order::strings.Cheques Overdue') }}</div>
            <div class="text-2xl font-semibold tabular-nums text-red-600" data-overdue="{{ $summary['overdue'] }}">{{ Utils::pDigits(Utils::formatQuantity($summary['overdue'], 0, ',')) }}</div>
            <div class="text-xs text-zinc-500">{{ __('order::strings.Cheques Count', ['count' => Utils::pDigits((string) $summary['overdue_count'])]) }}</div>
        </flux:card>
        <flux:card>
            <div class="text-sm text-zinc-500">{{ __('order::strings.Cheques Bounced') }}</div>
            <div class="text-2xl font-semibold tabular-nums" data-bounced="{{ $summary['bounced'] }}">{{ Utils::pDigits(Utils::formatQuantity($summary['bounced'], 0, ',')) }}</div>
        </flux:card>
    </div>

    <flux:radio.group wire:model.live="status" variant="segmented" data-cheque-filter>
        @foreach ($statuses as $status)
            <flux:radio :value="$status->value" :label="$status->label()" />
        @endforeach
        <flux:radio value="all" :label="__('strings.All')" />
    </flux:radio.group>

    <x-table.data-table
        :$columns
        :$rows
        :title="__('order::strings.Cheques')"
        row-component="order::components.cheque-row"
    />
</div>
