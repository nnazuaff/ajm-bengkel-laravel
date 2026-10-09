<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Ajukan booking servis motor dan lihat riwayat pekerjaan melalui akun pelanggan.">
    <title>{{ $workshop->name }}</title>
    @include('partials.favicon')
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.ts'])
    @fluxAppearance
</head>
<body class="min-h-screen bg-zinc-50 font-sans text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">
    <a href="#main-content" class="workshop-skip-link">Langsung ke konten</a>
    <header class="border-b border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
        <nav aria-label="Navigasi utama" class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 px-5 py-5 sm:px-8">
            @if ($workshop->horizontalLogoUrl())
                <x-app-logo href="{{ route('home') }}" />
            @else
                <a href="{{ route('home') }}" class="flex min-w-0 items-center gap-3 font-semibold" data-workshop-brand>
                    @if ($logoUrl)<img src="{{ $logoUrl }}" alt="" width="44" height="44" class="h-11 w-11 object-contain">@endif
                    <span class="break-words">AJM Bengkel</span>
                </a>
            @endif
            <div class="flex flex-wrap items-center gap-5 text-sm">
                <a href="#cara-booking">Cara booking</a>
                <a href="{{ route('check-in') }}" wire:navigate>Check-in di bengkel</a>
                @auth
                    <a href="{{ route('dashboard') }}" class="font-semibold underline underline-offset-4">Buka akun</a>
                @else
                    <a href="{{ route('login') }}">Masuk</a>
                    @if (Route::has('register'))<a href="{{ route('register') }}" class="font-semibold underline underline-offset-4">Daftar akun</a>@endif
                @endauth
            </div>
        </nav>
    </header>
    <main id="main-content" class="mx-auto max-w-6xl px-5 sm:px-8">
        <section class="grid gap-10 py-12 md:grid-cols-2 md:items-center md:py-20" aria-labelledby="home-heading">
            <div>
                <p class="mb-4 text-sm font-medium uppercase tracking-widest text-zinc-500 dark:text-zinc-400">Servis motor · akun pelanggan</p>
                <h1 id="home-heading" class="max-w-xl text-4xl font-semibold leading-tight tracking-tight sm:text-5xl">Urus jadwal servis.<br>Lihat hasil pekerjaannya.</h1>
                <p class="mt-6 max-w-lg text-base leading-relaxed text-zinc-600 dark:text-zinc-400">Ceritakan keluhan motor dan ajukan waktu kedatangan. Setelah akun terhubung dengan data bengkel, progres servis, foto pekerjaan, dan bon bisa Anda lihat di satu tempat.</p>
                <a href="{{ route('booking.guest') }}" class="mt-8 inline-flex min-h-11 items-center rounded-lg bg-zinc-900 px-5 py-3 text-sm font-semibold text-white dark:bg-white dark:text-zinc-900">Ajukan booking servis</a>
                <p class="mt-3 text-sm text-zinc-500 dark:text-zinc-400">Tanpa akun. Permintaan menunggu konfirmasi bengkel.</p>
            </div>
            <aside class="rounded-xl border border-zinc-200 bg-white p-6 sm:p-8 dark:border-zinc-800 dark:bg-zinc-900" aria-labelledby="transparency-heading">
                <h2 id="transparency-heading" class="text-xl font-semibold">Catatan servis, bukan sekadar kabar.</h2>
                <dl class="mt-6 divide-y divide-zinc-200 dark:divide-zinc-800">
                    <div class="py-4"><dt class="font-medium">Progres dan riwayat</dt><dd class="mt-1 text-sm leading-relaxed text-zinc-600 dark:text-zinc-400">Keluhan, diagnosis, pekerjaan, dan suku cadang yang dicatat bengkel.</dd></div>
                    <div class="py-4"><dt class="font-medium">Dokumentasi pekerjaan</dt><dd class="mt-1 text-sm leading-relaxed text-zinc-600 dark:text-zinc-400">Foto yang tersedia hanya dapat diakses melalui akun pemilik data.</dd></div>
                    <div class="py-4"><dt class="font-medium">Bon digital</dt><dd class="mt-1 text-sm leading-relaxed text-zinc-600 dark:text-zinc-400">Rincian biaya, status pembayaran, dan unduhan gambar bon setelah diterbitkan.</dd></div>
                </dl>
                <p class="mt-4 border-t border-zinc-200 pt-4 text-sm text-zinc-500 dark:border-zinc-800 dark:text-zinc-400">Baru membuat akun? Hubungi staf untuk verifikasi kepemilikan motor. Data tidak ditautkan otomatis.</p>
            </aside>
        </section>
        <section id="cara-booking" class="border-t border-zinc-200 py-10 dark:border-zinc-800" aria-labelledby="booking-heading">
            <h2 id="booking-heading" class="text-2xl font-semibold">Sebelum datang ke bengkel</h2>
            <ol class="mt-6 grid gap-6 md:grid-cols-3">
                <li><p class="text-sm text-zinc-500">01 / Akun</p><h3 class="mt-2 font-semibold">Pilih cara booking</h3><p class="mt-2 text-sm leading-relaxed text-zinc-600 dark:text-zinc-400">Booking tanpa akun atau masuk untuk menggunakan data kendaraan tersimpan.</p></li>
                <li><p class="text-sm text-zinc-500">02 / Permintaan</p><h3 class="mt-2 font-semibold">Isi data motor dan keluhan</h3><p class="mt-2 text-sm leading-relaxed text-zinc-600 dark:text-zinc-400">Pilih tanggal dan waktu kedatangan yang ingin Anda ajukan.</p></li>
                <li><p class="text-sm text-zinc-500">03 / Konfirmasi</p><h3 class="mt-2 font-semibold">Periksa status booking</h3><p class="mt-2 text-sm leading-relaxed text-zinc-600 dark:text-zinc-400">Petugas menghubungi kontak booking. Jika memakai akun, status juga tersedia di Booking saya.</p></li>
            </ol>
        </section>
        @if (filled($workshop->phone) || filled($workshop->address))
            <section class="mb-10 rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900" aria-labelledby="contact-heading">
                <h2 id="contact-heading" class="text-xl font-semibold">Hubungi bengkel</h2>
                <dl class="mt-4 grid gap-4 md:grid-cols-2">
                    @if (filled($workshop->phone))<div><dt class="text-sm text-zinc-500">Telepon / WhatsApp</dt><dd class="mt-1 break-words">{{ $workshop->phone }}</dd></div>@endif
                    @if (filled($workshop->address))<div><dt class="text-sm text-zinc-500">Alamat</dt><dd class="mt-1 whitespace-pre-line break-words">{{ $workshop->address }}</dd></div>@endif
                </dl>
            </section>
        @endif
    </main>
    <footer class="border-t border-zinc-200 px-5 py-6 text-sm text-zinc-500 dark:border-zinc-800 dark:text-zinc-400"><div class="mx-auto max-w-6xl">{{ $workshop->name }}</div></footer>
    @fluxScripts
</body>
</html>
