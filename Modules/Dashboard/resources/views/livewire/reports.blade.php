@php
    use App\Helpers\Utils;

    $money = fn (float $value): string => Utils::pDigits(Utils::formatQuantity($value, 2, ','));
    $stats = [
        [
            'label' => __('dashboard::strings.Order Value'),
            'value' => $money($revenue),
            'hint' => __('dashboard::strings.Excluding cancelled orders'),
            'icon' => 'banknotes',
        ],
        [
            'label' => __('dashboard::strings.Total Orders'),
            'value' => Utils::pDigits((string) $orderCount),
            'hint' => __('dashboard::strings.Average Order') . ': ' . $money($average),
            'icon' => 'shopping-bag',
        ],
        [
            'label' => __('dashboard::strings.Collected'),
            'value' => $money($collected),
            'hint' => __('dashboard::strings.Payments received'),
            'icon' => 'arrow-down-tray',
        ],
        [
            'label' => __('dashboard::strings.Outstanding'),
            'value' => $money($outstandingTotal),
            'hint' => Utils::pDigits((string) $outstandingCount) . ' ' . __('dashboard::strings.Orders still owing') . ' · ' . __('dashboard::strings.As of today'),
            'icon' => 'clock',
        ],
    ];
@endphp

<div class="flex w-full flex-1 flex-col gap-6">
    <flux:heading
        size="xl"
        level="1"
    >
        {{ __('dashboard::strings.Reports') }}
    </flux:heading>

    <x-table.filters
        :filters="$filterDefinitions"
        clear-action="clearFilters"
    />

    {{-- Headline numbers --}}
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($stats as $stat)
            <flux:card class="min-w-0 space-y-2">
                <div class="flex items-center justify-between text-zinc-500">
                    <flux:text>{{ $stat['label'] }}</flux:text>
                    <flux:icon
                        :name="$stat['icon']"
                        class="size-5"
                    />
                </div>
                <div class="text-2xl font-semibold tabular-nums">{{ $stat['value'] }}</div>
                <flux:text size="sm">{{ $stat['hint'] }}</flux:text>
            </flux:card>
        @endforeach
    </div>

    <div class="grid gap-4 xl:grid-cols-3">
        {{-- Top items --}}
        <flux:card class="min-w-0 space-y-3 xl:col-span-2">
            <flux:heading>{{ __('dashboard::strings.Top Items') }}</flux:heading>

            @forelse ($topItems as $line)
                <div class="flex items-center justify-between gap-3 rounded-lg px-2 py-2">
                    <div class="min-w-0">
                        <div class="truncate font-medium">{{ $line->item->name }}</div>
                        <flux:text size="sm">
                            {{ __('dashboard::strings.In orders', ['count' => Utils::pDigits((string) $line->orders)]) }}
                        </flux:text>
                    </div>
                    <span class="shrink-0 tabular-nums text-sm">{{ $money((float) $line->revenue) }}</span>
                </div>
            @empty
                <flux:text class="py-8 text-center">{{ __('dashboard::strings.No sales in this period') }}</flux:text>
            @endforelse
        </flux:card>

        {{-- Payments by method --}}
        <flux:card class="min-w-0 space-y-3">
            <flux:heading>{{ __('dashboard::strings.Payments by Method') }}</flux:heading>

            @forelse ($byMethod as $method)
                <div class="flex items-center justify-between gap-3 rounded-lg px-2 py-2">
                    <div class="min-w-0">
                        <div class="truncate font-medium">{{ $method->method->label() }}</div>
                        <flux:text size="sm">
                            {{ __('dashboard::strings.Payments count', ['count' => Utils::pDigits((string) $method->payments)]) }}
                        </flux:text>
                    </div>
                    <span class="shrink-0 tabular-nums text-sm">{{ $money((float) $method->total) }}</span>
                </div>
            @empty
                <flux:text class="py-8 text-center">{{ __('dashboard::strings.No payments in this period') }}</flux:text>
            @endforelse
        </flux:card>
    </div>

    {{-- Profit --}}
    <flux:card class="space-y-4" data-profit>
        <div>
            <flux:heading>{{ __('dashboard::strings.Profit') }}</flux:heading>
            <flux:text size="sm">{{ __('dashboard::strings.Profit Hint') }}</flux:text>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div>
                <flux:text size="sm">{{ __('dashboard::strings.Parts Profit') }}</flux:text>
                <div class="text-2xl font-semibold tabular-nums" data-parts-profit="{{ $profit['parts_profit'] }}">{{ $money($profit['parts_profit']) }}</div>
                <flux:text size="sm">
                    {{ $profit['margin'] === null ? '—' : __('dashboard::strings.Margin', ['percent' => Utils::pDigits((string) $profit['margin'])]) }}
                </flux:text>
            </div>
            <div>
                <flux:text size="sm">{{ __('dashboard::strings.Parts Cost') }}</flux:text>
                <div class="text-2xl font-semibold tabular-nums" data-parts-cost="{{ $profit['parts_cost'] }}">{{ $money($profit['parts_cost']) }}</div>
                <flux:text size="sm">{{ __('dashboard::strings.Of Sales', ['amount' => $money($profit['parts_revenue'])]) }}</flux:text>
            </div>
            <div>
                <flux:text size="sm">{{ __('dashboard::strings.Services Revenue') }}</flux:text>
                <div class="text-2xl font-semibold tabular-nums" data-services-revenue="{{ $profit['services_revenue'] }}">{{ $money($profit['services_revenue']) }}</div>
            </div>
            <div>
                <flux:text size="sm">{{ __('dashboard::strings.Unknown Cost') }}</flux:text>
                <div class="text-2xl font-semibold tabular-nums" data-unknown-cost="{{ $profit['unknown_cost_revenue'] }}">{{ $money($profit['unknown_cost_revenue']) }}</div>
                <flux:text size="sm">{{ __('dashboard::strings.Unknown Cost Hint') }}</flux:text>
            </div>
        </div>

        @foreach ($profit['items'] as $row)
            <div class="flex items-center justify-between gap-3 rounded-lg px-2 py-2" data-profit-item="{{ $row['name'] }}">
                <div class="min-w-0">
                    <div class="truncate font-medium">{{ $row['name'] }}</div>
                    <flux:text size="sm">{{ __('dashboard::strings.Margin', ['percent' => Utils::pDigits((string) $row['margin'])]) }}</flux:text>
                </div>
                <span @class(['shrink-0 tabular-nums text-sm', 'text-red-600' => $row['profit'] < 0])>{{ $money($row['profit']) }}</span>
            </div>
        @endforeach
    </flux:card>

    {{-- Purchases by supplier --}}
    <flux:card class="space-y-3">
        <flux:heading>{{ __('dashboard::strings.Purchases by Supplier') }}</flux:heading>

        @forelse ($bySupplier as $row)
            <div class="flex items-center justify-between gap-3 rounded-lg px-2 py-2">
                <div class="min-w-0">
                    <div class="truncate font-medium">{{ $row->supplier?->user->fullName() }}</div>
                    <flux:text size="sm">
                        {{ __('dashboard::strings.Purchases count', ['count' => Utils::pDigits((string) $row->purchases)]) }}
                    </flux:text>
                </div>
                <span class="shrink-0 tabular-nums text-sm">{{ $money((float) $row->spent) }}</span>
            </div>
        @empty
            <flux:text class="py-8 text-center">{{ __('dashboard::strings.No purchases in this period') }}</flux:text>
        @endforelse
    </flux:card>

    {{-- Outstanding --}}
    <flux:card class="space-y-3">
        <flux:heading>{{ __('dashboard::strings.Outstanding Orders') }}</flux:heading>

        @forelse ($outstanding as $row)
            <a
                href="{{ route('orders.edit', ['order' => $row['order']]) }}"
                wire:navigate
                class="flex items-center justify-between gap-3 rounded-lg px-2 py-2 hover:bg-zinc-50 dark:hover:bg-white/5"
            >
                <div class="flex min-w-0 gap-1 font-medium">
                    <span class="shrink-0">{{ $row['order']->order_number }}</span>
                    <span class="truncate">· {{ $row['order']->user->fullName() }}</span>
                </div>
                <div class="flex shrink-0 items-center gap-3">
                    <flux:badge
                        :color="$row['order']->paymentStatus()->color()"
                        size="sm"
                    >{{ $row['order']->paymentStatus()->label() }}</flux:badge>
                    <span class="tabular-nums text-sm font-medium text-red-600 dark:text-red-400">{{ $money($row['balance']) }}</span>
                </div>
            </a>
        @empty
            <flux:text class="py-8 text-center">{{ __('dashboard::strings.No outstanding balances') }}</flux:text>
        @endforelse
    </flux:card>
</div>
