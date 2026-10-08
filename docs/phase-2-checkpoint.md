# Checkpoint kedua: Booking dan pekerjaan servis

Scope disetujui melalui instruksi lanjut; tetap pause setelah checkpoint usable/verified.

## Booking

Booking berupa snapshot permintaan, tidak mengubah Customer/Vehicle saat submission.
Akun customer terverifikasi mengirim form online; owner/admin mengelola antrean.
Identitas submitted_by menentukan akses booking sendiri, bukan kecocokan email/telepon.
Tidak ada guest lookup menggunakan nomor berurutan atau pengaitan akun otomatis.

Schema bookings: booking_number unique varchar30, submitted_by nullable users FK restrict,
name120, phone20, email254 nullable, license_plate20, brand60, model100, year nullable,
current_mileage unsignedInt, booking_date date index, arrival_time time,
service_type100, complaint text, notes nullable text, admin_notes nullable text,
status30 default pending index, timestamps. Snapshot tidak soft-delete.
service_orders.booking_id nullable unique FK bookings restrict dibuat migration berikutnya.

Status: pending, confirmed, rescheduled, arrived, converted_to_service, rejected, cancelled.
Pending dapat confirmed/rescheduled/rejected/cancelled; confirmed/rescheduled dapat
rescheduled/arrived/rejected/cancelled; arrived dapat converted/cancelled.
Customer hanya cancel booking miliknya yang pending/confirmed/rescheduled.
Reschedule wajib tanggal/waktu valid; cancelled/rejected alasan admin wajib.
Jam booking input 08:00..17:00 WIB, tanggal hari ini..90 hari; bukan klaim jam resmi bengkel,
konfigurasi sederhana untuk slot permintaan, admin konfirmasi. Tidak kuota slot spekulatif.

Konversi admin satu transaksi: lock booking; hanya arrived; resolve motor plat kanonik;
cek owner phone sesuai snapshot untuk reuse, mismatch error tanpa overwrite;
new motor/customer memakai ReceiveWalkIn dengan canonical phone reuse;
nomor SRV, mileage, order, audit lewat action existing; set source Booking dan booking_id
melalui trusted conversion action, bukan browser; status converted. Double convert
idempotent mengembalikan order existing; unique booking_id melindungi concurrency.
Status conversion tidak boleh dipalsukan melalui status update biasa.

Nomor generate(prefix='SRV') ditambahkan ke NextServiceNumber; prefix hanya SRV/BKG.
Owner/admin CRUD booking berwenang; mekanik tidak dapat melihat antrean booking.
Online route /booking auth+verified (customer role), booking.mine link portal;
admin /bookings gate manage-workshop. UI Flux inline konsisten; hanya tampil fitur usable.

## Service jobs

service_jobs: order FK restrict, name120, description nullable text, mechanic_id nullable users FK
restrict, labor_price decimal(14,2), status20 pending/in_progress/completed/cancelled,
notes nullable text, timestamps. No hard delete. Custom jobs; tidak katalog.
Mekanik bekerja hanya order assigned, assignment dirinya/default order mechanic;
owner/admin boleh harga dan assignment. Mekanik tidak boleh harga/cancel/edit pekerjaan selesai.
Order delivered/cancelled immutable; jobs di order completed/ready/delivered juga terkunci.
Pekerjaan pending/in_progress menghalangi completed order; jobs selesai tidak wajib ada
untuk record lama/inspection-only (ceiling eksplisit). Cancel order harus membatalkan jobs
nonterminal atomik; concurrency lock order parent ServiceOrder lalu job.
Uang string decimal server validated, tanpa float; labor subtotal via DB decimal SUM.
UI child ServiceJobs pada detail order supaya Services.php tidak membesar.

## Ownership file

Booking worker: enums/model/policy/actions/Livewire booking admin+customer/views/tests/factory,
migrations 000007 bookings dan 000008 booking FK. Tidak mengubah shared routes/shell/actions.
Job worker: enum/model/policy/action/child Livewire/view/tests/factory/migration 000009.
Parent: numbering, routes/nav/portal/dashboard, ServiceOrder relations, UpdateServiceOrder
job guards, shared docs/script verification. Tidak commit/push/reset DB.
