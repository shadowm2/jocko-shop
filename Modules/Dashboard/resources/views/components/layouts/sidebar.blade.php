@php
    $activeWarehouse = resolve(\Modules\Inventory\Services\WarehouseService::class)->getActiveWarehouse();
@endphp
<!DOCTYPE html>
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    class="dark"
    dir="{{ app()->getLocale() === 'fa' ? 'rtl' : 'ltr' }}"
>

<head>
    @include('dashboard::partials.head')
</head>

<body class="min-h-screen bg-white dark:bg-zinc-800">
    <flux:sidebar
        sticky
        collapsible="mobile"
        class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900"
    >
        <flux:sidebar.header class="relative">
            <x-app-logo
                :sidebar="true"
                href="{{ route('dashboard') }}"
                wire:navigate
            />
            <flux:sidebar.collapse class="lg:hidden" />
            <flux:button
                variant="ghost"
                class="relative size-10 overflow-hidden"
                @click="
                document.documentElement.classList.add('theme-transition');
                $flux.appearance = $flux.appearance === 'light' ? 'dark':'light';
                setTimeout(() => {
                    document.documentElement.classList.remove('theme-transition')
                }, 300);
                "
            >
                <flux:icon.computer-desktop
                    x-show="$flux.appearance === 'system'"
                    x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="opacity-0 scale-50 -rotate-120"
                    x-transition:enter-end="opacity-100 scale-100 rotate-0"
                    x-transition:leave="transition ease-in duration-300"
                    x-transition:leave-start="opacity-100 scale-100 rotate-0"
                    x-transition:leave-end="opacity-0 scale-50 rotate-120"
                    class="absolute size-5"
                />

                <flux:icon.moon
                    x-show="$flux.appearance === 'dark'"
                    x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="opacity-0 scale-50 -rotate-120"
                    x-transition:enter-end="opacity-100 scale-100 rotate-0"
                    x-transition:leave="transition ease-in duration-300"
                    x-transition:leave-start="opacity-100 scale-100 rotate-0"
                    x-transition:leave-end="opacity-0 scale-50 rotate-120"
                    class="absolute size-5"
                />

                <flux:icon.sun
                    x-show="$flux.appearance === 'light'"
                    x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="opacity-0 scale-50 rotate-120"
                    x-transition:enter-end="opacity-100 scale-100 rotate-0"
                    x-transition:leave="transition ease-in duration-300"
                    x-transition:leave-start="opacity-100 scale-100 rotate-0"
                    x-transition:leave-end="opacity-0 scale-50 -rotate-120"
                    class="absolute size-5"
                />
            </flux:button>
        </flux:sidebar.header>

        <flux:sidebar.nav
            collapsible="true"
            persist
        >
            <flux:sidebar.group
                :heading="__('dashboard::strings.Platform')"
                class="grid"
            >
                <flux:sidebar.item
                    icon="home"
                    :href="route('dashboard')"
                    :current="request()->routeIs('dashboard')"
                    wire:navigate
                >
                    {{ __('dashboard::strings.Dashboard') }}
                </flux:sidebar.item>
                <flux:sidebar.item
                    icon="chart-bar"
                    :href="route('reports.index')"
                    :current="request()->routeIs('reports.index')"
                    wire:navigate
                >
                    {{ __('dashboard::strings.Reports') }}
                </flux:sidebar.item>
                <flux:sidebar.group
                    expandable
                    icon="circle-stack"
                    :heading="__('dashboard::strings.Inventory & Supply')"
                    class="grid first-level"
                    :expanded="request()->routeIs(['brands.*', 'items.*', 'suppliers.*', 'units.*','purchases.*', 'warehouses.*', 'stock-movements.*'])"
                >

                    <flux:sidebar.group
                        expandable
                        icon="cube"
                        :heading="__('inventory::strings.Items')"
                        class="grid"
                        :expanded="request()->routeIs('items.*')"
                    >
                        <flux:sidebar.item
                            :href="route('items.index')"
                            :current="request()->routeIs('items.index')"
                            wire:navigate
                        >
                            {{ __('inventory::strings.Items List') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item
                            icon:trailing="plus"
                            :href="route('items.create')"
                            :current="request()->routeIs('items.create')"
                            wire:navigate
                        >
                            {{ __('inventory::strings.Item Create') }}
                        </flux:sidebar.item>
                    </flux:sidebar.group>
                    <flux:sidebar.group
                        expandable
                        icon="truck"
                        :heading="__('inventory::strings.Suppliers')"
                        class="grid"
                        :expanded="request()->routeIs('suppliers.*')"
                    >
                        <flux:sidebar.item
                            :href="route('suppliers.index')"
                            :current="request()->routeIs('suppliers.index')"
                            wire:navigate
                        >
                            {{ __('inventory::strings.Suppliers List') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item
                            icon:trailing="plus"
                            :href="route('suppliers.create')"
                            :current="request()->routeIs('suppliers.create')"
                            wire:navigate
                        >
                            {{ __('inventory::strings.Supplier Add') }}
                        </flux:sidebar.item>
                    </flux:sidebar.group>

                    <flux:sidebar.group
                        expandable
                        :heading="__('inventory::strings.Purchases')"
                        class="grid"
                        icon="shopping-cart"
                        :expanded="request()->routeIs('purchases.*')"
                    >
                        <flux:sidebar.item
                            :href="route('purchases.index')"
                            :current="request()->routeIs('purchases.index')"
                            wire:navigate
                        >
                            {{ __('inventory::strings.Purchases List') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item
                            icon:trailing="plus"
                            :href="route('purchases.create')"
                            :current="request()->routeIs('purchases.create')"
                            wire:navigate
                        >
                            {{ __('inventory::strings.Purchase Add') }}
                        </flux:sidebar.item>
                    </flux:sidebar.group>
                    <flux:sidebar.group
                        expandable
                        :heading="__('inventory::strings.Warehouses')"
                        class="grid"
                        icon="shelving-unit"
                        :expanded="request()->routeIs('warehouses.*')"
                    >
                        <flux:sidebar.item
                            :href="route('warehouses.index')"
                            :current="request()->routeIs('warehouses.index')"
                            wire:navigate
                        >
                            {{ __('inventory::strings.Warehouses List') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item
                            icon:trailing="plus"
                            :href="route('warehouses.create')"
                            :current="request()->routeIs('warehouses.create')"
                            wire:navigate
                        >
                            {{ __('inventory::strings.Warehouse Add') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item
                            :href="route('warehouses.items.index')"
                            :current="request()->routeIs('warehouses.items.index')"
                            wire:navigate
                        >
                            {{ __('inventory::strings.Warehouse Items List') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item
                            :href="route('warehouses.items.low-stock')"
                            :current="request()->routeIs('warehouses.items.low-stock')"
                            wire:navigate
                        >
                            {{ __('inventory::strings.Low Stock') }}
                        </flux:sidebar.item>
                        @if ($activeWarehouse)
                            <flux:sidebar.item
                                :href="route('warehouses.items.create', ['warehouse' => $activeWarehouse->slug])"
                                :current="request()->routeIs('warehouses.items.create')"
                                wire:navigate
                            >
                                {{ __('inventory::strings.Warehouse Items Create') }}
                            </flux:sidebar.item>
                        @endif
                    </flux:sidebar.group>
                    <flux:sidebar.group
                        expandable
                        :heading="__('inventory::strings.Stock Movements')"
                        class="grid"
                        icon="arrows-right-left"
                        :expanded="request()->routeIs('stock-movements.*')"
                    >
                        <flux:sidebar.item
                            :href="route('stock-movements.index')"
                            :current="request()->routeIs('stock-movements.index')"
                            wire:navigate
                        >
                            {{ __('inventory::strings.Stock Movements List') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item
                            icon:trailing="plus"
                            :href="route('stock-movements.create')"
                            :current="request()->routeIs('stock-movements.create')"
                            wire:navigate
                        >
                            {{ __('inventory::strings.Stock Adjustment Add') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item
                            icon:trailing="plus"
                            :href="route('stock-movements.transfer')"
                            :current="request()->routeIs('stock-movements.transfer')"
                            wire:navigate
                        >
                            {{ __('inventory::strings.Stock Transfer Add') }}
                        </flux:sidebar.item>
                    </flux:sidebar.group>
                    <flux:sidebar.group
                        expandable
                        :heading="__('dashboard::strings.Other')"
                        class="grid"
                        icon="archive-box"
                        :expanded="request()->routeIs(['brands.*', 'units.*'])"
                    >
                        <flux:sidebar.group
                            expandable
                            :heading="__('inventory::strings.Brands')"
                            class="grid"
                            icon="shopping-bag"
                            :expanded="request()->routeIs(['brands.*',])"
                        >
                            <flux:sidebar.item
                                :href="route('brands.index')"
                                :current="request()->routeIs('brands.index')"
                                wire:navigate
                            >
                                {{ __('inventory::strings.Brands') }}
                            </flux:sidebar.item>
                            <flux:sidebar.item
                                icon:trailing="plus"
                                :href="route('brands.create')"
                                :current="request()->routeIs('brands.create')"
                                wire:navigate
                            >
                                {{ __('inventory::strings.Brand Add') }}
                            </flux:sidebar.item>
                        </flux:sidebar.group>
                        <flux:sidebar.group
                            expandable
                            :heading="__('inventory::strings.Units')"
                            class="grid"
                            icon="ruler"
                            :expanded="request()->routeIs('units.*')"
                        >
                            <flux:sidebar.item
                                :href="route('units.groups.index')"
                                :current="request()->routeIs('units.groups.index')"
                                wire:navigate
                            >
                                {{ __('inventory::strings.Unit Groups List') }}
                            </flux:sidebar.item>
                            <flux:sidebar.item
                                icon:trailing="plus"
                                :href="route('units.groups.create')"
                                :current="request()->routeIs('units.groups.create')"
                                wire:navigate
                            >
                                {{ __('inventory::strings.Unit Group Add') }}
                            </flux:sidebar.item>

                            <flux:sidebar.item
                                :href="route('units.index')"
                                :current="request()->routeIs('units.index')"
                                wire:navigate
                            >
                                {{ __('inventory::strings.Units List') }}
                            </flux:sidebar.item>
                            <flux:sidebar.item
                                icon:trailing="plus"
                                :href="route('units.create')"
                                :current="request()->routeIs('units.create')"
                                wire:navigate
                            >
                                {{ __('inventory::strings.Unit Add') }}
                            </flux:sidebar.item>
                        </flux:sidebar.group>
                    </flux:sidebar.group>
                </flux:sidebar.group>
                <flux:sidebar.group
                    expandable
                    icon="cog"
                    :heading="__('car::strings.Car')"
                    class="grid first-level"
                    :expanded="request()->routeIs(['cars.*', 'companies.*', 'colors.*'])"
                >
                    <flux:sidebar.group
                        expandable
                        :heading="__('car::strings.Cars')"
                        class="grid"
                        icon="car"
                        :expanded="request()->routeIs('cars.*')"
                    >
                        <flux:sidebar.item
                            :href="route('cars.index')"
                            :current="request()->routeIs('cars.index')"
                            wire:navigate
                        >
                            {{ __('car::strings.Cars List') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item
                            icon:trailing="plus"
                            :href="route('cars.create')"
                            :current="request()->routeIs('cars.create')"
                            wire:navigate
                        >
                            {{ __('car::strings.Add Car') }}
                        </flux:sidebar.item>
                    </flux:sidebar.group>
                    <flux:sidebar.group
                        expandable
                        :heading="__('car::strings.Car Companies')"
                        class="grid"
                        icon="building-office"
                        :expanded="request()->routeIs('companies.*')"
                    >
                        <flux:sidebar.item
                            :href="route('companies.index')"
                            :current="request()->routeIs('companies.index')"
                            wire:navigate
                        >
                            {{ __('car::strings.Car Companies List') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item
                            icon:trailing="plus"
                            :href="route('companies.create')"
                            :current="request()->routeIs('companies.create')"
                            wire:navigate
                        >
                            {{ __('car::strings.Add Car Company') }}
                        </flux:sidebar.item>
                    </flux:sidebar.group>
                    <flux:sidebar.group
                        expandable
                        :heading="__('car::strings.Colors')"
                        class="grid"
                        icon="swatch"
                        :expanded="request()->routeIs('colors.*')"
                    >
                        <flux:sidebar.item
                            :href="route('colors.index')"
                            :current="request()->routeIs('colors.index')"
                            wire:navigate
                        >
                            {{ __('car::strings.Colors List') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item
                            icon:trailing="plus"
                            :href="route('colors.create')"
                            :current="request()->routeIs('colors.create')"
                            wire:navigate
                        >
                            {{ __('car::strings.Add Color') }}
                        </flux:sidebar.item>
                    </flux:sidebar.group>
                </flux:sidebar.group>
                <flux:sidebar.group
                    expandable
                    icon="user-group"
                    :heading="__('user::strings.User')"
                    class="grid first-level"
                    :expanded="request()->routeIs(['users.*'])"
                >
                    <flux:sidebar.group
                        expandable
                        :heading="__('user::strings.Users')"
                        class="grid"
                        icon="user"
                        :expanded="request()->routeIs('users.*')"
                    >
                        <flux:sidebar.item
                            :href="route('users.index')"
                            :current="request()->routeIs('users.index')"
                            wire:navigate
                        >
                            {{ __('user::strings.Users List') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item
                            :href="route('users.create')"
                            icon:trailing="plus"
                            :current="request()->routeIs('users.create')"
                            wire:navigate
                        >
                            {{ __('user::strings.Add User') }}
                        </flux:sidebar.item>
                    </flux:sidebar.group>
                </flux:sidebar.group>
                <flux:sidebar.group
                    expandable
                    icon="shopping-bag"
                    :heading="__('order::strings.Orders')"
                    class="grid first-level"
                    :expanded="request()->routeIs(['orders.*', 'payments.*'])"
                >
                    <flux:sidebar.group
                        expandable
                        :heading="__('order::strings.Orders')"
                        class="grid"
                        icon="shopping-cart"
                        :expanded="request()->routeIs('orders.*')"
                    >
                        <flux:sidebar.item
                            :href="route('orders.index')"
                            :current="request()->routeIs('orders.index')"
                            wire:navigate
                        >
                            {{ __('order::strings.Orders') }}
                        </flux:sidebar.item>
                    </flux:sidebar.group>
                    <flux:sidebar.group
                        expandable
                        :heading="__('order::strings.Payments')"
                        class="grid"
                        icon="banknotes"
                        :expanded="request()->routeIs('payments.*')"
                    >
                        <flux:sidebar.item
                            :href="route('payments.index')"
                            :current="request()->routeIs('payments.index')"
                            wire:navigate
                        >
                            {{ __('order::strings.Payments List') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item
                            icon:trailing="plus"
                            :href="route('payments.create')"
                            :current="request()->routeIs('payments.create')"
                            wire:navigate
                        >
                            {{ __('order::strings.Submit Payment') }}
                        </flux:sidebar.item>
                    </flux:sidebar.group>
                </flux:sidebar.group>
                <flux:sidebar.group
                    expandable
                    icon="wrench"
                    :heading="__('dashboard::strings.General')"
                    class="grid first-level"
                    :expanded="request()->routeIs(['countries.*', 'provinces.*', 'cities.*', 'categories.*', 'system-settings.*', 'jocko-control-settings.*'])"
                >
                    <flux:sidebar.item
                        icon="globe-alt"
                        :href="route('countries.index')"
                        :current="request()->routeIs('countries.index')"
                        wire:navigate
                    >
                        {{ __('dashboard::strings.Countries') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="signal" :href="route('jocko-control-settings.edit')" :current="request()->routeIs('jocko-control-settings.edit')" wire:navigate>
                        {{ __('dashboard::strings.Jocko Control') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item
                        icon="map-pin"
                        :href="route('provinces.index')"
                        :current="request()->routeIs('provinces.index')"
                        wire:navigate
                    >
                        {{ __('dashboard::strings.Provinces') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item
                        icon="building-office"
                        :href="route('cities.index')"
                        :current="request()->routeIs('cities.index')"
                        wire:navigate
                    >
                        {{ __('dashboard::strings.Cities') }}
                    </flux:sidebar.item>
                    <flux:sidebar.group
                        expandable
                        :heading="__('dashboard::strings.Categories')"
                        class="grid"
                        icon="folder-open"
                        :expanded="request()->routeIs(['categories.*'])"
                    >
                        <flux:sidebar.item
                            :href="route('categories.index')"
                            :current="request()->routeIs('categories.index')"
                            wire:navigate
                        >
                            {{ __('dashboard::strings.Categories List') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item
                            icon:trailing="plus"
                            :href="route('categories.create')"
                            :current="request()->routeIs('categories.create')"
                            wire:navigate
                        >
                            {{ __('dashboard::strings.Category Create') }}
                        </flux:sidebar.item>
                    </flux:sidebar.group>
                    <flux:sidebar.item
                        icon="key"
                        :href="route('system-settings.edit')"
                        :current="request()->routeIs('system-settings.edit')"
                        wire:navigate
                    >
                        {{ __('dashboard::strings.System Settings') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>
            </flux:sidebar.group>

        </flux:sidebar.nav>

        <flux:spacer />

        <flux:sidebar.nav>
            <flux:sidebar.item
                icon="folder-git-2"
                href="https://github.com/laravel/livewire-starter-kit"
                target="_blank"
            >
                {{ __('Repository') }}
            </flux:sidebar.item>

            <flux:sidebar.item
                icon="book-open-text"
                href="https://laravel.com/docs/starter-kits#livewire"
                target="_blank"
            >
                {{ __('Documentation') }}
            </flux:sidebar.item>
        </flux:sidebar.nav>

        <x-dashboard::desktop-user-menu
            class="hidden lg:block"
            :name="auth()->user()->fullName()"
            :initials="auth()->user()->initials()"
        />
    </flux:sidebar>

    <!-- Mobile User Menu -->
    <flux:header class="lg:hidden">
        <flux:sidebar.toggle
            class="lg:hidden"
            icon="bars-2"
            inset="left"
        />

        <flux:spacer />

        <flux:dropdown
            position="top"
            align="end"
        >
            <flux:profile
                :initials="auth()->user()->initials()"
                icon-trailing="chevron-down"
            />

            <flux:menu>
                <flux:menu.radio.group>
                    <div class="p-0 text-sm font-normal">
                        <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                            <flux:avatar
                                :name="auth()->user()->name"
                                :initials="auth()->user()->initials()"
                            />

                            <div class="grid flex-1 text-start text-sm leading-tight">
                                <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                            </div>
                        </div>
                    </div>
                </flux:menu.radio.group>

                <flux:menu.separator />

                <flux:menu.radio.group>
                    <flux:menu.item
                        :href="route('profile.edit')"
                        icon="cog"
                        wire:navigate
                    >
                        {{ __('dashboard::strings.Settings') }}
                    </flux:menu.item>
                </flux:menu.radio.group>

                <flux:menu.separator />

                <form
                    method="POST"
                    action="{{ route('logout') }}"
                    class="w-full"
                >
                    @csrf
                    <flux:menu.item
                        as="button"
                        type="submit"
                        icon="arrow-right-start-on-rectangle"
                        class="w-full cursor-pointer"
                        data-test="logout-button"
                    >
                        {{ __('dashboard::strings.Log Out') }}
                    </flux:menu.item>
                </form>
            </flux:menu>
        </flux:dropdown>
    </flux:header>

    {{ $slot }}

    @persist('toast')
        <flux:toast.group>
            <flux:toast />
        </flux:toast.group>
    @endpersist

    @fluxScripts

</body>

</html>
