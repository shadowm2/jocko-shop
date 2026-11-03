@php
    use App\Helpers\Utils;
    use Modules\Order\Enums\OrderStatus;

    $stats = [
        [
            'label' => __('dashboard::strings.Total Orders'),
            'value' => Utils::pDigits((string) $orderCount),
            'hint' => Utils::pDigits((string) ($statusCounts[OrderStatus::PENDING->value] ?? 0)) . ' ' . OrderStatus::PENDING->label(),
            'icon' => 'shopping-bag',
            'href' => route('orders.index'),
        ],
        [
            'label' => __('dashboard::strings.Order Value'),
            'value' => Utils::formatQuantity($orderValue, 2, ','),
            'hint' => __('dashboard::strings.Excluding cancelled orders'),
            'icon' => 'banknotes',
            'href' => route('orders.index'),
        ],
        [
            'label' => __('dashboard::strings.Open Purchases'),
            'value' => Utils::pDigits((string) $openPurchases),
            'hint' => __('dashboard::strings.Ordered or partially received'),
            'icon' => 'shopping-cart',
            'href' => route('purchases.index'),
        ],
        [
            'label' => __('dashboard::strings.Customers'),
            'value' => Utils::pDigits((string) $customerCount),
            'hint' => __('dashboard::strings.Registered customers'),
            'icon' => 'users',
            'href' => route('users.index'),
        ],
    ];

    $barColors = [
        OrderStatus::PENDING->value => 'bg-zinc-400',
        OrderStatus::IN_PROGRESS->value => 'bg-amber-500',
        OrderStatus::COMPLETED->value => 'bg-green-500',
        OrderStatus::CANCELLED->value => 'bg-red-500',
    ];

    $actions = [
        ['orders.create', 'dashboard::strings.New Order', 'shopping-bag', true],
        ['purchases.create', 'dashboard::strings.New Purchase', 'shopping-cart', false],
        ['items.create', 'dashboard::strings.New Item', 'cube', false],
        ['users.create', 'dashboard::strings.New User', 'user-plus', false],
        ['suppliers.create', 'dashboard::strings.New Supplier', 'truck', false],
        ['warehouses.create', 'dashboard::strings.New Warehouse', 'shelving-unit', false],
    ];

    // Same rule as the sidebar: no warehouses to manage while one is in use and chosen.
    if (! resolve(\Modules\Inventory\Services\WarehouseService::class)->showsWarehouseMenu()) {
        $actions = array_values(array_filter($actions, fn (array $action): bool => $action[0] !== 'warehouses.create'));
    }
@endphp

<div class="flex w-full flex-1 flex-col gap-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <flux:heading
            size="xl"
            level="1"
        >
            {{ __('dashboard::strings.Dashboard') }}
        </flux:heading>

        <div class="flex flex-wrap gap-2">
            @foreach ($actions as [$routeName, $label, $icon, $primary])
                <flux:button
                    :href="route($routeName)"
                    :icon="$icon"
                    :variant="$primary ? 'primary' : 'filled'"
                    size="sm"
                    wire:navigate
                >
                    {{ __($label) }}
                </flux:button>
            @endforeach
        </div>
    </div>

    {{-- Headline numbers --}}
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($stats as $stat)
            <a
                href="{{ $stat['href'] }}"
                wire:navigate
                class="group min-w-0"
            >
                <flux:card class="h-full space-y-2 transition group-hover:bg-zinc-50 dark:group-hover:bg-zinc-700/50">
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
            </a>
        @endforeach
    </div>

    {{-- Trends --}}
    @if (array_sum($monthSales) + array_sum($monthPurchases) > 0)
        <div class="grid gap-4 xl:grid-cols-2">
            <flux:card class="min-w-0 space-y-3">
                <div>
                    <flux:heading>{{ __('dashboard::strings.Sales vs Purchases') }}</flux:heading>
                    <flux:text size="sm">{{ __('dashboard::strings.Last 6 months') }}</flux:text>
                </div>
                <x-dashboard::chart.columns
                    :labels="$monthLabels"
                    :series="[__('dashboard::strings.Sales') => $monthSales, __('dashboard::strings.Purchases') => $monthPurchases]"
                />
            </flux:card>

            <flux:card class="min-w-0 space-y-3">
                <div>
                    <flux:heading>{{ __('dashboard::strings.Daily Sales') }}</flux:heading>
                    <flux:text size="sm">{{ __('dashboard::strings.Last 30 days') }}</flux:text>
                </div>
                <x-dashboard::chart.columns
                    :labels="$dayLabels"
                    :series="[__('dashboard::strings.Sales') => $daySales]"
                    :label-every="5"
                />
            </flux:card>
        </div>
    @endif

    {{-- Orders by status --}}
    @if ($orderCount)
        <flux:card class="space-y-3">
            <flux:heading>{{ __('dashboard::strings.Orders by Status') }}</flux:heading>
            <div class="flex h-2 w-full overflow-hidden rounded-full bg-zinc-100 dark:bg-white/10">
                @foreach (OrderStatus::cases() as $status)
                    @if ($count = $statusCounts[$status->value] ?? 0)
                        <div
                            class="{{ $barColors[$status->value] }}"
                            style="width: {{ $count / $orderCount * 100 }}%"
                            title="{{ $status->label() }}"
                        ></div>
                    @endif
                @endforeach
            </div>
            <div class="flex flex-wrap gap-x-6 gap-y-2">
                @foreach (OrderStatus::cases() as $status)
                    <div class="flex items-center gap-2">
                        <flux:badge
                            :color="$status->color()"
                            size="sm"
                        >{{ $status->label() }}</flux:badge>
                        <span class="tabular-nums">{{ Utils::pDigits((string) ($statusCounts[$status->value] ?? 0)) }}</span>
                    </div>
                @endforeach
            </div>
        </flux:card>
    @endif

    <div class="grid gap-4 xl:grid-cols-3">
        {{-- Recent orders --}}
        <flux:card class="min-w-0 space-y-3 xl:col-span-2">
            <div class="flex items-center justify-between">
                <flux:heading>{{ __('dashboard::strings.Recent Orders') }}</flux:heading>
                <flux:button
                    :href="route('orders.index')"
                    size="sm"
                    variant="ghost"
                    wire:navigate
                >
                    {{ __('dashboard::strings.View All') }}
                </flux:button>
            </div>

            @forelse ($recentOrders as $order)
                <a
                    href="{{ route('orders.edit', ['order' => $order]) }}"
                    wire:navigate
                    class="flex items-center justify-between gap-3 rounded-lg px-2 py-2 hover:bg-zinc-50 dark:hover:bg-white/5"
                >
                    <div class="min-w-0">
                        <div class="flex gap-1 font-medium"><span class="shrink-0">{{ $order->order_number }}</span><span class="truncate">· {{ $order->user->fullName() }}</span></div>
                        <flux:text
                            size="sm"
                            class="truncate"
                        >
                            {{ $order->userCar->car->name }}@multiwarehouse · {{ $order->warehouse->name }}@endmultiwarehouse
                        </flux:text>
                    </div>
                    <div class="flex shrink-0 items-center gap-3">
                        <span class="tabular-nums text-sm">{{ Utils::formatQuantity($order->items_sum_total ?? 0, 2, ',') }}</span>
                        <flux:badge
                            :color="$order->status->color()"
                            size="sm"
                        >{{ $order->status->label() }}</flux:badge>
                    </div>
                </a>
            @empty
                <div class="flex flex-col items-center gap-3 py-8">
                    <flux:text>{{ __('dashboard::strings.No orders yet') }}</flux:text>
                    <flux:button
                        :href="route('orders.create')"
                        variant="primary"
                        icon="plus"
                        size="sm"
                        wire:navigate
                    >
                        {{ __('dashboard::strings.New Order') }}
                    </flux:button>
                </div>
            @endforelse
        </flux:card>

        {{-- Low stock --}}
        <flux:card class="min-w-0 space-y-3">
            <div class="flex items-center justify-between">
                <flux:heading>
                    {{ __('dashboard::strings.Low Stock') }}
                    @if ($lowStockCount)
                        <flux:badge
                            color="red"
                            size="sm"
                            class="ms-1"
                        >{{ Utils::pDigits((string) $lowStockCount) }}</flux:badge>
                    @endif
                </flux:heading>
                <flux:button
                    :href="route('warehouses.items.low-stock')"
                    size="sm"
                    variant="ghost"
                    wire:navigate
                >
                    {{ __('dashboard::strings.View All') }}
                </flux:button>
            </div>

            @forelse ($lowStock as $warehouseItem)
                <a
                    href="{{ route('warehouses.items.edit', ['warehouse' => $warehouseItem->warehouse, 'warehouseItem' => $warehouseItem]) }}"
                    wire:navigate
                    class="flex items-center justify-between gap-3 rounded-lg px-2 py-2 hover:bg-zinc-50 dark:hover:bg-white/5"
                >
                    <div class="min-w-0">
                        <div class="truncate font-medium">{{ $warehouseItem->item->name }}</div>
                        <flux:text
                            size="sm"
                            class="truncate"
                        >@multiwarehouse{{ $warehouseItem->warehouse->name }}@endmultiwarehouse</flux:text>
                    </div>
                    <div class="shrink-0 text-end text-sm tabular-nums">
                        <div class="font-medium text-red-600 dark:text-red-400">
                            {{ Utils::formatQuantity($warehouseItem->quantity, thousandsSeparator: ',') }}
                        </div>
                        <flux:text size="sm">
                            {{ __('dashboard::strings.Minimum') }}:
                            {{ Utils::formatQuantity($warehouseItem->min_quantity, thousandsSeparator: ',') }}
                        </flux:text>
                    </div>
                </a>
            @empty
                <flux:text class="py-8 text-center">{{ __('dashboard::strings.All items are well stocked') }}</flux:text>
            @endforelse
        </flux:card>
    </div>

    {{-- Recent purchases --}}
    <flux:card class="space-y-3">
        <div class="flex items-center justify-between">
            <flux:heading>{{ __('dashboard::strings.Recent Purchases') }}</flux:heading>
            <flux:button
                :href="route('purchases.index')"
                size="sm"
                variant="ghost"
                wire:navigate
            >
                {{ __('dashboard::strings.View All') }}
            </flux:button>
        </div>

        @forelse ($recentPurchases as $purchase)
            <a
                href="{{ route('purchases.edit', ['purchase' => $purchase]) }}"
                wire:navigate
                class="flex items-center justify-between gap-3 rounded-lg px-2 py-2 hover:bg-zinc-50 dark:hover:bg-white/5"
            >
                <div class="min-w-0">
                    <div class="flex gap-1 font-medium"><span class="shrink-0">{{ $purchase->order_number }}</span><span class="truncate">· {{ $purchase->supplier->user->fullName() }}</span></div>
                    <flux:text
                        size="sm"
                        class="truncate"
                    >@multiwarehouse{{ $purchase->warehouse->name }}@endmultiwarehouse</flux:text>
                </div>
                <div class="flex shrink-0 items-center gap-3">
                    <span class="tabular-nums text-sm">{{ Utils::formatQuantity($purchase->items_sum_total ?? 0, 2, ',') }}</span>
                    <flux:badge
                        :color="$purchase->status->color()"
                        size="sm"
                    >{{ $purchase->status->label() }}</flux:badge>
                </div>
            </a>
        @empty
            <div class="flex flex-col items-center gap-3 py-8">
                <flux:text>{{ __('dashboard::strings.No purchases yet') }}</flux:text>
                <flux:button
                    :href="route('purchases.create')"
                    variant="primary"
                    icon="plus"
                    size="sm"
                    wire:navigate
                >
                    {{ __('dashboard::strings.New Purchase') }}
                </flux:button>
            </div>
        @endforelse
    </flux:card>
</div>
