# Real-browser smoke QA

Run from repository root:

```sh
npm install --prefix .hermes/qa/deps --no-audit --no-fund puppeteer-core
npm install --prefix .hermes/qa/qr-deps --no-audit --no-fund jsqr @resvg/resvg-js
# Reuses existing public/build; build separately only when needed.
# npm run build
node scripts/browser-smoke.mjs
```

Requires PHP SQLite, Composer dependencies, Node, installed Chromium and built Vite assets. Chromium is discovered under `~/.cache/ms-playwright/chromium-*/chrome-linux64/chrome`; otherwise set `QA_CHROMIUM=/absolute/path/to/chromium`. Puppeteer resolves from project dependencies or the dedicated scratch installation. No project dependencies are changed.

## Isolation and authentication

Each run creates `.hermes/qa/browser-*/database.sqlite`, storage, logs, view cache, screenshots and a separate Chromium profile. Migrations run only against this SQLite file; no development MySQL/SQLite records, `.env`, production routes or source files are changed. SQLite does not validate MySQL stored objects.

The standalone PHP router runs on a randomly selected localhost port; existing ports 8000/8001/5173 are untouched. It refuses non-testing environments, non-loopback HTTP clients, missing dedicated QA directory or missing 256-bit ephemeral token. Fixture login/state routes require a private header. Token and app key are generated in memory, never printed or documented. Fixture `Auth::login` uses explicitly seeded users; no browser password entry, guessed credentials or external account access. A dedicated session cookie prevents collisions with other local apps. Livewire's testing-only `tmp-for-tests` disk is explicitly bound to scratch storage so actual browser uploads work in `APP_ENV=testing`.

Only the runner's own PHP server/browser are stopped in `finally`. Artifacts remain for inspection; delete only an explicitly selected `.hermes/qa/browser-*` run when no longer needed. Tokens die with the process; SQLite contains synthetic QA fixtures only. Do not publish the router or run it with a production document root.

## Coverage

Current revision: 56 checks, zero console errors; pre-handoff guest session rotates, old-cookie browser cannot redeem login; same check-in order exercised through mechanic diagnosis/work/part/photo/completion, cashier finalization/payment, customer private evidence/web receipt/PNG; separate visitor browser waits/reloads, mechanic confirms identity/intake, visitor polls and automatically authenticates to own dashboard then opens own service details; includes guest master creation, encrypted-code mechanic/public check-in, queue intake, mobile forms, actual SVG QR independent decode (URL only, no active code). QR decoding dependencies are scratch-only, not production dependencies. Physical phone scanning/public-network access still requires a reachable deployment URL.

Original 42-check baseline: public homepage, unauthenticated guard, fixture token guard; 13 owner/admin routes desktop and all routes plus public home at 390px; customer creation, vehicle creation, registered walk-in intake, mechanic assignment, diagnosis and legal transitions; completed job, spare-part stock/movement; actual file input upload and photo save; service completion, receipt draft/finalization/payment; HTTP receipt/PNG signature; direct sale stock deduction; own customer portal, another customer's receipt/image denied, customer admin denied; booking submission, mobile portal and console errors.

All mutations except explicit initial fixtures occur through real Chromium DOM interactions and actual Livewire requests. Assertions also inspect the isolated database through the guarded read-only QA state endpoint. Intentional 403/404 authorization console messages are excluded narrowly; all HTTP errors remain in the results. Exit status is nonzero on failed checks. Unexpected runtime setup failures also fail the command.

Artifacts: `results.json`, `report.md`, `final-state.json`, `server.log`, `storage/logs/laravel.log` when applicable, desktop/mobile screenshots, failure screenshot/text, upload response/call evidence, file-input validity state, receipt PNG/signature check. Private upload signed references in scratch evidence are expired/local only; never publish whole Chromium profiles or scratch artifacts without review.

**Coverage ceiling:** smoke QA is not exhaustive full-product certification. Mechanic-only permissions, all CRUD edit/archive permutations, admin booking conversion, settings mutations, financial reversals and public registration/password-login are not exhaustively exercised. PNG download is real HTTP; it is not a browser screenshot masquerading as application output.

## Initial failing run and verified correction

Run `.hermes/qa/browser-gPbmbg`: **38/40 checks passed**, one product defect causes both failures. All 13 admin desktop routes and 14 mobile pages passed; no document-wide overflow at 390px. Customer/vehicle creation, walk-in, assignment, diagnosis, job, part use, completion, receipt final/payment/PNG, direct sale, booking and ownership guards passed.

**High / functional: documentation photo cannot be saved.**

1. Owner opens Services, receives registered walk-in, assigns QA Mechanic, progresses to `in_progress`.
2. Opens documentation, chooses valid `fixture.png` via real file input.
3. Actual upload endpoint returns HTTP 200 with signed temporary path; preview appears. Input remains valid with `fixture.png`.
4. Fills caption, clicks **Simpan foto**.
5. Console: `Livewire Expression Error: Cannot read properties of undefined (reading 'name')`, expression `upload`; subsequently `TypeError: Cannot read properties of undefined (reading 'name')`.
6. No server `upload` method request is sent. Documentation count stays zero; no success notice.

`wire:submit="upload"` collides with Livewire's built-in client upload helper (`$wire.upload(name, file, ...)`). Parent independently reproduced 38/40 failure, renamed PHP action/form/targets to savePhoto, updated regression tests. Parent rerun: 40/40 passed; extended customer private evidence and mobile receipt-click checks: 42/42 passed, console_errors=0, artifacts .hermes/qa/browser-tMtEis/.

Evidence: `.hermes/qa/browser-gPbmbg/results.json`, `failure-34.png`, `failure-34.txt`, `photo-form-state.json`, `upload-response.txt`, `upload-calls-_startUpload.json`, `upload-calls-_finishUpload.json`. `_finishUpload` completes; no `upload-calls-upload.json`. PHP harness passes named Pint and PHP syntax; JS syntax passed.
