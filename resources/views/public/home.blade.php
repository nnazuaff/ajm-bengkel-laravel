<!DOCTYPE html>
<html lang="id" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Ajukan booking servis motor dan lihat riwayat pekerjaan melalui akun pelanggan.">
    <title>{{ $workshop->name }}</title>
    @include('partials.favicon')
    @include('partials.theme')
    @fonts
    @vite(['resources/css/app.css', 'resources/css/public-home.css', 'resources/js/app.ts'])
</head>
<body class="ajm-home ajm-customer">
    @php
        $customerAccount = auth()->user()?->role === \App\Enums\Role::Customer;
        $bookingUrl = route($customerAccount ? 'booking.mine' : 'booking.guest');
    @endphp
    <a href="#main-content" class="ajm-skip">Langsung ke konten</a>
    <x-customer-navbar />
    <main id="main-content" tabindex="-1">
        <section class="ajm-container ajm-hero" aria-labelledby="home-heading">
            <div class="ajm-hero-copy">
                <p class="ajm-intro">{{ $workshop->name }}</p>
                <h1 id="home-heading">Motor mulai nggak enak dipakai?</h1>
                <p class="ajm-hero-description">Bawa ke AJM. Kami cek dulu, lalu bahas bagian yang perlu dikerjakan.</p>
                <div class="ajm-actions">
                    <a href="{{ $bookingUrl }}" class="ajm-button ajm-button-primary" data-home-booking>Booking servis</a>
                    @if ($whatsappUrl)<a href="{{ $whatsappUrl }}" class="ajm-button" target="_blank" rel="noopener noreferrer">Chat AJM</a>@endif
                </div>
            </div>
            <figure class="ajm-hero-photo">
                <img src="{{ asset('images/workshop/ajm-front-960.webp') }}"
                    srcset="{{ asset('images/workshop/ajm-front-640.webp') }} 640w, {{ asset('images/workshop/ajm-front-960.webp') }} 960w, {{ asset('images/workshop/ajm-front-1280.webp') }} 1280w"
                    sizes="(max-width: 899px) calc(100vw - 40px), 650px" width="1280" height="720"
                    alt="Bagian depan bengkel Ahad Jaya Motor" fetchpriority="high" decoding="async">
            </figure>
        </section>

        <section id="layanan" class="ajm-container ajm-section ajm-services" aria-labelledby="services-heading">
            <div>
                <h2 id="services-heading">Yang bisa kami bantu</h2>
                <p>Rem bunyi, tarikan berat, atau starter susah? Bilang kapan terasa, nanti kami cek dari situ.</p>
            </div>
            <dl class="ajm-service-list">
                <div><dt>Tune up</dt><dd>Cek busi, filter, dan setelan saat langsam mulai nggak stabil.</dd></div>
                <div><dt>Ganti oli</dt><dd>Ganti oli sesuai kebutuhan motor, sekalian lihat takaran dan rembesnya.</dd></div>
                <div><dt>Rem</dt><dd>Cek kampas, kaliper, atau tromol kalau rem bunyi atau terasa dalam.</dd></div>
                <div><dt>Rantai</dt><dd>Bersihkan dan setel rantai. Kondisi gir ikut kami lihat.</dd></div>
                <div><dt>Kelistrikan</dt><dd>Cek aki, pengisian, dan jalur kabel saat starter atau lampu melemah.</dd></div>
            </dl>
        </section>

        <section id="bengkel" class="ajm-container ajm-section" aria-labelledby="workshop-heading">
            <div class="ajm-section-heading">
                <h2 id="workshop-heading">Motornya kami lihat dulu</h2>
                <p>Kalau ada bagian yang perlu dibuka atau diganti, pekerjaan dan biayanya dibicarakan dulu.</p>
            </div>
            <div class="ajm-work-photos">
                <figure>
                    <img src="{{ asset('images/workshop/ajm-work-960.webp') }}"
                        srcset="{{ asset('images/workshop/ajm-work-640.webp') }} 640w, {{ asset('images/workshop/ajm-work-960.webp') }} 960w"
                        sizes="(max-width: 767px) calc(100vw - 40px), 700px" width="960" height="540" loading="lazy" decoding="async" alt="Motor yang sedang dibongkar dan dikerjakan di bengkel AJM">
                    <figcaption>Pengecekan dan pengerjaan di bengkel.</figcaption>
                </figure>
                <figure>
                    <img src="{{ asset('images/workshop/ajm-workshop-640.webp') }}"
                        srcset="{{ asset('images/workshop/ajm-workshop-640.webp') }} 640w, {{ asset('images/workshop/ajm-workshop-960.webp') }} 960w"
                        sizes="(max-width: 767px) calc(100vw - 40px), 480px" width="640" height="360" loading="lazy" decoding="async" alt="Motor dan perlengkapan di dalam bengkel AJM">
                    <figcaption>Motor dan perlengkapan servis di AJM.</figcaption>
                </figure>
            </div>
            <div id="cara-booking" class="ajm-visit">
                <div><h3>Mau datang di waktu tertentu?</h3><p>Booking dulu, isi data motor dan keluhannya. Petugas akan mengonfirmasi jadwal lewat kontak yang kamu berikan. Tanpa akun juga bisa.</p></div>
                <div><h3>Sudah sampai di bengkel?</h3><p>Scan QR atau buka check-in, lalu minta kode ke petugas. Setelah diterima, progres servis, foto pekerjaan, dan bon bisa dilihat di akunmu.</p></div>
            </div>
        </section>

        <section class="ajm-container ajm-section ajm-product" aria-labelledby="product-heading">
            <div>
                <h2 id="product-heading">Dudukan shockbreaker custom</h2>
                <p>Dudukan shockbreaker mobil sudah aus? Bawa contohnya ke AJM. Ukurannya dilihat dulu sebelum membahas bahan dan harga.</p>
                @if ($whatsappUrl)<a href="{{ $whatsappUrl }}" class="ajm-text-link" target="_blank" rel="noopener noreferrer">Chat AJM <span aria-hidden="true">↗</span></a>@endif
            </div>
            <div class="ajm-product-photos">
                <figure><img src="{{ asset('images/workshop/shock-front-480.webp') }}" width="480" height="852" loading="lazy" decoding="async" alt="Contoh dudukan shockbreaker depan custom AJM"><figcaption>Dudukan depan</figcaption></figure>
                <figure><img src="{{ asset('images/workshop/shock-rear-480.webp') }}" width="480" height="852" loading="lazy" decoding="async" alt="Contoh dudukan shockbreaker belakang custom AJM"><figcaption>Dudukan belakang</figcaption></figure>
            </div>
        </section>

        <section id="faq" class="ajm-container ajm-section ajm-faq" data-slot="faq" data-layout="split" aria-labelledby="faq-heading">
            <div class="ajm-faq-intro" data-slot="faq-header">
                <p class="ajm-faq-eyebrow" data-slot="faq-eyebrow">FAQ</p>
                <h2 id="faq-heading" data-slot="faq-title">Yang sering ditanyakan</h2>
                <p data-slot="faq-description">Masih bingung mau booking atau check-in? Cek jawabannya di sini.</p>
            </div>
            <div class="ajm-faq-list" data-slot="faq-list">
                <div class="ajm-faq-item" data-slot="faq-item">
                    <h3 class="ajm-faq-question"><button type="button" id="faq-trigger-1" data-slot="faq-question" aria-expanded="false" aria-controls="faq-answer-1">Booking dan check-in bedanya apa?</button></h3>
                    <div id="faq-answer-1" class="ajm-faq-answer" data-slot="faq-answer" role="region" aria-labelledby="faq-trigger-1" hidden>
                        <p>Booking untuk mengajukan jadwal sebelum datang. Isi data motor, keluhan, dan waktu kedatangan, lalu tunggu konfirmasi petugas. Check-in dilakukan saat kamu sudah di bengkel: minta kode dari petugas, isi formulir, lalu tunggu pemeriksaan dan penerimaan motor. Booking tidak otomatis menjadi check-in.</p>
                        <p><a href="{{ $bookingUrl }}">Ajukan booking</a> · <a href="{{ route('check-in') }}">Buka check-in</a></p>
                    </div>
                </div>
                <div class="ajm-faq-item" data-slot="faq-item">
                    <h3 class="ajm-faq-question"><button type="button" id="faq-trigger-2" data-slot="faq-question" aria-expanded="false" aria-controls="faq-answer-2">Kalau mau booking, perlu punya akun?</button></h3>
                    <div id="faq-answer-2" class="ajm-faq-answer" data-slot="faq-answer" role="region" aria-labelledby="faq-trigger-2" hidden><p>Tidak. Kamu bisa booking tanpa akun dengan mengisi kontak, data motor, jadwal, dan keluhan. Petugas akan menghubungi kamu untuk mengonfirmasi jadwal; mengirim formulir belum berarti jadwal sudah disetujui.</p></div>
                </div>
                <div class="ajm-faq-item" data-slot="faq-item">
                    <h3 class="ajm-faq-question"><button type="button" id="faq-trigger-3" data-slot="faq-question" aria-expanded="false" aria-controls="faq-answer-3">Sudah sampai di bengkel, bagaimana cara check-in?</button></h3>
                    <div id="faq-answer-3" class="ajm-faq-answer" data-slot="faq-answer" role="region" aria-labelledby="faq-trigger-3" hidden><p>Scan QR di bengkel atau buka halaman check-in. Minta kode bengkel 6 digit ke petugas, lalu isi formulir. Jika sudah punya akun pelanggan, masuk dulu agar tidak perlu daftar ulang. Setelah dikirim, tunggu petugas memeriksa identitas dan motor kamu.</p></div>
                </div>
                <div class="ajm-faq-item" data-slot="faq-item">
                    <h3 class="ajm-faq-question"><button type="button" id="faq-trigger-4" data-slot="faq-question" aria-expanded="false" aria-controls="faq-answer-4">Bisa langsung datang tanpa booking?</button></h3>
                    <div id="faq-answer-4" class="ajm-faq-answer" data-slot="faq-answer" role="region" aria-labelledby="faq-trigger-4" hidden><p>Bisa. Datang langsung dengan motor kamu, lalu sampaikan keluhannya ke petugas. Di bengkel kamu bisa check-in memakai kode dari petugas, atau petugas mencatat kedatanganmu sebagai walk-in.</p></div>
                </div>
                <div class="ajm-faq-item" data-slot="faq-item">
                    <h3 class="ajm-faq-question"><button type="button" id="faq-trigger-5" data-slot="faq-question" aria-expanded="false" aria-controls="faq-answer-5">Apa yang harus disiapkan sebelum motor dicek?</button></h3>
                    <div id="faq-answer-5" class="ajm-faq-answer" data-slot="faq-answer" role="region" aria-labelledby="faq-trigger-5" hidden><p>Siapkan pelat nomor, informasi motor, perkiraan kilometer saat ini, dan ceritakan gejala yang kamu rasakan. Kalau sudah booking, sebutkan nama atau kontak yang dipakai agar petugas mudah menemukan pengajuannya.</p></div>
                </div>
                <div class="ajm-faq-item" data-slot="faq-item">
                    <h3 class="ajm-faq-question"><button type="button" id="faq-trigger-6" data-slot="faq-question" aria-expanded="false" aria-controls="faq-answer-6">Setelah servis, catatannya bisa dilihat di mana?</button></h3>
                    <div id="faq-answer-6" class="ajm-faq-answer" data-slot="faq-answer" role="region" aria-labelledby="faq-trigger-6" hidden><p>Melalui akun pelanggan setelah identitas dan motor kamu diverifikasi petugas. Kamu bisa melihat progres, riwayat servis, dokumentasi pekerjaan jika tersedia, dan bon yang sudah dibuat.</p></div>
                </div>
            </div>
        </section>

        <section id="lokasi" class="ajm-container ajm-section ajm-contact" aria-labelledby="contact-heading">
            <div>
                <h2 id="contact-heading">Mau mampir ke AJM?</h2>
                @if (filled($workshop->address))<p class="ajm-address">{{ $workshop->address }}</p>
                @else<p>Tanya petugas untuk alamat dan waktu kedatangan sebelum berangkat.</p>@endif
            </div>
            <div class="ajm-contact-actions">
                <iframe class="ajm-location-map" title="Peta lokasi {{ $workshop->name }}" src="{{ $mapsEmbedUrl }}" width="640" height="360" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
                @if ($mapsUrl)<a href="{{ $mapsUrl }}" class="ajm-button" target="_blank" rel="noopener noreferrer">Lihat rute <span aria-hidden="true">↗</span></a>@endif
                @if (filled($workshop->phone))<div class="ajm-phone"><span>Telepon / WhatsApp</span><p>{{ $workshop->phone }}</p></div>@endif
            </div>
        </section>
    </main>
    <footer class="ajm-container ajm-footer">
        <span>{{ $workshop->name }}</span>
        <span>Developed by <a href="https://github.com/nnazuaff" target="_blank" rel="noopener noreferrer">nnazuaff</a></span>
        <div><a href="{{ $bookingUrl }}">Booking servis</a>@guest @if (Route::has('register'))<a href="{{ route('register') }}">Daftar akun</a>@endif @endguest</div>
    </footer>
    @fluxScripts
</body>
</html>
