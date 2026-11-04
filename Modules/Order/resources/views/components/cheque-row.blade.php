@php
    use App\Helpers\Utils;
    use Modules\Order\Enums\ChequeStatus;
    use Morilog\Jalali\Jalalian;

    $pending = $row->cheque_status === ChequeStatus::PENDING;
    $overdue = $pending && $row->cheque_due_at?->isBefore(today());
    $soon = $pending && ! $overdue && $row->cheque_due_at?->lte(today()->addDays(\Modules\Order\Livewire\Cheques::SOON_DAYS));
@endphp
<x-table.row wire:key="cheque-{{ $row->id }}">
    <x-table.cell>
        <div class="font-medium" dir="ltr" data-cheque="{{ $row->id }}">{{ Utils::pDigits((string) $row->cheque_number) }}</div>
        <div class="text-xs text-zinc-500">{{ $row->cheque_bank }}</div>
    </x-table.cell>
    <x-table.cell>{{ $row->order->user->fullName() }}</x-table.cell>
    <x-table.cell>
        <flux:link href="{{ route('orders.edit', ['order' => $row->order]) }}">{{ $row->order->order_number }}</flux:link>
    </x-table.cell>
    <x-table.cell class="tabular-nums">{{ Utils::pDigits(Utils::formatQuantity((float) $row->amount, 0, ',')) }}</x-table.cell>
    <x-table.cell>
        <span @class(['text-red-600 font-medium' => $overdue, 'text-amber-600' => $soon])>
            {{ $row->cheque_due_at ? Utils::pDigits(Jalalian::fromDateTime($row->cheque_due_at)->format('Y/m/d')) : '—' }}
        </span>
        @if ($overdue)
            <div class="text-xs text-red-600">{{ __('order::strings.Cheques Overdue') }}</div>
        @endif
    </x-table.cell>
    <x-table.cell>
        <flux:badge size="sm" :color="$row->cheque_status->color()">{{ $row->cheque_status->label() }}</flux:badge>
    </x-table.cell>
    <x-table.cell>
        @if ($pending)
            <flux:button wire:click="mark({{ $row->id }}, 'cleared')" icon="check" variant="ghost" :tooltip="__('order::strings.Mark Cleared')" data-clear="{{ $row->id }}" />
            <flux:button wire:click="mark({{ $row->id }}, 'bounced')" wire:confirm="{{ __('order::strings.Mark Bounced Confirm') }}" icon="x-mark" variant="ghost" :tooltip="__('order::strings.Mark Bounced')" data-bounce="{{ $row->id }}" />
        @else
            <flux:button wire:click="mark({{ $row->id }}, 'pending')" icon="arrow-uturn-left" variant="ghost" :tooltip="__('order::strings.Mark Pending')" data-reopen="{{ $row->id }}" />
        @endif
    </x-table.cell>
</x-table.row>
