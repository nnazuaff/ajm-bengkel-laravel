# Verifikasi integrasi admin + customer/public

Status: core + check-in akun otomatis terimplementasi; verifikasi terbaru 811 tests /3499 assertions, 56 browser checks, lint/PHPStan/TypeScript/build/MySQL passed. Migration000022–000028 terpasang; sesudahnya DB demo lokal dibangun ulang dengan migrate:fresh atas persetujuan eksplisit pengguna. QA hanya memakai schema/SQLite terisolasi. Review independen sesi/password ditutup dengan 16 tests /99 assertions lulus; QR melalui HP dilaporkan lulus oleh pengguna. Bagian berikut adalah checkpoint historis admin modal sebelum revisi customer; angka di bawah tidak menggantikan hasil terbaru. Ownership existing customer tidak dibuka hanya dengan phone. Lihat customer-flow-audit.md.
Bukan deployment production atau sertifikasi semua permutasi penggunaan.

## Checkpoint historis admin modal (sebelum revisi customer)

- php artisan test --compact: 687 passed, 2793 assertions.
- composer lint:check: passed.
- composer types:check: passed, 0 errors. Warning turbo-extension dynamic loading
  pada PHP statis tetap muncul; warning tidak disembunyikan.
- npm run types:check: passed.
- npm run test:search: 23 passed.
- npm run build: passed.
- php artisan view:cache: passed.
- php artisan migrate --pretend: Nothing to migrate.
- git diff --check: passed.
- Database kerja: 19 migration domain Ran; tanpa reset/fresh.

php scripts/verify-mysql.php: passed pada schema acak terisolasi:
- 19 migration rollback/reapply, intake/duplicate/rollback audit, lifecycle/timestamps.
- Booking snapshot/conversion idempotent, exact decimal SUM jobs, terminal guard.
- Function saldo bon, procedure laporan harian/reversal, audit trigger stok.
- Direct sale stok/payment/void atomik; PNG nyata valid, file bukti receipt-proof.png.
- 4 worker / 40 nomor unik.
- 4 konversi booking / tepat 1 order.
- 4 pengurangan stok / hanya 2 tersedia / stok tidak negatif.
- 4 finalisasi dan pembayaran / tepat 1 finalisasi dan 1 payment.
- Schema verifikasi dibersihkan; data kerja dipertahankan.

## Review independen dan koreksi parent

- Mekanik dengan active ServiceJob pada order mekanik lain tidak boleh nonaktif/demote.
  Regression tests save/deactivate: passed.
- Output PNG wajib diperiksa sebelum finalisasi; layout terlalu tinggi rollback ke draft,
  bukan meninggalkan bon terkunci tanpa gambar. Regression test: passed.
- Ringkasan metode pembayaran berukuran tetap menjaga PNG setelah banyak installments/
  reversal; 500 event pembayaran tetap menghasilkan PNG bounded. Test: passed.
- Histori receipt draft membuka editor, bukan route cetak HTTP409. Test: passed.
- Receipt total nol lunas tanpa payment fiktif; partial draft save mempertahankan items.
- Review customer/public: 75 tests /317 assertions lulus. Reproducer terisolasi
  relink/unlink: akun lama kehilangan portal selected/web/PNG/photo (404), akun baru
  memperoleh akses (200), unlink menutup akses. Tidak ditemukan bypass konkret.

## Bukti / batas

PNG nyata:
    storage/app/verification/receipt-proof.png

Instruksi operasi: docs/operations.md.
Relasi/domain: docs/domain.md.
Kontrak scope: docs/admin-completion-contract.md dan docs/customer-public-contract.md.

QA Chromium aktual dijalankan ulang parent setelah memperbaiki collision Livewire upload:
49/49 checks passed, console_errors=0. Artefak terbaru .hermes/qa/browser-EGGupw/.
Booking customer list dipisah dari child BookingRequest modal. Browser membuktikan
open/cancel/Escape, reset draft, fokus masuk/kembali tombol, mobile scroll dan
submit menutup modal serta memperbarui daftar. Admin memakai child modals untuk
pelanggan/kendaraan, akun pelanggan, inventory editor/stock/categories/history, staf,
booking review dan walk-in. Service detail /services/{id}, receipt editor
/receipts/create dan /receipts/{id}/edit terpisah dari list. Browser menguji edit
pelanggan, cancel/reset, account popup, stok/history, staff edit, mobile dialog bounds,
Escape, intake, booking reception, finance workflow. Independent review completed: stable receipt redirect after first save, booking service
link directly to detail, revoked/stale actor roles denied by Gate before policies.
12 regression cases RED then GREEN; browser rerun49/49 passed.
Termasuk kategori delete, akun email unverified ketika requirement OFF, booking admin
confirmation/arrival, explicit archived master restoration, trusted linking, portal own motor.
Local requirement false terverifikasi; testing/production default true. Tidak mengisi
email_verified_at palsu atau memulihkan Harvey tanpa tindakan petugas.
13 route admin desktop dan halaman mobile390px tanpa horizontal overflow. Customer/vehicle
create, walk-in, assignment, diagnosis/status, job, part, foto upload/save, completion,
receipt finalize/pay/web/PNG, direct sale, customer booking, own detail/private photo,
mobile receipt click dan guard403/404 diuji lewat browser nyata dan state SQLite terisolasi.
Tidak memakai password/akun produksi atau memodifikasi data MySQL kerja.
Coverage tetap smoke: tidak seluruh CRUD edit/archive atau semua kombinasi mekanik/reversal
dijalankan di browser; branch bisnis/security tersebut dicakup automated tests.
Tidak ada commit/push otomatis; perubahan masih working tree.
