# CUSTOMER FLOW REVISION SPEC
## Revisi Booking Online, QR Check-in, Auto Customer, dan Auto Vehicle

> Dokumen ini adalah spesifikasi revisi untuk project Sistem Manajemen Bengkel Motor yang SUDAH berjalan.
> Dashboard admin dan fitur internal utama sudah tersedia.
> Fokus pekerjaan ini adalah menyederhanakan alur customer online dan customer walk-in tanpa merombak modul admin yang sudah stabil.

---

# 1. KONDISI SAAT INI

Masalah pada flow existing:

- Customer online harus register terlebih dahulu sebelum booking.
- User account, customer record, dan vehicle masih terlalu terasa sebagai entity terpisah bagi pengguna/admin.
- Admin dapat perlu melakukan linking manual.
- Customer yang datang langsung masih terlalu bergantung pada pegawai untuk menginput seluruh data.
- Flow tersebut terlalu rumit untuk bengkel kecil.

Target revisi:

1. Customer offline dapat check-in sendiri melalui QR.
2. QR check-in dilindungi kode/password yang diberikan mekanik.
3. Customer offline cukup mengisi data dasar.
4. Mekanik/admin melanjutkan input kendaraan, kilometer, keluhan, lalu membuat Service Order.
5. Registrasi akun online otomatis membuat atau menghubungkan Customer.
6. Kendaraan dari booking otomatis dibuat atau digunakan ulang.
7. Admin tidak perlu melakukan linking User → Customer → Vehicle secara manual.
8. Tambahkan guest booking agar akun tidak menjadi hambatan untuk booking.
9. Customer lama yang kemudian membuat akun dapat memperoleh riwayat lamanya setelah proses linking yang aman.

---

# 2. PRINSIP DOMAIN

Pisahkan konsep berikut:

- User = akun login
- Customer = entity pelanggan bengkel
- Vehicle = kendaraan milik customer

Jangan anggap Customer selalu sama dengan User.

Relasi ideal:

User
→ optional Customer

Customer
→ many Vehicles
→ many Bookings
→ many Service Orders
→ many Receipts

Customer harus dapat ada tanpa akun.

Contoh:

```text
Customer offline
user_id = null
```

Customer yang mempunyai akun:

```text
Customer online
user_id = <users.id>
```

Jika schema existing menggunakan pola lain, adaptasikan secara incremental tanpa merombak seluruh database jika tidak diperlukan.

---

# 3. CUSTOMER WALK-IN BARU

Flow baru:

```text
Customer datang
→ Scan QR
→ Masukkan Check-in Code
→ Isi Nama + No WhatsApp
→ Submit
→ Customer masuk antrean/check-in admin
→ Mekanik memilih customer
→ Pilih kendaraan existing / tambah kendaraan
→ Input KM
→ Input keluhan
→ Buat Service Order
```

Customer offline tidak perlu mengisi terlalu banyak data teknis.

Minimal field public check-in:

- Nama
- Nomor HP / WhatsApp
- Email opsional
- Check-in Code

Data kendaraan diisi atau dikonfirmasi mekanik/admin.

---

# 4. QR CHECK-IN

Tambahkan public page, contoh:

```text
/check-in
```

QR Code hanya berisi URL halaman check-in.

QR harus dapat dipasang secara permanen di bengkel.

QR tidak perlu berubah setiap hari.

Yang berubah adalah Check-in Code.

---

# 5. CHECK-IN CODE

Untuk mengurangi spam, customer harus meminta kode kepada mekanik/admin.

Contoh:

```text
Kode aktif: 4827
```

Admin/mekanik dapat:

- melihat kode aktif
- generate kode baru
- menonaktifkan kode sebelumnya

Rekomendasi MVP:

- 4-6 digit numeric code
- hanya satu kode aktif
- expiry opsional
- regenerate manual
- rate limiting tetap wajib

Jangan menggunakan password permanen seperti `bengkel123`.

Tidak perlu OTP SMS atau sistem rumit untuk tahap ini.

---

# 6. ADMIN CUSTOMER CHECK-IN PAGE

Tambahkan halaman/section:

```text
Customer Check-in
```

Tampilkan:

- Active Check-in Code
- Tombol Generate New Code
- QR Code
- Customer menunggu
- Waktu check-in
- Status
- Quick action Create Service

Status yang dapat dipakai:

- waiting
- processing
- converted_to_service
- cancelled

Contoh:

```text
Customer Check-in

Kode Aktif: 4827
[Generate New Code]

[ QR CODE ]

Menunggu

Fauzan
081234567890
3 menit lalu

[Create Service]
```

Gunakan design system admin existing.

Jangan redesign dashboard secara keseluruhan.

---

# 7. CUSTOMER DUPLICATE PREVENTION

Saat QR check-in atau guest booking masuk:

1. Normalisasi nomor HP.
2. Cari Customer existing.
3. Jika ditemukan, gunakan Customer tersebut.
4. Jika tidak ditemukan, buat Customer baru.

Jangan match customer berdasarkan nama saja.

Gunakan nomor telepon sebagai primary matching signal untuk workflow ini.

Contoh nomor yang harus dapat dinormalisasi:

```text
081234567890
6281234567890
+6281234567890
```

Simpan normalized phone jika diperlukan.

Centralize logic tersebut dalam helper/service agar tidak diduplikasi di banyak controller/component.

---

# 8. EXISTING CUSTOMER CHECK-IN

Jika Customer lama scan QR:

```text
Phone ditemukan
→ reuse Customer
→ tampilkan kendaraan existing
```

Admin/mekanik kemudian dapat memilih:

```text
D 1234 ABC
Honda Vario 160

[Gunakan Kendaraan]
[Tambah Kendaraan Baru]
```

Jangan membuat Customer baru setiap kali datang.

---

# 9. VEHICLE WALK-IN

Mekanik/admin mengurus kendaraan.

Minimal:

- Nomor polisi
- Brand
- Model/type
- Tahun opsional
- Warna opsional
- Current mileage
- Complaint

Normalisasi nomor polisi.

Contoh:

```text
D 1234 ABC
D1234ABC
d 1234 abc
```

menjadi normalized representation:

```text
D1234ABC
```

Pencarian/reuse vehicle menggunakan:

```text
customer_id + normalized_license_plate
```

Jangan membuat vehicle duplicate.

---

# 10. MANUAL WALK-IN TETAP ADA

QR adalah convenience feature, bukan satu-satunya cara.

Jangan hapus flow manual admin.

Flow manual tetap diperlukan untuk:

- customer tidak membawa HP
- customer tidak punya smartphone
- QR bermasalah
- customer lanjut usia
- admin ingin langsung input

Admin harus tetap dapat:

```text
Cari Customer
→ Pilih / Buat
→ Pilih / Buat Vehicle
→ Input KM + Complaint
→ Create Service
```

---

# 11. REGISTRATION AUTO CUSTOMER

Saat customer register akun:

```text
Register User
→ resolve Customer
→ create/reuse Customer
→ link Customer.user_id
```

Admin tidak boleh perlu melakukan linking manual.

Jika tidak ada Customer yang cocok:

```text
Create Customer
```

Jika Customer existing ditemukan secara aman:

```text
Link User → Customer
```

Prioritas matching:

1. verified phone
2. verified email jika sistem menggunakannya
3. jika ambigu, jangan auto-link

Jangan link hanya berdasarkan nama.

---

# 12. CUSTOMER OFFLINE LALU REGISTER

Scenario:

Customer sudah pernah datang:

```text
customers:
id = 15
phone = 081234567890
user_id = null
```

Kemudian register.

Target:

```text
User dibuat
→ Customer existing ditemukan
→ user_id customer diisi
→ riwayat lama tetap menggunakan Customer yang sama
```

Setelah link berhasil, akun dapat melihat:

- vehicles
- bookings
- service history
- documentation
- receipts

Jika identity verification belum tersedia, gunakan pendekatan aman dan jangan membuat account claiming terlalu permisif.

---

# 13. ONLINE BOOKING DENGAN AKUN

Authenticated booking harus sederhana.

Flow:

```text
Login
→ Customer otomatis tersedia
→ Pilih kendaraan existing / tambah kendaraan
→ Pilih tanggal
→ Pilih slot/jam
→ Input keluhan
→ Submit Booking
```

Pre-fill:

- name
- phone
- email

Admin menerima booking yang sudah terhubung dengan:

```text
Booking
→ Customer
→ Vehicle
```

Tidak ada linking manual.

---

# 14. AUTO CREATE / REUSE VEHICLE DARI BOOKING

Saat customer mengisi kendaraan:

```text
Normalize license plate
→ cari berdasarkan customer + plate
```

Jika ditemukan:

```text
reuse Vehicle
```

Jika tidak:

```text
create Vehicle
```

Kemudian:

```text
create Booking
```

Jangan membuat kendaraan baru setiap kali booking.

---

# 15. GUEST BOOKING

Tambahkan opsi booking tanpa akun.

Ini sangat direkomendasikan untuk bengkel kecil.

Public booking form:

### Customer

- Nama
- No WhatsApp
- Email opsional

### Vehicle

- Nomor polisi
- Brand
- Model/type
- Tahun opsional
- Current mileage

### Booking

- Tanggal
- Jam/slot
- Jenis service
- Keluhan
- Catatan opsional

Backend:

```text
normalize phone
→ create/reuse Customer
→ normalize plate
→ create/reuse Vehicle
→ create Booking
```

Account bukan requirement untuk booking.

---

# 16. ACCOUNT VS GUEST BOOKING

Public page dapat menawarkan:

```text
Booking Service

[Booking Tanpa Akun]
[Login]
```

Guest booking:

```text
Customer + Vehicle + Booking
```

Account booking:

```text
Data customer sudah tersedia
→ pilih kendaraan
→ booking
```

Keuntungan akun:

- riwayat booking
- service history
- vehicle history
- receipt
- documentation
- status service

Account harus menjadi benefit, bukan penghalang.

---

# 17. CENTRAL CUSTOMER RESOLVER

Buat reusable business logic, naming menyesuaikan project.

Contoh konsep:

```text
CustomerResolver
```

Responsibility:

- normalize phone
- lookup Customer
- create Customer
- safely link User
- prevent duplicate Customer

Gunakan service/action pattern sesuai convention existing.

Jangan copy-paste logic resolve customer di:

- registration
- guest booking
- account booking
- QR check-in
- admin walk-in

---

# 18. CENTRAL VEHICLE RESOLVER

Buat reusable logic untuk:

- normalize license plate
- find vehicle by customer + normalized plate
- create vehicle jika belum ada
- prevent duplicate vehicle

Jangan overwrite data existing yang dipercaya hanya karena public form mengirim nilai berbeda.

Perubahan sensitif dapat meminta review admin.

---

# 19. CHECK-IN DATA MODEL

Adaptasikan ke schema existing.

Possible table:

```text
check_ins
```

Possible fields:

- id
- customer_id
- status
- checked_in_at
- processed_at nullable
- processed_by nullable
- service_order_id nullable
- timestamps

Possible table:

```text
check_in_codes
```

Possible fields:

- id
- code / code_hash
- active
- expires_at nullable
- created_by
- timestamps

Alternative implementation diperbolehkan jika lebih cocok dengan arsitektur existing.

Jangan membuat tabel hanya karena tertulis di dokumen jika kebutuhan dapat dipenuhi lebih bersih dengan struktur existing.

---

# 20. CUSTOMER SCHEMA UPDATE

Possible additions:

```text
customers
- user_id nullable
- normalized_phone
```

Pastikan:

- user_id relationship sesuai architecture
- index tersedia
- unique constraint hanya dipasang setelah existing data diperiksa

Jangan reset database.

Gunakan migration incremental.

---

# 21. VEHICLE SCHEMA UPDATE

Possible addition:

```text
vehicles
- normalized_license_plate
```

Backfill existing data.

Audit duplicate terlebih dahulu sebelum menambahkan unique constraint.

Jangan menghapus vehicle duplicate existing secara otomatis tanpa evaluasi.

---

# 22. BOOKING SOURCE

Jika berguna, simpan source:

```text
guest
authenticated
admin
```

Ini opsional jika schema existing sudah memiliki informasi asal booking.

---

# 23. CHECK-IN SECURITY

Implementasikan:

- active Check-in Code validation
- rate limiting
- server-side validation
- phone normalization
- duplicate submission prevention
- CSRF sesuai route/context
- friendly error message

Contoh duplicate prevention:

Jika customer yang sama sudah memiliki:

```text
waiting check-in
```

dalam waktu dekat, jangan membuat 10 check-in baru.

Bisa:

- reuse existing check-in
- atau tolak dengan pesan bahwa data sudah masuk

---

# 24. GUEST BOOKING SECURITY

Tambahkan:

- Laravel rate limiter
- server-side validation
- duplicate spam protection

Captcha tidak perlu untuk tahap awal kecuali spam benar-benar menjadi masalah.

Keep UX simple.

---

# 25. AUTHORIZATION

Public:

- check-in form
- guest booking

Admin/mechanic protected:

- active code management
- waiting check-ins
- process check-in
- create Service Order

Customer protected:

- own profile
- own vehicles
- own bookings
- own service history
- own receipts

Customer tidak boleh dapat membaca data customer lain.

---

# 26. CUSTOMER CLAIM / LINK SECURITY

Jangan membiarkan user claim Customer hanya dengan mengetahui:

```text
phone number
```

Jika auto-link Customer existing ke User:

prefer verified identifier.

Jika sistem belum mempunyai phone verification:

- evaluasi email verified jika sesuai
- atau buat flow verification/confirmation
- atau gunakan manual review jika ambiguous

Jangan mengorbankan ownership security demi convenience.

---

# 27. DATABASE TRANSACTIONS

Gunakan transaction untuk operation multi-record.

Guest booking:

```text
Resolve Customer
→ Resolve Vehicle
→ Create Booking
```

Process check-in:

```text
Resolve/Create Vehicle
→ Create Service Order
→ Mark Check-in processed
```

Jika satu critical step gagal, data harus tetap konsisten.

---

# 28. UX ADMIN

Admin tidak perlu melihat istilah teknis:

```text
Link User to Customer
Attach Customer Entity
Resolve Vehicle Relationship
```

UI admin harus sederhana.

Booking:

```text
Fauzan
D 1234 ABC
Honda Vario 160
10 Oct 2026
09:00

[Confirm]
[Reschedule]
[Reject]
```

Check-in:

```text
Fauzan
081234567890
3 menit lalu

D 1234 ABC - Vario 160

[Create Service]
```

Relational complexity harus disembunyikan dari user bengkel.

---

# 29. UX PUBLIC CHECK-IN

Mobile-first.

Flow seminimal mungkin:

```text
Masukkan Kode Bengkel
→ Nama
→ WhatsApp
→ Submit
```

Jangan meminta customer offline mengisi data tidak penting.

Success state:

```text
Data berhasil dikirim.
Silakan tunggu, petugas bengkel akan memproses kendaraan Anda.
```

---

# 30. UX ONLINE BOOKING

Guest:

```text
Data Customer
→ Data Kendaraan
→ Jadwal
→ Keluhan
→ Submit
```

Registered:

```text
Select Vehicle
→ Jadwal
→ Keluhan
→ Submit
```

Gunakan design/frontend/UI/UX skill yang tersedia di environment agent.

Pertahankan design system project existing.

---

# 31. ERROR HANDLING

Handle dengan pesan user-friendly:

- kode check-in salah
- kode expired
- check-in sudah terkirim
- rate limit
- nomor HP invalid
- booking slot tidak tersedia
- vehicle duplicate
- ambiguous account linking
- User sudah linked ke Customer lain

Jangan tampilkan raw exception.

---

# 32. MIGRATION STRATEGY

Project sudah production-like/developed.

JANGAN:

- reset database
- migrate:fresh
- rewrite semua migration
- drop tabel stable tanpa alasan

Lakukan:

1. audit migration existing
2. audit customers/users/vehicles/bookings
3. audit foreign key
4. audit data existing
5. buat migration incremental
6. backfill normalized fields
7. cek duplicates
8. baru tambahkan index/constraint jika aman

---

# 33. IMPLEMENTATION ORDER

Kerjakan dalam urutan:

## Step 1
Audit existing auth, Customer, Vehicle, Booking, Service Order.

## Step 2
Implement phone normalization.

## Step 3
Implement license plate normalization.

## Step 4
Centralize Customer resolver.

## Step 5
Centralize Vehicle resolver.

## Step 6
Fix registration → auto Customer.

## Step 7
Fix authenticated booking → auto Customer/Vehicle linking.

## Step 8
Implement guest booking.

## Step 9
Implement Check-in Code.

## Step 10
Implement QR/public check-in.

## Step 11
Implement admin waiting check-in UI.

## Step 12
Integrate check-in → Vehicle → Service Order.

## Step 13
Add/update tests.

## Step 14
Polish UX.

Jangan mengubah modul lain yang sudah stabil tanpa kebutuhan.

---

# 34. TESTING

Tambahkan tests untuk:

### QR Check-in

- valid code accepted
- invalid code rejected
- expired/inactive code rejected
- existing customer reused
- new customer created
- duplicate waiting check-in handled
- admin/mechanic can process check-in
- unauthorized user cannot manage code

### Registration

- new account creates Customer
- eligible existing Customer reused
- Customer duplicate tidak dibuat
- ambiguous linking tidak dilakukan sembarangan

### Guest Booking

- creates/reuses Customer
- creates/reuses Vehicle
- creates Booking dengan relationship benar

### Authenticated Booking

- automatically uses linked Customer
- existing Vehicle selectable
- new Vehicle can be created
- duplicate Vehicle avoided

### Normalization

Phone:

```text
0812...
62812...
+62812...
```

resolve secara konsisten.

Plate:

```text
D 1234 ABC
D1234ABC
d 1234 abc
```

resolve secara konsisten.

### Authorization

- customer A tidak dapat melihat vehicle/booking/service customer B

---

# 35. EDGE CASES

## Case A
Customer yang sama booking berulang:
reuse Customer.

## Case B
Customer yang sama + plate sama:
reuse Vehicle.

## Case C
Customer sama + motor baru:
create Vehicle baru.

## Case D
Customer offline kemudian register:
safe link ke existing Customer.

## Case E
Registered User sudah memiliki Customer:
jangan create Customer kedua.

## Case F
Customer check-in dua kali cepat:
jangan buat duplicate queue.

## Case G
Customer tidak bisa scan QR:
manual walk-in tetap tersedia.

## Case H
Phone atau plate beda format:
normalization menghindari duplicate.

## Case I
Data account/customer matching ambigu:
jangan auto-link tanpa verification.

---

# 36. DO NOT

Jangan:

- paksa semua customer membuat akun
- meminta admin melakukan manual User → Customer linking
- meminta admin melakukan manual Booking → Vehicle linking
- membuat Customer baru setiap guest booking
- membuat Vehicle baru setiap booking
- menghapus manual walk-in
- menggunakan QR password permanen
- merombak dashboard admin secara besar-besaran
- reset database
- mengubah modul stabil tanpa kebutuhan
- membuat OTP/CAPTCHA kompleks tanpa kebutuhan
- mencampurkan customer dengan User sebagai satu entity

---

# 37. TARGET FINAL FLOW

## OFFLINE

```text
Customer datang
→ Scan QR
→ Tanya kode ke mekanik
→ Isi Nama + WhatsApp
→ Customer resolve/create
→ Check-in Queue
→ Mekanik select Customer
→ Select/Create Vehicle
→ KM + Complaint
→ Service Order
```

## GUEST ONLINE

```text
Booking Public
→ Customer Data
→ Vehicle Data
→ Auto Customer
→ Auto Vehicle
→ Booking
```

## REGISTERED ONLINE

```text
Register/Login
→ Customer auto-linked
→ Select/Create Vehicle
→ Booking
```

## EXISTING OFFLINE CUSTOMER LATER REGISTERS

```text
Existing Customer
→ Register
→ Identity verified/matched
→ Link User
→ Existing history becomes accessible
```

---

# 38. SUCCESS CRITERIA

Fitur dianggap selesai jika:

- QR check-in bekerja
- code management bekerja
- spam protection dasar tersedia
- duplicate Customer dapat dicegah
- duplicate Vehicle dapat dicegah
- registration auto-link bekerja
- booking authenticated tidak memerlukan linking manual
- guest booking bekerja
- admin check-in queue bekerja
- check-in dapat dikonversi menjadi Service Order
- manual walk-in tetap bekerja
- authorization aman
- critical tests lulus
- existing admin modules tetap berfungsi
- database existing tidak di-reset

---

# 39. FINAL INSTRUCTION FOR AI AGENT

Project sudah berjalan dan dashboard admin sudah selesai.

Dokumen ini adalah UPDATE terhadap flow Customer dan Booking.

Jangan membuat ulang project.

Jangan hanya memberikan rancangan.

Mulai dengan audit code existing dan identifikasi implementasi Customer, User, Vehicle, Booking, dan Service Order yang sudah tersedia.

Setelah itu langsung implementasikan revisi ini secara incremental.

Gunakan conventions project existing.

Gunakan Laravel/Livewire architecture existing.

Gunakan frontend/UI/UX/design skills yang tersedia untuk halaman public check-in, guest booking, dan tambahan UI admin.

Tujuan utamanya adalah menghilangkan friction:

```text
Customer masuk
→ sistem otomatis resolve Customer
→ sistem otomatis resolve Vehicle
→ Booking / Check-in langsung usable
```

Admin bengkel tidak perlu memahami relasi database atau melakukan linking manual.

Sistem harus tetap sederhana untuk bengkel kecil, aman, maintainable, dan tidak overengineered.
