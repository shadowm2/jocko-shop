@php
    use App\Helpers\Utils;
    use Morilog\Jalali\Jalalian;

    $direction = $row->direction();
@endphp
<x-table.row>
    <x-table.cell>
        {{ Utils::pDigits(Jalalian::fromDateTime($row->created_at)->format('Y/m/d H:i')) }}
    </x-table.cell>
    <x-table.cell>
        <flux:link :href="route('items.edit', ['item' => $row->item])">
            {{ $row->item?->name }}
        </flux:link>
    </x-table.cell>
    <x-table.cell>
        <div class="flex flex-col items-start gap-1">
            <flux:badge :color="match ($direction) { 1 => 'green', -1 => 'red', default => 'zinc' }">
                {{ $row->type->label() }}
            </flux:badge>
            @if ($row->description)
                <span class="text-xs text-zinc-500">{{ $row->description }}</span>
            @endif
        </div>
    </x-table.cell>
    @multiwarehouse
        <x-table.cell>
            @if ($direction === 0)
                {{ $row->fromWarehouse?->name }} &rarr; {{ $row->toWarehouse?->name }}
            @else
                {{ ($row->toWarehouse ?? $row->fromWarehouse)?->name }}
            @endif
        </x-table.cell>
    @endmultiwarehouse
    <x-table.cell>
        <span
            dir="ltr"
            @class(['text-green-600' => $direction === 1, 'text-red-600' => $direction === -1])
        >{{ match ($direction) { 1 => '+', -1 => '-', default => '' } }}{{ Utils::formatQuantity($row->quantity, 3, ',') }}</span>
    </x-table.cell>
    <x-table.cell>
        {{ Utils::formatQuantity($row->balance_after, 3, ',') }}
    </x-table.cell>
    <x-table.cell>
        {{ $row->reference?->order_number ?? '-' }}
    </x-table.cell>
    <x-table.cell>
        {{ $row->user?->fullName() ?? '-' }}
    </x-table.cell>
</x-table.row>
