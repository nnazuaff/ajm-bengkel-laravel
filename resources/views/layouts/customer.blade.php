<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>@include('partials.head')</head>
<body class="workshop-shell ajm-customer min-h-screen antialiased">
    <a href="#main-content" class="workshop-skip-link">Langsung ke konten</a>
    <x-customer-navbar />
    <main id="main-content" tabindex="-1" class="ajm-customer-main mx-auto w-full max-w-6xl">{{ $slot }}</main>
    @fluxScripts
</body>
</html>
