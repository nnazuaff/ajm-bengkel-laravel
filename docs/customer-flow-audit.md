# Customer flow revision: audit and implementation boundaries

Authority: `CUSTOMER_FLOW_REVISION.md`; incremental changes only. Existing admin, finance, inventory ledger, photos and receipts remain intact.

## Existing implementation

- `customers.phone` already unique, canonicalized via `WorkshopInput`; `customers.user_id` nullable unique. Customers exist without accounts.
- `vehicles.license_plate` globally unique, canonicalized; vehicles belong to customers. Retain this stronger constraint and reject conflicting ownership rather than silently transfer vehicles.
- Registration previously created only User. Phone is now required for new registration; older accounts can supply it while booking.
- Booking previously held snapshots only; master resolution occurred at reception. New bookings resolve Customer and Vehicle atomically and retain request snapshots without overwriting trusted master details.
- Manual walk-in already transactional, guards active vehicle orders, validates mileage, and creates audit entries. Retained; shared resolvers replace duplicated identity logic.
- Customer portal relies on trusted `Customer.user_id`, never arbitrary phone/name matches. Preserve ownership checks.
- No QR check-in/code/queue existed.

Read-only working database audit: 2 customers, 2 vehicles, 4 users; existing phone/plate values canonical under the current helper. No row content or credentials recorded. Normalization migration performs its own conflict audit including archived rows before writing; conflicts block migration, not silently merge/delete records.

## Identity safety decision

Phone matching deduplicates operational records; it is not proof of account ownership. Newly created Customer may be linked to the registering User atomically. When an existing phone belongs to an offline Customer, registration reuses that record without granting historic portal access until identity is confirmed by staff during reception. Email verification disabled locally is not proof of ownership; do not auto-link by unverified email. Existing trusted user-to-customer relationships take precedence and cannot be replaced by submitted phone data.

Guest booking/check-in never reveals matching customers, vehicles, receipts or history publicly. Staff queue may show them under authorization. No SMS OTP dependency introduced.

## Implementation interfaces

- `CustomerResolver::resolve(array): Customer`, `forUser(User,array): Customer`.
- `VehicleResolver::resolve(Customer,array): Vehicle`.
- `CreateBooking::create(User,array)` and `guest(array)`.
- Booking nullable customer/vehicle FKs; legacy snapshots retained and safe backfill only when owner and identifiers agree.
- Public `/booking/guest`, `/check-in`; protected `/check-ins`, `/check-ins/qr`.
- Six-digit rotating code stored encrypted; public QR contains only permanent URL, not active code. Staff-only visibility/rotation; audit excludes code.
- Check-in conversion uses dedicated staff-authorized intake entrypoint; normal manual walk-in remains manager-only. Mechanics processing check-ins self-assign; cannot assign another mechanic.
- Critical operations transactional, rate limits on public server actions, duplicate submission prevention.

## Final validation checkpoint (latest)

QR physical-phone/public-access test reported passed by the user; no independent
phone test claimed. Local automated QR decode remains covered. Parent reran 811
tests /3499 assertions, formatter, PHPStan (0 errors with optional turbo warning),
TypeScript, frontend build, 23 browser-search checks and Blade compilation: passed.
Isolated MySQL migration/rollback/reapply, transaction and concurrent allocation,
conversion, stock, finance checks passed. Working owner/demo data untouched by QA.

Chromium .hermes/qa/browser-SEXOhn: 56/56 checks, zero console errors. Same new
check-in order reached mechanic inspection, owner approval/pricing, mechanic work,
part deduction, private photo upload, completion, owner receipt final/payment,
customer service evidence, own web receipt and real PNG download. Tests use separate
visitor/mechanic/cashier sessions and isolated SQLite/storage, not working accounts.

Review regression: rejected duplicate check-in from another browser previously
retained the submitted password in the Livewire response state. Credentials are now
cleared before every action result and on every dehydration, including non-submit
updates and already-submitted calls. Credential tests submit all deferred password
updates in one request, matching the real browser. Duplicate/validation/success and
non-submit snapshot regression tests passed.

Independent review identified conditional session fixation (requires prior knowledge
of an accepted guest cookie; no cookie-planting vector established) and caller-supplied
password retention in non-submit responses. New regression tests reproduced both.
Before storing the handoff, session migrate(true) rotates the ID and destroys the old
session without breaking Livewire CSRF/polling. Browser regression uses a separate
context with the old guest cookie: no waiting proof and redirect to login, not the
new customer's portal. Handoff still redeemed only once in the original rotated
session. Independent re-review completed with no remaining findings within the two
correction paths: session destruction before handoff, preserved CSRF/polling,
one-use redemption and empty credential snapshots. Reviewer and parent each ran
16 focused tests /99 assertions successfully on isolated SQLite/array sessions.
This is a scoped code review, not a production penetration-test certification.

The local demo database was rebuilt with migrate:fresh only after explicit user
approval. Normal updates continue to use migrate; production/destructive resets
are not part of the release workflow. No demo account credential is stored in source.
Backend, UI and regression tests saved in grouped local commits:
97327c8 (customer/check-in domain), 50bcc4a (responsive operational UI),
19ea331 (integration/security tests). Documentation is the remaining local snapshot.
No push authorized or performed in this checkpoint.

## Dashboard check-in and reusable credentials revision (previous checkpoint)

Customer portal and sidebar expose Check-in. Logged-in customer contact comes exclusively from trusted linked Customer/User, never editable request contact. Waiting/processing blocks new entries regardless of age; converted/cancelled permits another check-in. Dashboard disables its submit navigation while pending and offers status access. Server duplicate guard locks canonical customer under the active code transaction. Cancelled waiting sessions reset when a logged customer reopens the form.

Public web check-in registration requires unique email, password and confirmation, using the existing Laravel password rules; password is hashed and removed from Livewire state on success/failure. Existing phone-linked accounts must log in, never have credentials overwritten. A genuinely new Customer needs no reception checkbox; offline-master matches remain unlinked until an explicit identity check. No fake email verification: identity acceptance remains separate. Email/password re-login after reception is tested through actual Fortify login POST. Legacy internal SubmitCheckIn callers with no browser handoff retain compatibility and old explicit reception verification. Existing passwordless accounts are not assigned new credentials from public phone input.

Removed Akses akun action from the customer-list UI; legacy recovery component/action retained internally for compatibility, not required for normal customer intake. Receipt navigation for unfinished services now redirects to their detail with a clear notice, no 422 exception; Bon servis action appears only for completed/ready/delivered services or an existing receipt. Server-side finance lifecycle checks remain unchanged.

Latest gate: 808 tests /3481 assertions, PHPStan zero errors, formatter/TypeScript/build passed. 56/56 real Chromium checks, zero console errors: guest credential creation, no-checkbox new intake, independent browser auto dashboard, logged repeat check-in, pending disabled state, mechanic cancellation and re-enabled dashboard. Isolated MySQL migration rollback/reapply/concurrency passed; migration000028 normal applied without reset. No commit/push. Physical phone/public network testing remains pending.

## Automatic account and waiting dashboard revision (previous checkpoint)

Check-in now provisions a customer-role User automatically when neither a linked account nor a phone-matching User (including archived) exists. Email remains nullable; no dummy email or displayed default password. Submitted public email is contact information, not verified account identity. Newly inserted Customer is linked to this new User; existing offline history remains unlinked until reception.

Browser stores a random handoff in the server-side session; CheckIn stores only SHA-256 hash plus 24-hour expiration, not exposed in the Livewire snapshot. Waiting page survives refresh and polls every five seconds. Staff must explicitly verify identity, vehicle ownership and the waiting browser in person before confirming an automatically provisioned account. Conversion and account activation are one transaction; audit failure rolls both back. User identity verification is separate from email verification.

After confirmed service creation, only the original browser can consume its handoff once, regenerate the session and log in to the associated customer-role account. Authenticated customers may continue to their own dashboard without switching accounts. Guest attempts against an existing account never log in from phone/email alone; normal login or owner recovery remains required. Duplicate submissions cannot replace another browser's handoff. Expiration, cancellation, archival, role change or account relinking prevents automatic login.

Customer dashboard displays recent check-ins and links to own service details. Re-entry from another device is not implemented for newly provisioned passwordless accounts: normal existing-account login or staff recovery remains necessary; no OTP delivery dependency added. Physical phone/public URL test still pending.

Latest verification: 801 tests /3431 assertions passed, lint/PHPStan/TypeScript/build passed; Chromium 56/56 checks zero console errors. Isolated separate visitor and mechanic browser contexts proved waiting reload, mobile UI, identity checkbox, polling automatic dashboard redirect and own service details. MySQL migrations/rollback/reapply and check-in conversion passed. Migration000027 applied normally; no data reset, commit or push. Independent security review of this additional handoff revision pending.

## Previous revision verification

Integrated results: 793 automated tests / 3384 assertions passed; formatter, PHPStan (0 errors; existing optional turbo-extension warning), TypeScript, build passed. Chromium isolated QA: 56/56 checks, zero console errors; guest booking created correct masters, mechanic rotated code, public check-in submitted, queue intake created a self-assigned Service Order. QR SVG decoded with an independent rasterizer/jsQR; payload equals the check-in URL and excludes the active code. Audit-veto rollback for code rotation/cancellation and cross-mechanic idempotent retry authorization regression tests passed. Mobile 390px public/queue checked; existing manual intake and finance smoke preserved. Isolated MySQL migration rollback/reapply, transactions/concurrency and encrypted code/duplicate check-in/idempotent mechanic intake passed.

Independent focused security review completed: no remaining findings within customer resolution, booking intake, code management and check-in conversion scope. Reviewer ran 147 tests / 602 assertions and isolated SQLite probes for offline ownership, code rotation/disable audit rollback and mechanic retry boundaries. Parent reran 32 focused tests / 123 assertions after handoff; passed. This is a scoped review, not production penetration-test certification.

Five incremental working migrations (000022–000026) applied normally, no fresh/reset. Historical transaction rows are not deleted; canonical master identifiers retained, safe booking relation backfill only. Existing uncommitted responsive/modal/validation work preserved. No commit/push.

Use `/booking/guest` for guest booking, `/check-in` for public on-site check-in, sidebar Customer check-in for staff queue/code/QR. Staff must generate the first code; six digits, rotates manually, expires after 24 hours, optional disable. QR contains only the public check-in URL. Configure a reachable APP_URL before printing QR; localhost is not usable from customer phones. Registration now requires phone; existing accounts may supply phone during booking. Old offline history remains protected pending staff identity confirmation during booking reception. New registration safely links newly created masters only.

Remaining ceiling: no SMS verification, no CAPTCHA, no external messaging/payment APIs, no arbitrary slot-capacity rules. Requested schedule remains a request pending staff confirmation; duplicate open vehicle/date/time bookings are rejected. Production email/network/deployment configuration and old Next.js visual migration remain separate work.
