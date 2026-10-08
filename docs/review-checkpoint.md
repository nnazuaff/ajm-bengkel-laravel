# Checkpoint review kedua

Catatan historis checkpoint kedua; sudah dilanjutkan atas izin pengguna.
Status terbaru: docs/verification.md. Instruksi: docs/operations.md. Tidak ada commit/push.

## Tersedia

- Auth Fortify + role owner/admin/mechanic/customer; registrasi umum selalu customer.
- Dashboard hitungan nyata; mekanik hanya order ditugaskan.
- Pelanggan/kendaraan: tambah/edit/search/pagination/arsip aman, soft delete, ownership policy.
- Walk-in motor baru atau terdaftar: kontak reuse, mileage, keluhan, mekanik, nomor harian.
- Detail servis: diagnosis terpisah, transisi status, timestamp, audit, terminal record terkunci.
- Booking customer terverifikasi: snapshot permintaan, daftar sendiri/cancel, ownership submitted_by.
- Admin booking: konfirmasi, reschedule, datang, reject/cancel beralasan, konversi idempotent.
- Pekerjaan custom servis: assignment, status, labor decimal snapshot, subtotal, terminal lock.
- Servis tidak selesai jika jobs masih terbuka; cancellation membatalkan pekerjaan nonterminal atomik.
- Livewire/Flux responsive, TypeScript shortcut Ctrl/Cmd+K pencarian.

## Hasil verifikasi aktual

- php artisan test --compact: 238 passed, 1007 assertions.
- composer lint:check: passed.
- composer types:check: passed, 0 analysis errors; startup warning turbo extension pada PHP statis herd-lite masih muncul.
- npm run types:check: passed.
- npm run test:search: 23 passed.
- npm run build: passed.
- php scripts/verify-mysql.php: migration, rollback 9 migration, reapply, intake rollback,
  workflow/audit, booking conversion, exact decimal SUM, 4 worker/40 nomor unik,
  4 konversi booking serentak menghasilkan tepat 1 order: passed. Schema verifikasi dibersihkan.
- MySQL kerja: 9 migration domain Ran; tidak migrate:fresh/reset data kerja.
- Route customers/vehicles/services/bookings/booking: auth + verified + gate terverifikasi.
- Login, CSS, TypeScript lokal HTTP 200; guest services HTTP 302.
- Browser login/register desktop serta login mobile 390px: tanpa horizontal overflow;
  label Flux aria-labelledby tersedia. UI admin diuji melalui HTTP/Livewire tests,
  belum sesi browser admin autentik untuk QA visual lengkap.
- git diff --check: passed.

## Perbaikan hasil review

- Nomor harian memakai upsert sebelum row lock: menghindari shared-lock upgrade deadlock INSERT IGNORE.
- Nested intake non-array ditolak sebelum array_map.
- Pembatalan tidak menerima notes=null dengan fallback catatan lama.
- Diagnosis tidak boleh dihapus pada tahap sesudah persetujuan hingga penyerahan.
- Error stale save tetap terlihat ketika order ditutup oleh admin lain.

## Review pengguna

Buka http://127.0.0.1:8000/login (server lokal yang sudah berjalan).
Gunakan akun staff yang sudah dimiliki; tidak ada password default/seed baru.
Bila perlu akun baru, dari root repo jalankan:

    php artisan workshop:create-user email@domain.test --name="Nama Staff" --role=owner

Password melalui prompt tersembunyi, bukan argumen atau chat.

Urutan review:
1. Login, dashboard, menu pelanggan/kendaraan/servis.
2. Tambah pelanggan, tambah motor, cari via plat/telepon.
3. Terima walk-in, buka detail, catat diagnosis, ubah status sesuai urutan.
4. Coba mileage menurun dan order aktif ganda: harus ditolak tanpa data parsial.
5. Akun customer terverifikasi membuka /booking, mengajukan booking. Admin /bookings
   mengonfirmasi, menandai datang, menerima servis. Konversi ulang tidak menduplikasi.
6. Detail servis: tambah pekerjaan + harga jasa, coba completed saat pekerjaan belum selesai.
   Selesaikan pekerjaan, kemudian order. Harga pecahan divalidasi tanpa float.
7. Arsip ditolak saat masih ada kendaraan/servis aktif; setelah ditutup riwayat tetap tersimpan.

Gunakan data review yang memang boleh disimpan di DB lokal. Test otomatis tidak memakai data kerja.

## Belum dikerjakan

Inventory/movements, photos, bon/image receipt, payments,
portal histori pelanggan, procedure/function/trigger laporan. Lanjut hanya setelah review pengguna.
