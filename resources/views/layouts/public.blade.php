<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>@include('partials.head')</head>
<body class="workshop-shell min-h-screen bg-zinc-50 text-zinc-800 antialiased dark:bg-zinc-950 dark:text-zinc-100">
    <a href="#main-content" class="workshop-skip-link">Langsung ke formulir</a>
    <header class="border-b border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
        <nav aria-label="Navigasi utama" class="mx-auto flex max-w-3xl flex-wrap items-center justify-between gap-3 p-4">
            <x-app-logo href="{{ route('home') }}" wire:navigate />
            <div class="flex flex-wrap gap-4 text-sm"><a href="{{ route('home') }}" wire:navigate>Beranda</a>@auth<a href="{{ route('dashboard') }}" wire:navigate>Akun saya</a>@else<a href="{{ route('login') }}" wire:navigate>Masuk</a>@endauth</div>
        </nav>
    </header>
    <main id="main-content" tabindex="-1" class="mx-auto w-full max-w-3xl p-4 sm:p-6">{{ $slot }}</main>
    @fluxScripts
</body>
</html>
