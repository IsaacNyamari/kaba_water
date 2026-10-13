<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">

<head>
    @include('partials.head')
</head>

<body class="min-h-screen bg-white dark:bg-zinc-800">
    @php
        $user = auth()->user();
        $homeRoute = match (true) {
            $user?->hasRole('super_admin') => route('admin.dashboard'),
            $user?->hasRole('vendor') => route('vendor.dashboard'),
            $user?->hasRole('client') => route('portal.dashboard'),
            default => url('/'),
        };
    @endphp

    <flux:sidebar sticky collapsible="mobile"
        class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
        <flux:sidebar.header>
            <x-app-logo :sidebar="true" :href="$homeRoute" wire:navigate />
            <flux:sidebar.collapse class="lg:hidden" />
        </flux:sidebar.header>

        <flux:sidebar.nav>

            {{-- ═══════════ SUPER ADMIN ═══════════ --}}
            @role('super_admin')
                <flux:sidebar.group :heading="__('Platform')" class="grid">
                    <flux:sidebar.item icon="home" :href="route('admin.dashboard')"
                        :current="request()->routeIs('admin.dashboard')" wire:navigate>
                        {{ __('Dashboard') }}
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="building-office-2" :href="route('admin.vendors.index')"
                        :current="request()->routeIs('admin.vendors.*')" wire:navigate>
                        {{ __('Vendors') }}
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="credit-card" :href="route('admin.plans.index')"
                        :current="request()->routeIs('admin.plans.*')" wire:navigate>
                        {{ __('Plans') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>
            @endrole

            {{-- ═══════════ VENDOR ═══════════ --}}
            @role('vendor')
                <flux:sidebar.group :heading="__('Workspace')" class="grid">
                    <flux:sidebar.item icon="home" :href="route('vendor.dashboard')"
                        :current="request()->routeIs('vendor.dashboard')" wire:navigate>
                        {{ __('Dashboard') }}
                    </flux:sidebar.item>

                    @canany(['vendor.clients.view', 'vendor.clients.manage'])
                        <flux:sidebar.item icon="users" :href="route('vendor.clients.index')"
                            :current="request()->routeIs('vendor.clients.*')" wire:navigate>
                            {{ __('Clients') }}
                        </flux:sidebar.item>
                    @endcanany

                    @canany(['vendor.meters.view', 'vendor.meters.manage'])
                        <flux:sidebar.item icon="cog-6-tooth" :href="route('vendor.meters.index')"
                            :current="request()->routeIs('vendor.meters.*')" wire:navigate>
                            {{ __('Meters') }}
                        </flux:sidebar.item>
                    @endcanany

                    @canany(['vendor.readings.view', 'vendor.readings.manage'])
                        <flux:sidebar.item icon="chart-bar" :href="route('vendor.readings.index')"
                            :current="request()->routeIs('vendor.readings.*')" wire:navigate>
                            {{ __('Readings') }}
                        </flux:sidebar.item>
                    @endcanany

                    @canany(['vendor.work_orders.view', 'vendor.work_orders.manage'])
                        <flux:sidebar.item icon="wrench-screwdriver" :href="route('vendor.work-orders.index')"
                            :current="request()->routeIs('vendor.work-orders.*')" wire:navigate>
                            {{ __('Work Orders') }}
                        </flux:sidebar.item>
                    @endcanany
                </flux:sidebar.group>

                <flux:sidebar.group :heading="__('Billing')" class="grid">
                    @canany(['vendor.invoices.view', 'vendor.invoices.manage'])
                        <flux:sidebar.item icon="document-text" :href="route('vendor.invoices.index')"
                            :current="request()->routeIs('vendor.invoices.*')" wire:navigate>
                            {{ __('Invoices') }}
                        </flux:sidebar.item>
                    @endcanany

                    @canany(['vendor.payments.view', 'vendor.payments.manage'])
                        <flux:sidebar.item icon="banknotes" :href="route('vendor.payments.index')"
                            :current="request()->routeIs('vendor.payments.*')" wire:navigate>
                            {{ __('Payments') }}
                        </flux:sidebar.item>
                    @endcanany

                    @canany(['vendor.tariffs.view', 'vendor.tariffs.manage'])
                        <flux:sidebar.item icon="currency-dollar" :href="route('vendor.tariffs.index')"
                            :current="request()->routeIs('vendor.tariffs.*')" wire:navigate>
                            {{ __('Tariffs') }}
                        </flux:sidebar.item>
                    @endcanany
                </flux:sidebar.group>

                <flux:sidebar.group :heading="__('Finance')" class="grid">
                    @canany(['vendor.expenses.view', 'vendor.expenses.manage'])
                        <flux:sidebar.item icon="receipt-percent" :href="route('vendor.expenses.index')"
                            :current="request()->routeIs('vendor.expenses.*')" wire:navigate>
                            {{ __('Expenses') }}
                        </flux:sidebar.item>
                    @endcanany

                    @canany(['vendor.suppliers.view', 'vendor.suppliers.manage'])
                        <flux:sidebar.item icon="truck" :href="route('vendor.suppliers.index')"
                            :current="request()->routeIs('vendor.suppliers.*')" wire:navigate>
                            {{ __('Suppliers') }}
                        </flux:sidebar.item>
                    @endcanany

                    @canany(['vendor.assets.view', 'vendor.assets.manage'])
                        <flux:sidebar.item icon="building-office" :href="route('vendor.assets.index')"
                            :current="request()->routeIs('vendor.assets.*')" wire:navigate>
                            {{ __('Assets') }}
                        </flux:sidebar.item>
                    @endcanany

                    @canany(['vendor.liabilities.view', 'vendor.liabilities.manage'])
                        <flux:sidebar.item icon="credit-card" :href="route('vendor.liabilities.index')"
                            :current="request()->routeIs('vendor.liabilities.*')" wire:navigate>
                            {{ __('Liabilities') }}
                        </flux:sidebar.item>
                    @endcanany

                    @can('vendor.finance.view')
                        <flux:sidebar.item icon="document-chart-bar" :href="route('vendor.finance.reports')"
                            :current="request()->routeIs('vendor.finance.*')" wire:navigate>
                            {{ __('Reports') }}
                        </flux:sidebar.item>
                    @endcan
                </flux:sidebar.group>

                @can('vendor.settings.manage')
                    <flux:sidebar.group :heading="__('Configuration')" class="grid">
                        <flux:sidebar.item icon="adjustments-horizontal" :href="route('vendor.settings.index')"
                            :current="request()->routeIs('vendor.settings.*')" wire:navigate>
                            {{ __('Settings') }}
                        </flux:sidebar.item>
                    </flux:sidebar.group>
                @endcan
            @endrole

            {{-- ═══════════ CLIENT ═══════════ --}}
            @role('client')
                <flux:sidebar.group :heading="__('My Account')" class="grid">
                    <flux:sidebar.item icon="home" :href="route('portal.dashboard')"
                        :current="request()->routeIs('portal.dashboard')" wire:navigate>
                        {{ __('Dashboard') }}
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="cog-6-tooth" :href="route('portal.meter')"
                        :current="request()->routeIs('portal.meter')" wire:navigate>
                        {{ __('My Meter') }}
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="chart-bar" :href="route('portal.readings.index')"
                        :current="request()->routeIs('portal.readings.*')" wire:navigate>
                        {{ __('Readings') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>

                <flux:sidebar.group :heading="__('Billing')" class="grid">
                    <flux:sidebar.item icon="document-text" :href="route('portal.invoices.index')"
                        :current="request()->routeIs('portal.invoices.*')" wire:navigate>
                        {{ __('Invoices') }}
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="banknotes" :href="route('portal.payments.index')"
                        :current="request()->routeIs('portal.payments.*')" wire:navigate>
                        {{ __('Payments') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>

                <flux:sidebar.group :heading="__('Settings')" class="grid">
                    <flux:sidebar.item icon="user-circle" :href="route('portal.profile')"
                        :current="request()->routeIs('portal.profile')" wire:navigate>
                        {{ __('Profile') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>
            @endrole

        </flux:sidebar.nav>

        <flux:spacer />

        <x-desktop-user-menu class="hidden lg:block" :name="$user->name" />
    </flux:sidebar>

    <!-- Mobile User Menu -->
    <flux:header class="lg:hidden">
        <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

        <flux:spacer />

        <flux:dropdown position="top" align="end">
            <flux:profile :initials="$user->initials()" icon-trailing="chevron-down" />

            <flux:menu>
                <flux:menu.radio.group>
                    <div class="p-0 text-sm font-normal">
                        <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                            <flux:avatar :name="$user->name" :initials="$user->initials()" />

                            <div class="grid flex-1 text-start text-sm leading-tight">
                                <flux:heading class="truncate">{{ $user->name }}</flux:heading>
                                <flux:text class="truncate">{{ $user->email }}</flux:text>
                            </div>
                        </div>
                    </div>
                </flux:menu.radio.group>

                <flux:menu.separator />

                <flux:menu.radio.group>
                    @role('client')
                        <flux:menu.item :href="route('portal.profile')" icon="cog" wire:navigate>
                            {{ __('Profile') }}
                        </flux:menu.item>
                    @else
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                            {{ __('Settings') }}
                        </flux:menu.item>
                    @endrole
                </flux:menu.radio.group>

                <flux:menu.separator />

                <form method="POST" action="{{ route('logout') }}" class="w-full">
                    @csrf
                    <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle"
                        class="w-full cursor-pointer" data-test="logout-button">
                        {{ __('Log out') }}
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
