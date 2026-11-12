<x-table.row>
    <x-table.cell>
        {{ \Illuminate\Support\Facades\Lang::has('dashboard::countries.' . $row->code)
            ? __('dashboard::countries.' . $row->code)
            : $row->name }}
    </x-table.cell>
    <x-table.cell>
        {{ \App\Helpers\Utils::pDigits($row->code) }}
    </x-table.cell>
    <x-table.cell>
        {{ \App\Helpers\Utils::pDigits($row->code3) }}
    </x-table.cell>
    <x-table.cell>
        <span dir="ltr">{{ \App\Helpers\Utils::pDigits($row->phone_code) }}</span>
    </x-table.cell>
    <x-table.cell>
        <flux:switch
            :checked="$row->is_active"
            wire:change="toggleIsActive('{{ $row->code }}')"
        />
    </x-table.cell>
    <x-table.cell>
        <flux:button
            :tooltip="__('dashboard::strings.Edit Country')"
            :href="route('countries.edit', ['country' => $row->code])"
            icon="pencil-square"
            variant="ghost"
        />
    </x-table.cell>
</x-table.row>
