# Admin completion contract

AGENTS.md primary spec. User authorizes all admin scope until verified completion, no checkpoint pause now.
Scope diperluas pengguna ke customer/public; lihat docs/customer-public-contract.md. No Git commit/push, no destructive working DB reset.
No speculative accounting/multibranch/integrations. Fresh domain currently 238 tests.

## Money / stock
All money DECIMAL(14,2), strings with regex 0..999999999999.99, bc arithmetic, no float.
Stock whole units unsignedInteger (initial scope excludes fractional oil dispensing).
StockMovement signed quantity delta; type in/out/adjustment/return, before/after integers,
reason string, reference_type nullable string/reference_id nullable bigint, actor created_by FK.
Quantity delta explicit signed; out negative. Never assign stock in master form.
Lock order: parent ServiceOrder then optional Receipt then inventory rows ascending id.
Receipt-specific actions acquire ServiceOrder first if linked, then Receipt, then inventory.

## Inventory interfaces owned inventory worker
Migrations 000010 inventory_categories, 000011 inventory_items, 000012 stock_movements,
000013 service_items. Schema category name unique; item sku unique/name/category_id/brand,
purchase_price/selling_price, current_stock/minimum_stock/unit/supplier/storage_location/is_active,
timestamps softDeletes. Item current_stock excluded mass-assignment; always action updates.
ServiceItem order/item FK, description snapshot, quantity positive integer, unit_price/subtotal,
used_by FK, returned_at nullable immutable datetime. Never hard delete; full return record once.

StockLedger::move(User actor, InventoryItem item, int delta, string type, string reason,
?string referenceType=null, ?int referenceId=null):StockMovement.
Trusted domain helper transaction+item lock, no policy inside (callers authorize), validates
positive stock after/nonzero delta, same row reload, records movement+audit atomic. Return may
work on archived/inactive item for reversals. Manual UI owner/admin only; use withTrashed.

UseServicePart::use(User actor,ServiceOrder order,int inventoryItemId,int quantity):ServiceItem;
returnPart(User actor,ServiceOrder order,ServiceItem part):ServiceItem;
returnAll(User actor,ServiceOrder order):void.
Policies assigned mechanic/admin; parent status editable only waiting..waiting_part. Price from
item current selling price, no browser override. Return locks parent, part then item; idempotent.
Block alterations once any nonvoided FINAL/PAID service receipt exists. Draft receipt rebuilt
from jobs/parts at finalization. Parent cancellation calls returnAll in same DB transaction;
block cancelled service if nonvoided finalized receipt until receipt voided.
UI Inventory page /inventory gate manage-workshop; ServiceParts child mount(int serviceOrderId).
Inventory methods restock + adjustment reason required, zero stock initial, low/out filters/history.

## Finance owned finance worker
Migrations 000015 receipts, 000016 receipt_items, 000017 payments.
Receipt uuid public_id unique optional future share (no public route now), receipt_number BON unique,
service_order_id nullable unique FK (one receipt per order; voided kept, no replacement initial),
customer_id/vehicle_id nullable FKs, cashier_id FK, transaction_date datetime,
status draft/final/paid/voided enum, subtotal/discount/grand_total decimal, payment_status
unpaid/partial/paid enum, notes nullable, workshop_snapshot/customer_snapshot/vehicle_snapshot JSON,
void_reason, voided_at nullable. ReceiptItems receipt FK, description/type service/product/custom,
quantity unsignedInt, unit_price/discount/total decimal, inventory_item_id/service_job_id nullable.
Payments receipt FK amount decimal method cash/transfer/qris/other, paid_at, reference nullable,
created_by FK, reversed_at nullable, reversal_reason nullable. No hard delete.

ManageReceipt action methods:
create(User actor,array input):Receipt (service_order_id nullable; snapshot jobs/active parts;
only completed/ready/delivered service; direct sale independent, customer/vehicle authoritative).
saveDraft(User actor,Receipt receipt,array input):Receipt (discount notes items; direct input
array validates inventory item/qty and server chooses selling price; custom manual unit_price
server validated; service jobs/parts auto assembled, extra custom allowed).
finalize(User actor,Receipt receipt):Receipt locks/rebuilds snapshots, recomputes all totals;
direct product deductions once via StockLedger, service parts NOT deducted twice; immutable.
pay(User actor,Receipt receipt,array input):Payment supports partial+full, no overpayment,
lock receipt, server recompute paid sum; allow final only; payment reference/date validated.
reversePayment(User actor,Payment payment,string reason):Payment owner only, soft lifecycle;
updates receipt status/payment_status, audit, no stock effect.
void(User actor,Receipt receipt,string reason):Receipt OWNER only. Reverse active payments
(reversed_at/reason) for correction audit, return direct stock once; service part records returned
via UseServicePart returnAll trusted inside void AFTER marking voided. Idempotent void/no duplicates.
Draft void no inventory reversal. Warn user paid reversal records bookkeeping correction,
not automatic bank/refund integration. All multi-step audit failures rollback.

UI Receipts /receipts gate manage-workshop handles draft/create service/direct/new general,
items/edit/finalize/pay/void/reverse with confirmations and errors. Payments /payments read
search/list links receipt. Receipt view controller auth policy owner/admin; mechanics NO finances.
Receipt web /receipts/{receipt}/view receipts.show and /receipts/{receipt}/image receipts.image
protected. PNG primary output via GD/Imagick installed, renderer ReceiptImage::render(Receipt):string
PNG bytes no remote HTML/external assets; local bundled font if needed license included, dynamic
wrap heights long names/items, logo optional validated local storage. Snapshot workshop identity
from WorkshopSetting::current()->only name/phone/address/footer (parent model available).
Receipt view never trusts client totals, print CSS and image download link. No public IDs exposed.

## Photos owned documentation worker
Migration 000014 service_documentations: order/job nullable FK, disk/path, category before/process/
after/evidence/other, caption nullable, uploaded_by FK, timestamps, deleted_at nullable for removal.
Private local disk (storage app/private). MIME jpg/png/webp, max5MB, max4096x4096, no SVG.
Photos action upload(User,ServiceOrder,UploadedFile,array input):ServiceDocumentation; safe
random path, validate job belongs order, policy admin/assigned mechanic, do not expose raw path.
remove(User,ServiceDocumentation):void audit logical soft-delete preserve file for history (no
orphan physical cleanup needed). Terminal delivered/cancelled read-only, completed/ready photo
after-work allowed. Gallery child ServicePhotos mount(int serviceOrderId), upload/category/job/caption,
preview via protected route /documentation/{documentation} documentation.show, auth+policy.
Controller Laravel Storage response no arbitrary input file routes, X-Content-Type-Options nosniff.
Parent ServiceOrder docs() relation, UI embedding. Customer portal no new access route.

## Parent operations
Migration 000018 workshop_settings singleton id1 name/phone/address/footer optional logo_path,
000019 MySQL stored objects (after stock/payments exist) branch only mysql driver.
Parent WorkshopSetting::current():self default AJM Bengkel (not guessed contacts). Staff /mechanics
owner full role management/admin read mechanics, account create password via user secret UI ONLY
if browser interaction; code form password normal user input server hashed not logs, no generated
shared default. Simplest staff creation existing console; UI role/active/name edits OWNER only,
last-owner protection + assigned active orders restriction before mechanic deactivate; audit.
Settings workshop identity owner only. Audit page manager read, filters/date/action actor eagerload.
History /history manager, search plate, detail each order complaint/diagnosis/jobs/parts/photos/
receipt/payment with archived masters preserved. Reports manager date range payments gross/reversed/
net and service/stock totals, CSV authorized. No profit claims from current purchase price.

Stored function workshop_receipt_balance(receiptId) DECIMAL: active payments outstanding excluding
voided. Procedure workshop_daily_payments(from,to) daily active received gross/reversed/net report.
Trigger stock_movement_audit AFTER INSERT stock_movements inserts selected audit with actor,
entity StockMovement/context movement before/after. Laravel ledger audit uses inventory.stock_changed
not stock_movement.created; no duplicated event wording. DB objects reproducible drop down;
MySQL runner verify actual function/procedure/trigger interacting Laravel. SQLite fallback reports.

## Shared ownership
Inventory worker owns only new inventory/parts files 10-13 + tests.
Finance worker owns only receipt/payment files15-17 + tests/render/views.
Photo worker owns only docs files14 + tests.
Parent owns all shared models ServiceOrder/User, actions UpdateServiceOrder/SaveServiceJob,
routes/providers/sidebar/dashboard/Services view, settings/ops files18-19 + integration tests/scripts.
No workers run working DB migrations; parent runs after isolation tests. Named formatter only until
workers finish. Shared differences inspected; failed command never fabricated as success.
