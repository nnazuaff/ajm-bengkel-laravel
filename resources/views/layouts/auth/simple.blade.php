<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="workshop-auth min-h-screen bg-zinc-50 text-zinc-800 antialiased dark:bg-zinc-950 dark:text-zinc-100">
        <a href="#main-content" class="workshop-skip-link">{{ __('Langsung ke formulir') }}</a>

        <div class="flex min-h-svh items-center justify-center p-4 sm:p-6">
            <div class="grid w-full max-w-md overflow-hidden rounded-xl border border-zinc-200 bg-white lg:max-w-4xl lg:grid-cols-2 dark:border-zinc-800 dark:bg-zinc-900">
                <aside class="flex flex-col gap-8 border-b border-zinc-200 bg-zinc-50 p-6 sm:p-8 lg:border-e lg:border-b-0 dark:border-zinc-800 dark:bg-zinc-900">
                    <a href="{{ route('home') }}" class="flex min-w-0 items-center gap-3 rounded-md" wire:navigate>
                        <span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900">
                            <x-app-logo-icon class="size-8" />
                        </span>
                        <span class="min-w-0">
                            <span class="block truncate text-base font-semibold">{{ config('app.name') }}</span>
                            <span class="block text-xs text-zinc-600 dark:text-zinc-400">{{ __('Administrasi bengkel motor') }}</span>
                        </span>
                    </a>

                    <div class="hidden space-y-3 lg:block">
                        <p class="text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Akses akun') }}</p>
                        <flux:heading size="lg" level="2">{{ __('Untuk staf dan pelanggan') }}</flux:heading>
                        <flux:text>{{ __('Gunakan akun sesuai peran Anda. Pendaftaran umum membuat akun pelanggan, bukan akun staf.') }}</flux:text>
                    </div>
                </aside>

                <main id="main-content" tabindex="-1" class="min-w-0 bg-white p-6 sm:p-8 dark:bg-zinc-900">
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
