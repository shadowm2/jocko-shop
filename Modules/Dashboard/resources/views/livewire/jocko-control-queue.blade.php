<div
    class="flex flex-col gap-5"
    wire:poll.10s.visible
>
    <div>
        <flux:heading
            size="xl"
            level="1"
        >{{ __('dashboard::strings.Queued Changes') }}</flux:heading>
        <flux:subheading>{{ __('dashboard::strings.Queued Changes Description') }}</flux:subheading>
    </div>

    @if (! $linked)
        <flux:callout
            icon="exclamation-triangle"
            variant="warning"
        >
            <flux:callout.text>
                {{ __('dashboard::strings.Queued Changes Not Linked') }}
                <flux:link :href="route('jocko-control-settings.edit')">{{ __('dashboard::strings.Jocko Control') }}</flux:link>
            </flux:callout.text>
        </flux:callout>
    @elseif ($lastError)
        <flux:callout
            icon="signal-slash"
            variant="danger"
        >
            <flux:callout.text>{{ __('dashboard::strings.Queued Changes Cannot Reach Control', ['message' => $lastError['message']]) }}</flux:callout.text>
        </flux:callout>
    @endif

    <div
        class="grid grid-cols-1 gap-3 sm:grid-cols-3"
        data-queue-counts
    >
        @foreach ([\Modules\Dashboard\Livewire\JockoControlQueue::WAITING => 'amber', \Modules\Dashboard\Livewire\JockoControlQueue::REFUSED => 'red', \Modules\Dashboard\Livewire\JockoControlQueue::SENT => 'green'] as $status => $color)
            <flux:card class="flex items-center justify-between">
                <flux:badge :color="$color">{{ __('dashboard::strings.'.ucfirst($status)) }}</flux:badge>
                <span
                    class="text-2xl font-semibold tabular-nums"
                    data-count="{{ $status }}"
                >{{ \App\Helpers\Utils::pDigits(number_format($counts[$status])) }}</span>
            </flux:card>
        @endforeach
    </div>

    <x-table.data-table
        :$columns
        :$rows
        :title="__('dashboard::strings.Queued Changes')"
        row-component="dashboard::components.outbox-event-row"
    >
        <x-slot name="toolbar">
            <x-table.filter-drawer
                :values="$filters"
                :filters="$filterDefinitions"
            >
                <x-table.filters
                    :filters="$filterDefinitions"
                    clear-action="clearFilters"
                    panel
                />
            </x-table.filter-drawer>
        </x-slot>

        <x-slot name="action">
            <div class="flex gap-2">
                @if ($counts['refused'] > 0)
                    <flux:button
                        icon:trailing="arrow-path"
                        wire:click="retryRefused"
                        wire:confirm="{{ __('dashboard::strings.Retry Refused Confirm') }}"
                    >
                        {{ __('dashboard::strings.Retry Refused') }}
                    </flux:button>
                @endif
                <flux:button
                    icon:trailing="paper-airplane"
                    variant="primary"
                    wire:click="pushNow"
                    wire:loading.attr="disabled"
                    wire:target="pushNow"
                >
                    {{ __('dashboard::strings.Send Now') }}
                </flux:button>
            </div>
        </x-slot>
    </x-table.data-table>
</div>
