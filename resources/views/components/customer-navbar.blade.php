<header class="ajm-customer-header">
    <nav class="ajm-customer-nav" aria-label="Navigasi utama">
        @php($branding = \App\Models\WorkshopSetting::current())
        @if (! $branding->horizontalLogoUrl() && $branding->publicLogoUrl())
            <a href="{{ route('home') }}" wire:navigate class="flex min-w-0 items-center gap-2 text-sm font-semibold" data-workshop-brand>
                <img src="{{ $branding->publicLogoUrl() }}" alt="" width="32" height="32" class="size-8 object-contain" />
                <span>AJM Bengkel</span>
            </a>
        @else
            <x-app-logo href="{{ route('home') }}" wire:navigate class="!w-auto !max-w-40" />
        @endif
        <div class="ajm-customer-links">
            <a href="{{ route('home') }}" wire:navigate>Beranda</a>
            @auth
                @if (auth()->user()->role === \App\Enums\Role::Customer)
                    <a href="{{ route('portal') }}" wire:navigate @if(request()->routeIs('portal', 'dashboard')) aria-current="page" @endif>Dashboard</a>
                    <a href="{{ route('booking.mine') }}" wire:navigate @if(request()->routeIs('booking.mine')) aria-current="page" @endif>Booking saya</a>
                    <a href="{{ route('check-in') }}">Check-in</a>
                @else
                    <a href="{{ route('dashboard') }}" wire:navigate>Dashboard</a>
                @endif
            @else
                <a href="{{ route('booking.guest') }}" wire:navigate>Booking</a>
                <a href="{{ route('check-in') }}">Check-in</a>
                <a href="{{ route('login') }}" wire:navigate>Masuk</a>
            @endauth
        </div>
        <div class="ajm-customer-tools">
            <x-theme-toggle />
            @auth
                <details class="ajm-customer-account" data-account-menu>
                    <summary aria-label="Buka menu akun">Akun <span aria-hidden="true">+</span></summary>
                    <div class="ajm-customer-menu-content">
                        <a href="{{ route('profile.edit') }}" wire:navigate>Pengaturan akun</a>
                        <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" data-test="logout-button">Keluar</button></form>
                    </div>
                </details>
            @endauth
            <details class="ajm-customer-menu" data-home-menu>
                <summary aria-label="Buka menu navigasi">Menu <span aria-hidden="true">+</span></summary>
                <div class="ajm-customer-menu-content">
                    <a href="{{ route('home') }}" wire:navigate>Beranda</a>
                    @auth
                        <a href="{{ route('dashboard') }}" wire:navigate>Dashboard</a>
                        @if (auth()->user()->role === \App\Enums\Role::Customer)
                            <a href="{{ route('booking.mine') }}" wire:navigate>Booking saya</a>
                            <a href="{{ route('check-in') }}">Check-in</a>
                        @endif
                        <a href="{{ route('profile.edit') }}" wire:navigate>Pengaturan akun</a>
                        <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" data-test="logout-button">Keluar</button></form>
                    @else
                        <a href="{{ route('booking.guest') }}" wire:navigate>Booking</a>
                        <a href="{{ route('check-in') }}">Check-in</a>
                        <a href="{{ route('login') }}" wire:navigate>Masuk</a>
                    @endauth
                </div>
            </details>
        </div>
    </nav>
</header>
