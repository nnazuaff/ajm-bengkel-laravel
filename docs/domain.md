# Domain final AJM Bengkel

AGENTS.md adalah spesifikasi. Schema dibangun bertahap; 19 migration domain setelah
starter authentication. Booking snapshot bukan customer master otomatis.

```mermaid
erDiagram
    USER ||--o| CUSTOMER : trusted_account
    CUSTOMER ||--o{ VEHICLE : owns
    CUSTOMER ||--o{ SERVICE_ORDER : serviced_for
    VEHICLE ||--o{ SERVICE_ORDER : history
    USER ||--o{ BOOKING : submitted_by
    BOOKING o|--o| SERVICE_ORDER : converted_once
    USER o|--o{ SERVICE_ORDER : mechanic
    SERVICE_ORDER ||--o{ SERVICE_JOB : work
    USER o|--o{ SERVICE_JOB : assigned
    SERVICE_ORDER ||--o{ SERVICE_DOCUMENTATION : evidence
    SERVICE_JOB o|--o{ SERVICE_DOCUMENTATION : optional_job
    INVENTORY_CATEGORY o|--o{ INVENTORY_ITEM : categorizes
    INVENTORY_ITEM ||--o{ STOCK_MOVEMENT : ledger
    INVENTORY_ITEM ||--o{ SERVICE_ITEM : used_part
    SERVICE_ORDER ||--o{ SERVICE_ITEM : parts
    SERVICE_ORDER o|--o| RECEIPT : service_bill
    CUSTOMER o|--o{ RECEIPT : buyer
    VEHICLE o|--o{ RECEIPT : optional_vehicle
    RECEIPT ||--o{ RECEIPT_ITEM : snapshots
    INVENTORY_ITEM o|--o{ RECEIPT_ITEM : optional_product
    SERVICE_JOB o|--o{ RECEIPT_ITEM : optional_labor
    RECEIPT ||--o{ PAYMENT : paid_or_reversed
    USER ||--o{ AUDIT_LOG : actor
```

## Transaction boundaries

- Intake: customer/vehicle reuse atau create, mileage, nomor dan order satu transaksi.
- Booking: lock request lalu intake; unique booking_id dan status konversi idempotent.
- Jobs: lock order lalu job; assigned mechanic authorization; price decimal string.
- Parts: lock order, optional receipt, part, inventory. Snapshot harga/nama; ledger/audit
  atomik. Return idempotent; cancellation mengembalikan part dalam transaksi status.
- Direct sale: lock receipt, inventory ascending ID. Finalisasi recalculation dan
  snapshot; stok berkurang sekali. Receipt PNG divalidasi sebelum final commit.
- Service bon: lock order lalu receipt. Pekerjaan completed dan part nonreturned diambil;
  tidak ada pengurangan stok kedua. Final melarang edit jobs/part yang memengaruhi bon.
- Payment: lock receipt; positive/balance validation; decimal total; reversal/void
  owner beralasan. Koreksi tidak menghapus transaksi atau otomatis mengirim refund.
- Documentation: order lock, actual image validation, private random path, audit;
  storage cleanup bila transaksi gagal. Removal logical; terminal orders read-only.
- Linking: lock customer dan target verified customer account; FK unique nullable,
  konfirmasi kepemilikan offline wajib; unlink/relink audit ID saja.

## Lifecycle / historical integrity

Masters user/customer/vehicle/inventory soft delete. Transaksi tidak dihapus melalui
UI. Stock movements immutable melalui model. Receipt snapshot identitas/item/harga
mencegah perubahan master mengubah bon final. Logo lama tetap disimpan karena
snapshot bon lama dapat merujuk file tersebut. Part returned_at dan payment reversed_at
menyimpan reversal eksplisit. Customer portal memeriksa owner saat ini per request;
link akun tidak dibuat otomatis dari informasi kontak.

## Simplifikasi sengaja

- Integer quantity untuk unit stok; bukan dispensing pecahan.
- Satu receipt per service order, termasuk receipt voided; tidak menerbitkan pengganti
  otomatis. Koreksi memakai lifecycle yang terlihat dan audit.
- No public anonymous receipt sharing: protected authenticated downloads saja.
- QRIS/transfer dicatat manual; tidak ada settlement gateway.
- Financial report penerimaan/reversal/net, bukan laba atau advanced accounting.
- Auth owner/admin/mechanic/customer enum; tidak ada permission package berlebihan.

Instruksi operasi dan verifikasi: docs/operations.md. Hasil pengujian aktual dicatat
terpisah dari diagram/desain; diagram bukan bukti keberhasilan implementasi.
