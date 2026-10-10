<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="workshop-auth ajm-customer min-h-screen antialiased">
        <a href="#main-content" class="workshop-skip-link">{{ __('Langsung ke formulir') }}</a>

        <x-customer-navbar />
        <div class="ajm-auth-container">
            <div class="ajm-auth-grid">
                <aside class="ajm-auth-context">
                    <a href="{{ route('home') }}" class="flex min-w-0 items-center gap-3 rounded-md" wire:navigate>
                        <span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900">
                            <x-app-logo-icon class="size-8" />
                        </span>
                        <span class="min-w-0">
                            <span class="block truncate text-base font-semibold">{{ config('app.name') }}</span>
                            <span class="block text-xs text-zinc-600 dark:text-zinc-400">{{ __('Akun bengkel dan pelanggan') }}</span>
                        </span>
                    </a>

                    <div class="hidden space-y-3 lg:block">
                        <p class="text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Akses akun') }}</p>
                        <flux:heading size="lg" level="2">{{ __('Untuk staf dan pelanggan') }}</flux:heading>
                        <flux:text>{{ __('Booking, progres servis, foto pekerjaan, dan bon bisa dilihat di akunmu. Akun staf dibuat oleh owner.') }}</flux:text>
                    </div>
                </aside>

                <main id="main-content" tabindex="-1" class="ajm-auth-form">
                    {{ $slot }}
                </main>
            </div>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
