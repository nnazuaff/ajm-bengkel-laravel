# Customer/public extension

User expands all admin completion to public/customer too. Admin workers retain existing whitelist.
No WhatsApp API/QRIS gateway/customer estimate approval/reminders/multibranch.

## Ownership decision
Customer.user_id unique nullable remains trusted link. Never auto-link by phone/email/booking submission.
Admin/owner explicit verified link action after offline ownership verification, logs IDs only.
Owner/admin may link active CUSTOMER role account; unique account prevents multiple customer masters.
Unlink/relink requires confirmation, audit; finalized history retains customer relation ownership current
account (intended account correction) and permission guards. Customer cannot set user_id anywhere.
Booking submitted_by only booking request ownership, not proof vehicle ownership.

CustomerPortal Livewire auth verified/customer gate /portal portal.index (legacy portal name preserve
route('portal')); own Customer::where(user_id=Auth.id) active only, vehicles active or archived
owned history allowed, selected vehicle/order IDs Locked and policy+relation checked each request.
Unlinked state truthful: contact staff verify account; can create online booking but no vehicle leak.
Display own vehicles, service progress/mileage/complaint/diagnosis/jobs/work photo/parts,
receipt grand total/payment and web/PNG links. Cannot mutate service/jobs/prices/stock/photos.
Existing CustomerBooking retains own request list/cancel. Portal may link to /booking.

Customer receipts separate CustomerReceiptController show(Receipt),image(Receipt) verify customer
role and receipt.customer_id exists customer.user_id == actor.id, not guessed sequential identifiers.
Protected /portal/receipts/{receipt} customer.receipts.show and /portal/receipts/{receipt}/image
customer.receipts.image. No public unauthed share links initial; Receipt.public_id future only.
Use actual ReceiptImage render shared and snapshot view as available, do not grant customer finance
policy create/update/list. Draft receipt NOT exposed to customer, final/paid/voided allowed display
void status truthful, nonsequential ID optional not needed under authorization.

CustomerDocumentationController show(ServiceDocumentation) protects customer owner through
photo.order.customer.user_id; only nondeleted photo/order customer own, private file no arbitrary
route path, inline MIME jpg/png/webp nosniff/private headers; defer reuse same service reader until
policy infrastructure supports role with no upload permission. No customer write photos.

Public root landing uses WorkshopSetting::current identity only supplied fields; no invented hours,
pricing, address, services count. Primary booking CTA authenticated /booking redirects login;
explicit account required. Link login/register true Fortify, concise local operational copy, same
palette/Typography/no stock imagery necessary. Public contact renders phone/address only when
configured. Workshop logo optional configured private/public image path not guessed.

Files portal worker: app/Livewire/CustomerPortal.php, resources/views/livewire/customer-portal.blade.php,
app/Http/Controllers/{CustomerReceiptController,CustomerDocumentationController,PublicHomeController}.php,
resources/views/public/home.blade.php, tests/Feature/CustomerPortalTest.php tests/Feature/PublicSiteTest.php.
Parent all routes/shared nav and customer access linking module. Don't edit finance/documentation
workers files; inspect actual ReceiptImage/controller/views schemas may still arriving.
Parent adds customer receipts/photo routes auth verified can:customer-portal; portal route points class.
