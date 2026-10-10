<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="workshop-shell min-h-screen bg-zinc-50 text-zinc-800 antialiased dark:bg-zinc-950 dark:text-zinc-100">
        <a href="#main-content" class="workshop-skip-link">{{ __('Langsung ke konten') }}</a>

        <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <x-theme-toggle class="hidden lg:inline-flex" />
                <flux:sidebar.toggle class="lg:hidden" icon="x-mark" :aria-label="__('Tutup navigasi')" />
            </flux:sidebar.header>

            <flux:sidebar.nav :aria-label="__('Navigasi utama')" class="gap-4 [&_[data-flux-sidebar-group]>div:first-child]:text-xs [&_[data-flux-sidebar-group]>div:first-child]:tracking-wide [&_[data-flux-sidebar-group]>div:first-child]:text-zinc-500 dark:[&_[data-flux-sidebar-group]>div:first-child]:text-zinc-400">
                @can('customer-portal')
                    <flux:sidebar.group heading="Akun pelanggan" data-sidebar-section="customer">
                        <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard', 'portal')" wire:navigate>Dashboard</flux:sidebar.item>
                        <flux:sidebar.item icon="qr-code" :href="route('check-in')">Check-in</flux:sidebar.item>
                        <flux:sidebar.item icon="calendar" :href="route('booking.mine')" :current="request()->routeIs('booking.mine')" wire:navigate>Booking saya</flux:sidebar.item>
                    </flux:sidebar.group>
                @else
                    <flux:sidebar.group heading="Ringkasan" data-sidebar-section="summary">
                        <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>Dashboard</flux:sidebar.item>
                    </flux:sidebar.group>
                @endcan

                @can('work-services')
                    <flux:sidebar.group heading="Operasional" data-sidebar-section="operations">
                        <flux:sidebar.item icon="qr-code" :href="route('check-ins.index')" :current="request()->routeIs('check-ins.*')" wire:navigate>Customer check-in</flux:sidebar.item>
                        @can('manage-workshop')
                            <flux:sidebar.item icon="calendar" :href="route('bookings.index')" :current="request()->routeIs('bookings.*')" wire:navigate>Booking</flux:sidebar.item>
                        @endcan
                        <flux:sidebar.item icon="wrench" :href="route('services.index')" :current="request()->routeIs('services.*')" wire:navigate>Servis</flux:sidebar.item>
                        @can('manage-workshop')
                            <flux:sidebar.item icon="clock" :href="route('history.index')" :current="request()->routeIs('history.*')" wire:navigate>Riwayat servis</flux:sidebar.item>
                        @endcan
                    </flux:sidebar.group>
                @endcan

                @can('manage-workshop')
                    <flux:sidebar.group heading="Data bengkel" data-sidebar-section="masters">
                        <flux:sidebar.item icon="users" :href="route('customers.index')" :current="request()->routeIs('customers.*')" wire:navigate>Pelanggan</flux:sidebar.item>
                        <flux:sidebar.item icon="truck" :href="route('vehicles.index')" :current="request()->routeIs('vehicles.*')" wire:navigate>Kendaraan</flux:sidebar.item>
                        <flux:sidebar.item icon="archive-box" :href="route('inventory.index')" :current="request()->routeIs('inventory.*')" wire:navigate>Inventori</flux:sidebar.item>
                        <flux:sidebar.item icon="users" :href="route('mechanics.index')" :current="request()->routeIs('mechanics.*')" wire:navigate>Mekanik / staf</flux:sidebar.item>
                    </flux:sidebar.group>
                    <flux:sidebar.group heading="Keuangan" data-sidebar-section="finance">
                        <flux:sidebar.item icon="document-text" :href="route('receipts.index')" :current="request()->routeIs('receipts.*')" wire:navigate>Bon / penjualan</flux:sidebar.item>
                        <flux:sidebar.item icon="banknotes" :href="route('payments.index')" :current="request()->routeIs('payments.*')" wire:navigate>Pembayaran</flux:sidebar.item>
                        <flux:sidebar.item icon="chart-bar" :href="route('reports.index')" :current="request()->routeIs('reports.*')" wire:navigate>Laporan</flux:sidebar.item>
                    </flux:sidebar.group>
                    <flux:sidebar.group heading="Pengelolaan" data-sidebar-section="management">
                        <flux:sidebar.item icon="clipboard-document-list" :href="route('audit.index')" :current="request()->routeIs('audit.*')" wire:navigate>Audit log</flux:sidebar.item>
                        @can('manage-users')
                            <flux:sidebar.item icon="cog" :href="route('workshop-settings.edit')" :current="request()->routeIs('workshop-settings.*')" wire:navigate>Identitas bengkel</flux:sidebar.item>
                        @endcan
                    </flux:sidebar.group>
                @endcan
            </flux:sidebar.nav>

            <flux:spacer />

            <x-desktop-user-menu class="hidden lg:block border-t border-zinc-200 pt-3 dark:border-zinc-800" />
        </flux:sidebar>

        <flux:header class="border-b border-zinc-200 bg-white px-4 lg:hidden dark:border-zinc-800 dark:bg-zinc-900">
            <flux:sidebar.toggle icon="bars-2" inset="left" :aria-label="__('Buka navigasi')" />
            <x-app-logo class="ms-2 !w-auto !max-w-32" href="{{ route('dashboard') }}" wire:navigate />
            <flux:spacer />

            <x-theme-toggle class="me-2" />
            <flux:dropdown position="bottom" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon:trailing="chevron-down"
                    :aria-label="__('Menu akun')"
                />

                <flux:menu>
                    <div class="flex min-w-0 items-center gap-3 px-2 py-2 text-start text-sm">
                        <flux:avatar :name="auth()->user()->name" :initials="auth()->user()->initials()" size="sm" />
                        <div class="grid min-w-0 flex-1 gap-0.5">
                            <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                            <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                        </div>
                    </div>
                    <flux:menu.separator />
                    <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                        {{ __('Pengaturan akun') }}
                    </flux:menu.item>
                    <flux:menu.separator />
                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                            data-test="logout-button"
                        >
                            {{ __('Keluar') }}
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
