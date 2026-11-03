@php
    use App\Helpers\Utils;
    use Morilog\Jalali\Jalalian;

    [$entity, $action] = array_pad(explode('.', $row->event_type, 2), 2, '');
    $status = $row->pushed_at !== null ? 'sent' : ($row->rejected_code !== null ? 'refused' : 'waiting');
@endphp
<x-table.row>
    <x-table.cell>
        {{ Utils::pDigits(Jalalian::fromDateTime($row->created_at)->format('Y/m/d H:i:s')) }}
    </x-table.cell>
    <x-table.cell>
        <div class="flex flex-col items-start gap-1">
            <span
                dir="ltr"
                class="font-medium"
            >{{ $entity }}</span>
            <flux:badge size="sm">{{ $action }}</flux:badge>
        </div>
    </x-table.cell>
    <x-table.cell>
        <span dir="ltr">{{ isset($row->payload['id']) ? '#'.$row->payload['id'] : '—' }}</span>
    </x-table.cell>
    <x-table.cell>
        <flux:badge :color="match ($status) { 'sent' => 'green', 'refused' => 'red', default => 'amber' }">
            {{ __('dashboard::strings.'.ucfirst($status)) }}
        </flux:badge>
        @if ($row->rejected_code)
            <div
                dir="ltr"
                class="mt-1 text-xs text-zinc-500"
            >{{ $row->rejected_code }}</div>
        @endif
    </x-table.cell>
    <x-table.cell>
        {{ Utils::pDigits((string) $row->attempts) }}
    </x-table.cell>
    <x-table.cell>
        @if ($row->last_error)
            <span
                class="line-clamp-2 max-w-xs text-xs text-red-600 dark:text-red-400"
                title="{{ $row->last_error }}"
            >{{ $row->last_error }}</span>
        @else
            —
        @endif
    </x-table.cell>
</x-table.row>
