# Menjalankan AJM Bengkel

AGENTS.md adalah spesifikasi. Stack: Laravel 13, Livewire 4, Fortify, Flux, Blade,
Tailwind 4, TypeScript, Vite, MySQL. Tidak ada React/Vue/Inertia.

## Kebutuhan

- PHP yang memenuhi composer.lock; PDO MySQL, BCMath, GD dengan FreeType, Fileinfo.
- MySQL 8.4. User migrasi memerlukan CREATE ROUTINE dan TRIGGER. Runtime menjalankan
  operasi domain Laravel; objek SQL tidak menggantikan transaksi aplikasi.
- Node/npm versi yang memenuhi package-lock.json.
- Verifikasi email dikendalikan AUTH_REQUIRE_EMAIL_VERIFICATION: default false saat
  APP_ENV=local, true pada testing/production. Email_verified_at tidak diisi palsu.
  Setelah SMTP siap gunakan true lalu php artisan config:clear. MAIL_MAILER=log
  hanya pengembangan; reset password tetap perlu email transport yang berfungsi.

Konfigurasi lokal ada di .env, jangan commit nilai credential. Sesudah konfigurasi:

    composer install
    npm ci
    php artisan migrate
    php artisan storage:link
    npm run build
    composer dev

Jangan menjalankan migrate:fresh pada database kerja. Untuk database baru, APP_KEY
harus dibuat sekali melalui php artisan key:generate; jangan mengganti key aplikasi
berisi akun/2FA aktif. composer setup starter kit mengganti key; jangan pakai untuk
memperbarui instalasi yang sudah dipakai.

Pemilik pertama dibuat secara tepercaya:

    php artisan workshop:create-user email@domain.test --name="Nama Pemilik" --role=owner

Password dimasukkan pada prompt tersembunyi. Tidak ada seed password bersama.
Owner dapat menambah staf, mengganti role, menonaktifkan staf melalui /mechanics.
Owner terakhir tidak dapat dinonaktifkan; penugasan aktif melindungi akses mekanik.

## Workflow operasional

1. /customers dan /vehicles untuk data master; atau /services untuk intake walk-in
   satu form. Cari plat/telepon, gunakan motor tercatat, mileage tidak boleh menurun.
2. Pelanggan login mengajukan /booking (email wajib hanya jika konfigurasi true).
   Admin /bookings meninjau, mengonfirmasi, mencatat datang. Form Terima servis
   menawarkan hubungkan akun booking dan pemulihan arsip yang ditemukan. Petugas
   wajib konfirmasi identitas/motor/hak histori bila linking atau pemulihan dipilih.
   Master/order/link/pemulihan/audit satu transaksi; ulang tidak menduplikasi order.
   Akun yang sudah terhubung ke pelanggan lain tidak direbut/ditimpa otomatis.
   Pemesan pengantar dapat diterima tanpa menghubungkan akun. Booking berikutnya
   menyediakan Pilih motor saya hanya dari kendaraan aktif milik master terhubung.
3. /services: penugasan mekanik, diagnosis, pekerjaan/jasa, part, foto pekerjaan,
   transisi status. Order tidak dapat selesai dengan pekerjaan masih terbuka.
4. /inventory: item baru stok nol, restok/koreksi beralasan. Setiap perubahan stok
   dicatat ledger; pemakaian part dan pengembalian berjalan atomik.
5. Sesudah servis selesai, buat bon lewat detail servis atau /receipts. Penjualan
   langsung tidak perlu order; pelanggan Umum diizinkan. Server menghitung harga
   produk/total, snapshot harga part tetap, bon final terkunci.
6. Catat pembayaran cash/transfer/QRIS/other melalui bon. Parsial tersedia; overpayment
   ditolak. QRIS hanya pencatatan metode, bukan integrasi payment gateway.
7. Web bon dan unduhan PNG memakai snapshot transaksi. Bukti utama tetap database.
8. Owner dapat void/reverse dengan alasan. Koreksi pembukuan bukan transfer refund
   otomatis. Void mengembalikan stok sekali dan mempertahankan dokumen/audit.
9. /history menelusuri histori plat termasuk kendaraan arsip. /payments, /reports,
   /audit membantu rekonsiliasi. Laporan penerimaan/pembalikan bukan laporan laba.
10. /workshop-settings mengatur identitas nyata bengkel; tidak ada kontak/jam buka
    rekaan. Backup database dan storage/app/private serta storage/app/public.

## Portal pelanggan

Root publik menampilkan identitas bengkel, guest booking `/booking/guest`, login,
dan check-in `/check-in`. Registrasi membuat User role customer dan membuat master
Customer baru secara atomik. Kecocokan dengan Customer offline tidak membuka histori
lama; konfirmasi identitas dan kepemilikan tetap dilakukan saat penerimaan.

Check-in publik sekaligus membuat akun memakai email unik, password dan konfirmasi.
Akun lama tidak ditimpa: pelanggan yang sudah punya akun harus login. Dashboard dan
sidebar pelanggan menyediakan Check-in dengan kontak dari akun, bukan input bebas.
Satu Customer hanya boleh mempunyai satu check-in waiting/processing, tanpa batas
umur; setelah diterima menjadi servis atau dibatalkan, boleh mengajukan lagi.

Petugas `/check-ins` generate kode enam digit, berlaku 24 jam, dapat diputar atau
dinonaktifkan. QR berisi URL saja; kode diberikan kepada pelanggan yang hadir.
Halaman menunggu polling setiap lima detik dan tetap bekerja setelah refresh.
Mekanik menerima kendaraan/KM/keluhan; akun baru yang belum mempunyai histori lama
tidak memerlukan checkbox tambahan. Klaim Customer offline lama tetap memerlukan
konfirmasi identitas, kepemilikan motor dan browser pelanggan di hadapan petugas.
Konversi order, aktivasi akun dan audit atomik. Akun identity_verified_at boleh
mengakses portal tanpa mengisi email_verified_at palsu; email transport tetap
memerlukan konfigurasi untuk verifikasi email/reset password.

Browser awal dapat memakai handoff sekali sebelum kedaluwarsa 24 jam untuk login
otomatis setelah servis diterima. Password pilihan pelanggan memungkinkan login
ulang melalui halaman login biasa. Handoff bukan link publik dan tidak disimpan
sebagai plaintext di CheckIn. Histori hanya dari Customer yang terhubung tepercaya.
Aksi Akses akun telah dihapus dari daftar pelanggan; linking legacy dipertahankan
internal untuk kompatibilitas, bukan langkah wajib operasi biasa.

Portal membaca kendaraan, progres/histori, diagnosis, pekerjaan, part, foto, bon dan
status pembayaran sendiri. Bon draft tidak dibagikan. Foto privat tidak memakai
public/storage; akses HTTP selalu diperiksa terhadap order/pelanggan milik akun.
Customer tidak mendapat akses write, daftar admin, atau harga/stok internal.

## Pemeriksaan

    php artisan test --compact
    composer lint:check
    composer types:check
    npm run types:check
    npm run test:search
    npm run build
    php scripts/verify-mysql.php
    php artisan migrate:status
    git diff --check

verify-mysql membuat database acak ajm_verify_*, melakukan migration/rollback/reapply,
rollback audit, workflow, ledger/void, PNG, function/procedure/trigger, dan race nomor,
konversi booking, stok, finalisasi/pembayaran. Memerlukan CREATE/DROP DATABASE dan
pcntl. Cleanup hanya database yang dibuat harness, bukan data kerja.

MySQL objects migration 000019:
- workshop_receipt_balance: saldo bon dari pembayaran aktif; voided bernilai nol.
- workshop_daily_payments: gross, reversal berdasarkan tanggal reversal, net per hari.
- stock_movement_audit: audit INSERT ledger di level database.

PHPStan turbo dapat mengeluarkan warning dynamic loading pada PHP statis; hasil
analisis tetap harus 0 error. Jangan menyembunyikan error atau mengedit vendor.

Tidak termasuk scope awal: WhatsApp API, QRIS API, reminder otomatis, multibranch,
estimate approval online, advanced accounting, purchase orders, supplier module.
