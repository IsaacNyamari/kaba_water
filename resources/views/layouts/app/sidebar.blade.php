<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    @include('partials.head')
</head>

<body class="min-h-screen bg-white dark:bg-zinc-800">
    @php
        $user = auth()->user();
        $homeRoute = $user?->homeRoute() ?? url('/');
        $modules = \App\Support\Modules::for($user);
        $currentModule = \App\Support\Modules::current();
    @endphp

    <flux:sidebar sticky collapsible="mobile"
        class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
        <flux:sidebar.header>
            <x-app-logo :sidebar="true" :href="$homeRoute" wire:navigate />
            <div class="ms-auto hidden lg:block">
                <livewire:notification-bell />
            </div>
            <flux:sidebar.collapse class="lg:hidden" />
        </flux:sidebar.header>

        {{-- Module switcher (Water Billing ⇄ Project Management) --}}
        @if (count($modules) > 1)
            <flux:dropdown position="bottom" align="start" class="w-full">
                <button type="button" data-test="module-switcher"
                    class="flex w-full items-center gap-3 rounded-xl bg-white px-3 py-2 text-start shadow-xs ring-1 ring-zinc-200 transition hover:ring-brand-400 dark:bg-white/5 dark:ring-white/10">
                    <span class="flex size-8 shrink-0 items-center justify-center rounded-lg {{ $currentModule === 'projects' ? 'bg-amber-500' : 'bg-brand-500' }} text-white">
                        <flux:icon :name="$modules[$currentModule]['icon'] ?? 'squares-2x2'" variant="mini" class="size-4" />
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-[11px] font-medium uppercase tracking-wide text-zinc-500">{{ __('Module') }}</span>
                        <span class="block truncate text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ $modules[$currentModule]['name'] ?? __('Choose a module') }}</span>
                    </span>
                    <flux:icon.chevron-up-down variant="mini" class="size-4 text-zinc-400" />
                </button>

                <flux:menu class="min-w-64">
                    @foreach ($modules as $key => $module)
                        <flux:menu.item :href="$module['url']" :icon="$module['icon']" wire:navigate
                            :icon-trailing="$key === $currentModule ? 'check' : null">
                            {{ $module['name'] }}
                        </flux:menu.item>
                    @endforeach
                    <flux:menu.separator />
                    <flux:menu.item :href="route('modules')" icon="squares-2x2" wire:navigate>{{ __('All modules') }}</flux:menu.item>
                </flux:menu>
            </flux:dropdown>
        @endif

        <flux:sidebar.nav>

            {{-- ═══════════ PROJECT MANAGEMENT ═══════════ --}}
            @if ($currentModule === 'projects' || (isset($modules['projects']) && ! isset($modules['water'])))
                <flux:sidebar.group :heading="__('Projects')" class="grid">
                    <flux:sidebar.item icon="squares-2x2" :href="route('projects.index')"
                        :current="request()->routeIs('projects.index')" wire:navigate>
                        {{ __('Dashboard & projects') }}
                    </flux:sidebar.item>
                    @can('projects.manage')
                        <flux:sidebar.item icon="plus-circle" :href="route('projects.create')"
                            :current="request()->routeIs('projects.create')" wire:navigate>
                            {{ __('New project') }}
                        </flux:sidebar.item>
                    @endcan
                </flux:sidebar.group>

                @canany(['vendor.suppliers.view', 'vendor.assets.view', 'vendor.team.manage'])
                    <flux:sidebar.group :heading="__('Shared')" class="grid">
                        @can('vendor.suppliers.view')
                            <flux:sidebar.item icon="truck" :href="route('vendor.suppliers.index')" wire:navigate>{{ __('Suppliers') }}</flux:sidebar.item>
                        @endcan
                        @can('vendor.assets.view')
                            <flux:sidebar.item icon="building-office" :href="route('vendor.assets.index')" wire:navigate>{{ __('Equipment & assets') }}</flux:sidebar.item>
                        @endcan
                        @can('vendor.team.manage')
                            <flux:sidebar.item icon="user-group" :href="route('vendor.team.index')" wire:navigate>{{ __('Team') }}</flux:sidebar.item>
                        @endcan
                    </flux:sidebar.group>
                @endcanany
            @endif

            @if ($currentModule === 'water')

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

                    @php $pendingAccess = \App\Models\AccessRequest::pending()->count(); @endphp
                    <flux:sidebar.item icon="user-plus" :href="route('admin.access-requests.index')"
                        :current="request()->routeIs('admin.access-requests.*')"
                        :badge="$pendingAccess ?: null" wire:navigate>
                        {{ __('Access Requests') }}
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="credit-card" :href="route('admin.plans.index')"
                        :current="request()->routeIs('admin.plans.*')" wire:navigate>
                        {{ __('Plans') }}
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="adjustments-horizontal" :href="route('admin.settings.index')"
                        :current="request()->routeIs('admin.settings.*')">
                        {{ __('Settings') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>
            @endrole

            {{-- ═══════════ VENDOR ═══════════ --}}
            {{-- The vendor's whole team; each link below checks its own permission. --}}
            @hasanyrole('vendor|vendor_staff|technician|meter_reader|accountant')
                <flux:sidebar.group :heading="__('Workspace')" class="grid">
                    @can('vendor.dashboard.view')
                        <flux:sidebar.item icon="home" :href="route('vendor.dashboard')"
                            :current="request()->routeIs('vendor.dashboard')" wire:navigate>
                            {{ __('Dashboard') }}
                        </flux:sidebar.item>
                    @endcan

                    @can('vendor.jobs.view')
                        <flux:sidebar.item icon="clipboard-document-check" :href="route('vendor.jobs.index')"
                            :current="request()->routeIs('vendor.jobs.*')" wire:navigate>
                            {{ __('My jobs') }}
                        </flux:sidebar.item>
                    @endcan

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

                @canany(['vendor.invoices.view', 'vendor.invoices.manage', 'vendor.payments.view', 'vendor.payments.manage', 'vendor.tariffs.view', 'vendor.tariffs.manage'])
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
                @endcanany

                @canany(['vendor.expenses.view', 'vendor.expenses.manage', 'vendor.suppliers.view', 'vendor.suppliers.manage', 'vendor.assets.view', 'vendor.assets.manage', 'vendor.liabilities.view', 'vendor.liabilities.manage', 'vendor.finance.view', 'vendor.reports.view'])
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
                            {{ __('Financial Reports') }}
                        </flux:sidebar.item>
                    @endcan

                    @can('vendor.reports.view')
                        <flux:sidebar.item icon="presentation-chart-line" :href="route('vendor.reports.index')"
                            :current="request()->routeIs('vendor.reports.*')" wire:navigate>
                            {{ __('Usage Reports') }}
                        </flux:sidebar.item>
                    @endcan
                </flux:sidebar.group>
                @endcanany

                @canany(['vendor.team.manage', 'vendor.settings.manage'])
                    <flux:sidebar.group :heading="__('Configuration')" class="grid">
                        @can('vendor.team.manage')
                            <flux:sidebar.item icon="user-group" :href="route('vendor.team.index')"
                                :current="request()->routeIs('vendor.team.*')" wire:navigate>
                                {{ __('Team') }}
                            </flux:sidebar.item>
                        @endcan
                        @can('vendor.settings.manage')
                            <flux:sidebar.item icon="adjustments-horizontal" :href="route('vendor.settings.index')"
                                :current="request()->routeIs('vendor.settings.*')" wire:navigate>
                                {{ __('Settings') }}
                            </flux:sidebar.item>
                        @endcan
                    </flux:sidebar.group>
                @endcanany
            @endhasanyrole

            {{-- ═══════════ ACCOUNTING (owner, accountant, staff) ═══════════ --}}
            @hasanyrole('vendor|accountant|vendor_staff')
                @can('accounting.view')
                    <flux:sidebar.group :heading="__('Accounting')" icon="calculator" class="grid">
                        <flux:sidebar.item icon="squares-2x2" :href="route('vendor.accounting.dashboard')"
                            :current="request()->routeIs('vendor.accounting.dashboard')" wire:navigate>
                            {{ __('Overview') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="list-bullet" :href="route('vendor.accounting.accounts.index')"
                            :current="request()->routeIs('vendor.accounting.accounts.*')" wire:navigate>
                            {{ __('Chart of Accounts') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="book-open" :href="route('vendor.accounting.journal.index')"
                            :current="request()->routeIs('vendor.accounting.journal.*')" wire:navigate>
                            {{ __('Journal Entries') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="calendar-days" :href="route('vendor.accounting.periods.index')"
                            :current="request()->routeIs('vendor.accounting.periods.*')" wire:navigate>
                            {{ __('Fiscal Periods') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="flag" :href="route('vendor.accounting.opening-balances')"
                            :current="request()->routeIs('vendor.accounting.opening-balances')" wire:navigate>
                            {{ __('Opening Balances') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="scale" :href="route('vendor.accounting.reports.trial-balance')"
                            :current="request()->routeIs('vendor.accounting.reports.trial-balance')" wire:navigate>
                            {{ __('Trial Balance') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="presentation-chart-line" :href="route('vendor.accounting.reports.income-statement')"
                            :current="request()->routeIs('vendor.accounting.reports.income-statement')" wire:navigate>
                            {{ __('Income Statement') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="building-library" :href="route('vendor.accounting.reports.balance-sheet')"
                            :current="request()->routeIs('vendor.accounting.reports.balance-sheet')" wire:navigate>
                            {{ __('Balance Sheet') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="arrows-right-left" :href="route('vendor.accounting.reports.cash-flow')"
                            :current="request()->routeIs('vendor.accounting.reports.cash-flow')" wire:navigate>
                            {{ __('Cash Flow') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="document-magnifying-glass" :href="route('vendor.accounting.reports.general-ledger')"
                            :current="request()->routeIs('vendor.accounting.reports.general-ledger')" wire:navigate>
                            {{ __('General Ledger') }}
                        </flux:sidebar.item>
                    </flux:sidebar.group>
                @endcan
            @endhasanyrole

            @endif

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

                    @can('client.work_orders.view')
                        <flux:sidebar.item icon="wrench-screwdriver" :href="route('portal.work-orders.index')"
                            :current="request()->routeIs('portal.work-orders.*')" wire:navigate>
                            {{ __('Work Orders') }}
                        </flux:sidebar.item>
                    @endcan
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

        <livewire:notification-bell :toasts="false" />

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
