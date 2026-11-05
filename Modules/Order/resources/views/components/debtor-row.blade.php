@php
    use App\Helpers\Utils;
    use Morilog\Jalali\Jalalian;

    $user = $row['user'];
    $days = $row['oldest_at'] ? (int) $row['oldest_at']->diffInDays(now()) : 0;
@endphp
<x-table.row>
    <x-table.cell>
        <div class="font-medium" data-debtor="{{ $user->id }}">{{ $user->fullName() }}</div>
        <div class="text-xs text-zinc-500" dir="ltr">{{ Utils::pDigits((string) $user->mobile) ?: '—' }}</div>
    </x-table.cell>
    <x-table.cell>{{ Utils::pDigits((string) $row['open_orders']) }}</x-table.cell>
    <x-table.cell>
        <span class="font-semibold tabular-nums text-red-600 dark:text-red-400" data-balance="{{ $row['balance'] }}">{{ Utils::pDigits(Utils::formatQuantity($row['balance'], 0, ',')) }}</span>
    </x-table.cell>
    <x-table.cell>
        @php $old = $row['aging'][2] + $row['aging'][3]; @endphp
        @if ($old > 0)
            <span class="tabular-nums text-red-600 dark:text-red-400" data-owed-old="{{ $old }}">{{ Utils::pDigits(Utils::formatQuantity($old, 0, ',')) }}</span>
        @else
            <span class="text-zinc-400">—</span>
        @endif
    </x-table.cell>
    <x-table.cell>
        @if ($row['oldest_at'])
            <span @class(['text-red-600' => $days > 30, 'text-amber-600' => $days > 14 && $days <= 30])>
                {{ Utils::pDigits(Jalalian::fromDateTime($row['oldest_at'])->format('Y/m/d')) }}
            </span>
            <div class="text-xs text-zinc-500">{{ __('order::strings.Days Ago', ['days' => Utils::pDigits((string) $days)]) }}</div>
        @endif
    </x-table.cell>
    <x-table.cell>
        @if ($row['last_reminder_at'])
            {{ Utils::pDigits(Jalalian::fromDateTime($row['last_reminder_at'])->format('Y/m/d')) }}
        @else
            <span class="text-zinc-400">—</span>
        @endif
    </x-table.cell>
    <x-table.cell>
        <flux:button :href="route('users.statement', $user)" target="_blank" icon="document-text" variant="ghost" :tooltip="__('order::strings.Statement')" />
        <flux:button wire:click="remind({{ $user->id }})" icon="chat-bubble-left-ellipsis" variant="ghost" :tooltip="__('order::strings.Send Reminder')" data-remind="{{ $user->id }}" />
    </x-table.cell>
</x-table.row>
