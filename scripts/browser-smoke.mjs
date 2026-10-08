import assert from 'node:assert/strict';
import { spawn, spawnSync } from 'node:child_process';
import { randomBytes } from 'node:crypto';
import { createRequire } from 'node:module';
import { mkdirSync, mkdtempSync, writeFileSync, existsSync, readdirSync } from 'node:fs';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { createServer } from 'node:net';

const root = fileURLToPath(new URL('../', import.meta.url));
const require = createRequire(import.meta.url);
let puppeteer;
try { puppeteer = require('puppeteer-core'); }
catch { puppeteer = require(join(root, '.hermes/qa/deps/node_modules/puppeteer-core')); }
const cache = join(process.env.HOME, '.cache/ms-playwright');
const chromium = process.env.QA_CHROMIUM || (existsSync(cache) ? readdirSync(cache).filter(n => /^chromium-/.test(n)).map(n => join(cache, n, 'chrome-linux64/chrome')).find(existsSync) : null);
assert(chromium, 'Set QA_CHROMIUM to an installed Chromium executable.');
mkdirSync(join(root, '.hermes/qa'), { recursive: true });
const out = mkdtempSync(join(root, '.hermes/qa/browser-'));
const token = randomBytes(32).toString('hex');
const env = { ...process.env, APP_ENV: 'testing', AUTH_REQUIRE_EMAIL_VERIFICATION: 'false', APP_DEBUG: 'true', APP_KEY: 'base64:' + randomBytes(32).toString('base64'), QA_DIR: out, QA_TOKEN: token };
const init = spawnSync('php', ['scripts/browser-smoke.php', 'init'], { cwd: root, env, encoding: 'utf8' });
assert.equal(init.status, 0, init.stdout + init.stderr);
const probe = createServer();
await new Promise(resolve => probe.listen(0, '127.0.0.1', resolve));
const port = probe.address().port;
await new Promise(resolve => probe.close(resolve));
const base = `http://127.0.0.1:${port}`;
env.APP_URL = base;
const server = spawn('php', ['-S', `127.0.0.1:${port}`, '-t', 'public', 'scripts/browser-smoke.php'], { cwd: root, env, stdio: ['ignore', 'pipe', 'pipe'] });
let serverLog = '';
server.stdout.on('data', d => serverLog += d);
server.stderr.on('data', d => serverLog += d);
let browser;
const results = [], errors = [], warnings = [], network = [];
try {
    for (let n = 0; ; n++) {
        try { if ((await fetch(base + '/up')).ok) break; } catch {}
        assert(n < 60, 'QA server readiness failed');
        await new Promise(r => setTimeout(r, 100));
    }
    browser = await puppeteer.launch({ executablePath: chromium, headless: true, args: ['--no-sandbox', '--disable-dev-shm-usage'], userDataDir: join(out, 'chromium') });
    const page = await browser.newPage();
    await page.setViewport({ width: 1440, height: 1000 });
    page.on('console', m => {
        if (m.type() === 'warn') warnings.push({ url: page.url(), message: m.text() });
        if (m.type() !== 'error') return;
        const expectedDenial = (page.url() === base + '/customers' && m.text().includes('403 (Forbidden)')) || ([base + '/portal/receipts/1', base + '/portal/receipts/1/image'].includes(page.url()) && m.text().includes('404 (Not Found)'));
        if (!expectedDenial) errors.push({ url: page.url(), message: m.text(), stack: m.stackTrace() });
    });
    page.on('pageerror', e => errors.push({ url: page.url(), message: e.message, stack: e.stack }));
    page.on('response', async r => {
        if (r.status() >= 400) network.push({ url: r.url(), status: r.status() });
        if (r.url().includes('upload-file')) { try { writeFileSync(join(out, 'upload-response.txt'), `${r.status()}\n${await r.text()}`); } catch {} }
        if (r.request().method() === 'POST' && r.url().includes('livewire')) {
            try {
                const body = JSON.parse(r.request().postData() || '{}');
                const calls = (body.components || []).flatMap(c => c.calls || []).map(c => c.method);
                if (calls.some(c => ['_startUpload', '_finishUpload', 'upload'].includes(c))) writeFileSync(join(out, 'upload-calls-' + calls.join('-') + '.json'), JSON.stringify({ calls, response: await r.json() }, null, 2));
            } catch {}
        }
    });
    page.on('dialog', d => d.accept());
    await page.setRequestInterception(true);
    page.on('request', r => r.continue({ headers: { ...r.headers(), ...(r.url().startsWith(base + '/__qa/') ? { 'X-QA-Token': token } : {}) } }));
    const state = async () => (await fetch(base + '/__qa/state', { headers: { 'X-QA-Token': token } })).json();
    const idle = async () => {
        await page.waitForNetworkIdle({ idleTime: 400, timeout: 15000 });
        assert.equal(await page.$$eval('dialog[open] [data-flux-modal-close]', controls => controls.length), 0, 'Duplicate built-in modal close control');
    };
    const go = async path => { const response = await page.goto(base + path, { waitUntil: 'networkidle0' }); return response.status(); };
    const model = (name, tag = '') => `${tag}[wire\\:model="${name}"]`;
    const fill = async (name, value) => {
        const selector = model(name);
        await page.waitForSelector(selector);
        await page.$eval(selector, (el, value) => { el.value = value; el.dispatchEvent(new Event('input', { bubbles: true })); el.dispatchEvent(new Event('change', { bubbles: true })); }, String(value));
    };
    const select = async (name, value) => { await page.select(model(name, 'select'), String(value)); await idle(); };
    const button = async text => {
        const handle = await page.evaluateHandle(text => [...document.querySelectorAll('button,a')].find(e => e.textContent.trim() === text && e.getClientRects().length), text);
        assert(await handle.asElement(), `Missing button: ${text}`);
        await handle.asElement().click(); await idle();
    };
    const textHas = async text => assert((await page.$eval('body', e => e.innerText)).includes(text), `Missing text: ${text}`);
    const test = async (name, fn) => {
        try { await fn(); results.push({ name, pass: true }); }
        catch (e) { results.push({ name, pass: false, error: e.message }); writeFileSync(join(out, 'failure-' + results.length + '.txt'), await page.$eval('body', e => e.innerText)); await page.screenshot({ path: join(out, 'failure-' + results.length + '.png'), fullPage: true }); }
        writeFileSync(join(out, 'results.json'), JSON.stringify({ results, errors, warnings, network }, null, 2));
    };
    await test('Public home and unauthenticated admin guard', async () => { assert.equal(await go('/'), 200); assert.equal(await go('/customers'), 200); assert(page.url().endsWith('/login')); });
    await test('Fixture token guard', async () => { assert.equal((await fetch(base + '/__qa/state')).status, 403); });
    await go('/__qa/login/owner');
    const routes = ['/dashboard', '/customers', '/vehicles', '/bookings', '/services', '/inventory', '/receipts', '/payments', '/history', '/mechanics', '/reports', '/audit-log', '/workshop-settings'];
    for (const route of routes) await test(`Admin desktop ${route}`, async () => { assert.equal(await go(route), 200); assert(!page.url().includes('/login')); assert(await page.$('h1')); });
    await test('Grouped sidebar desktop and mobile navigation', async () => {
        await go('/dashboard');
        assert.deepEqual(await page.$$eval('[data-sidebar-section]', groups => groups.map(group => group.dataset.sidebarSection)), ['summary', 'operations', 'masters', 'finance', 'management']);
        await page.setViewport({ width: 390, height: 844 });
        await page.click('[aria-label="Buka navigasi"]'); await idle();
        const sidebar = await page.$('ui-sidebar');
        await sidebar.screenshot({ path: join(out, 'sidebar-mobile.png') });
        await page.click('[data-sidebar-section="operations"] a[href$="/services"]'); await idle();
        assert(page.url().endsWith('/services'));
        await page.setViewport({ width: 1440, height: 1000 });
    });
    await go('/dashboard');
    await page.screenshot({ path: join(out, 'admin-dashboard.png'), fullPage: true });
    for (const route of ['/', ...routes]) await test(`Mobile 390px ${route}`, async () => { await page.setViewport({ width: 390, height: 844 }); assert.equal(await go(route), 200); const size = await page.evaluate(() => ({ width: innerWidth, scroll: document.documentElement.scrollWidth })); assert(size.scroll <= size.width + 1, JSON.stringify(size)); });
    await page.screenshot({ path: join(out, 'admin-mobile.png'), fullPage: true });
    await page.setViewport({ width: 1440, height: 1000 });
    await test('Inventory category creation and confirmed deletion through UI', async () => {
        await go('/inventory'); await button('Kelola kategori');
        await fill('categoryName', 'QA Disposable Category'); await button('Simpan kategori'); await button('Kelola kategori');
        const remove = await page.$('[aria-label="Hapus kategori QA Disposable Category"]'); assert(remove);
        await remove.click(); await idle(); await textHas('Kategori berhasil dihapus.');
        assert.equal(await page.$('[aria-label="Hapus kategori QA Disposable Category"]'), null);
    });
    await test('Customer create through UI' , async () => { await go('/customers'); await button('Tambah pelanggan'); await fill('form.name', 'QA Browser Customer'); await fill('form.phone', '081234567899'); await button('Simpan pelanggan'); assert((await state()).customers.some(c => c.name === 'QA Browser Customer')); await textHas('QA Browser Customer'); });
    await test('Customer modal edit cancel reset and account popup', async () => {
        await go('/customers'); await page.click('[aria-label="Edit QA Browser Customer"]'); await idle();
        await fill('form.name','QA Browser Customer edited'); await button('Simpan pelanggan'); await textHas('QA Browser Customer edited');
        await button('Tambah pelanggan'); await fill('form.name','Unsaved draft'); await page.keyboard.press('Escape'); await idle();
        await button('Tambah pelanggan'); assert.equal(await page.$eval(model('form.name'),e=>e.value),''); await button('Batal');
        const customer=(await state()).customers.find(c=>c.name==='QA Browser Customer edited');
        await page.click(`[wire\\:click="openAccount(${customer.id})"]`); await idle(); await textHas('Akun portal pelanggan'); await button('Batal');
    });
    await test('Vehicle create through UI' , async () => { await go('/vehicles'); await button('Tambah kendaraan'); const customer = (await state()).customers.find(c => c.name === 'QA Browser Customer edited'); assert(customer); await select('form.customer_id', customer.id); await fill('form.license_plate', 'B 2222 QA'); await fill('form.brand', 'Honda'); await fill('form.model', 'Vario'); await fill('form.latest_mileage', '2000'); await button('Simpan kendaraan'); assert((await state()).vehicles.some(v => v.license_plate === 'B2222QA')); });
    await test('Inventory modal restock adjustment history and draft cancel', async () => {
        await go('/inventory'); await page.click('[aria-label="Restok QA Motor Oil"]'); await idle();
        await fill('stockForm.quantity','1'); await fill('stockForm.reason','QA modal restock'); await button('Catat mutasi'); assert.equal((await state()).stock,21);
        await page.click('[aria-label="Koreksi stok QA Motor Oil"]'); await idle();
        await fill('stockForm.quantity','-1'); await fill('stockForm.reason','QA modal correction'); await button('Catat mutasi'); assert.equal((await state()).stock,20);
        await page.click('[aria-label="Riwayat stok QA Motor Oil"]'); await idle(); await textHas('QA modal correction');
        assert.equal(await page.$$eval('dialog[open] button', buttons => buttons.filter(button => button.textContent.trim() === 'Tutup riwayat').length), 1);
        await button('Tutup riwayat'); await page.waitForFunction(() => !document.querySelector('dialog[open]'));
        await page.click('[aria-label="Riwayat stok QA Motor Oil"]'); await idle();
        await page.keyboard.press('Escape'); await idle(); await page.waitForFunction(() => !document.querySelector('dialog[open]'));
        await button('Tambah barang'); await fill('form.sku','UNSAVED-DEMO'); await button('Batal');
        await button('Tambah barang'); assert.equal(await page.$eval(model('form.sku'),e=>e.value),''); await button('Batal');
    });
    await test('Staff edit modal saves without changing credentials', async () => {
        await go('/mechanics'); await page.click('[aria-label="Edit QA Mechanic"]'); await idle();
        assert.equal(await page.$('input[type=password]'),null); await fill('name','QA Mechanic Updated'); await button('Simpan staf'); await textHas('QA Mechanic Updated');
        await button('Tambah staf'); await textHas('Kata sandi'); await button('Batal'); await page.waitForFunction(()=>!document.querySelector('dialog[open]'));
    });
    await test('Admin mobile modal controls remain scrollable and dismissible', async () => {
        await page.setViewport({width:390,height:844});
        for(const [route,label] of [['/customers','Tambah pelanggan'],['/vehicles','Tambah kendaraan'],['/inventory','Tambah barang'],['/mechanics','Tambah staf'],['/services','Terima walk-in']]) {
            await go(route); await button(label); await page.waitForSelector('dialog[open]');
            const bounds=await page.$eval('dialog[open]',e=>({left:e.getBoundingClientRect().left,right:e.getBoundingClientRect().right,height:e.clientHeight}));
            assert(bounds.left>=0 && bounds.right<=391 && bounds.height<=844,JSON.stringify({route,...bounds}));
            await page.keyboard.press('Escape'); await idle(); await page.waitForFunction(()=>!document.querySelector('dialog[open]'));
        }
        await page.setViewport({width:1440,height:1000});
    });
    let orderId, receiptId;
    await test('Walk-in assignment and diagnosis through UI', async () => {
        await go('/services'); await button('Terima walk-in');
        await page.$eval('[wire\\:model\\.live\\.debounce\\.300ms="intakeSearch"]', e => { e.value = 'B 1001 QA'; e.dispatchEvent(new Event('input', { bubbles: true })); }); await idle();
        await page.waitForSelector('[aria-label="Pilih motor B1001QA"]'); await page.click('[aria-label="Pilih motor B1001QA"]'); await idle();
        await fill('intake.current_mileage', '1500'); await fill('intake.complaint', 'QA browser noisy brakes'); await select('intake.mechanic_id', 2); await button('Terima servis');
        const own = (await state()).orders.find(o => o.customer_id === 1); assert(own); orderId = own.id;
        await go(`/services/${orderId}`);
        await fill('detail.diagnosis', 'QA worn brake pad');
        for (const status of ['inspection', 'approved', 'in_progress']) { await select('detail.status', status); await button('Simpan perubahan'); assert.equal((await state()).orders.find(o => o.id === orderId).status, status); }
    });
    await test('Job completed and part deducted through UI', async () => { assert(orderId, 'Walk-in prerequisite failed'); await button('Tambah pekerjaan'); await fill('form.name', 'QA Replace brake pad'); await fill('form.labor_price', '25000.00'); await select('form.status', 'completed'); await button('Simpan pekerjaan'); await textHas('QA Replace brake pad'); await select('inventoryItemId', 1); await fill('quantity', '1'); await button('Pakai part'); assert.equal((await state()).stock, 19); assert.equal((await state()).movement_count, 4); });
    await test('Photo upload through UI', async () => {
        assert(orderId, 'Walk-in prerequisite failed');
        // Real valid image fixture generated locally; never touches persistent application storage.
        const png = join(out, 'fixture.png');
        writeFileSync(png, Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a1ioAAAAASUVORK5CYII=', 'base64'));
        const input = await page.$('input[type=file]'); assert(input); await input.uploadFile(png); await idle(); await page.waitForFunction(() => ![...document.querySelectorAll('button')].find(e => e.textContent.trim() === 'Simpan foto')?.disabled); await fill('caption', 'QA brake evidence'); writeFileSync(join(out, 'photo-form-state.json'), JSON.stringify(await page.$eval('input[type=file]', e => ({ files: [...e.files].map(f => f.name), validity: e.validity.valid, message: e.validationMessage, outerHTML: e.outerHTML })), null, 2)); await button('Simpan foto'); assert.equal((await state()).photo_count, 1);
    });
    await test('Service complete and receipt finalize/pay through UI', async () => {
        assert(orderId, 'Walk-in prerequisite failed'); await select('detail.status', 'completed'); await button('Simpan perubahan'); assert.equal((await state()).orders.find(o => o.id === orderId).status, 'completed');
        await go(`/receipts/create?service_order_id=${orderId}`); await button('Simpan draf'); receiptId = (await state()).receipts.find(r => r.service_order_id === orderId)?.id; assert(receiptId); await button('Finalisasi bon'); const receipt = (await state()).receipts.find(r => r.id === receiptId); assert.equal(receipt.grand_total, '40000.00'); await fill('amount', '40000.00'); await button('Simpan pembayaran'); assert.equal((await state()).receipts.find(r => r.id === receiptId).status, 'paid');
        assert.equal(await go(`/receipts/${receiptId}/view`), 200);
        const png = await page.evaluate(async url => { const r = await fetch(url); const bytes = [...new Uint8Array(await r.arrayBuffer())]; return { status: r.status, type: r.headers.get('content-type'), signature: bytes.slice(0, 8), bytes: bytes.length }; }, `${base}/receipts/${receiptId}/image`);
        assert.equal(png.status, 200); assert.equal(png.type, 'image/png'); assert.deepEqual(png.signature, [137,80,78,71,13,10,26,10]); writeFileSync(join(out, 'receipt-image-check.json'), JSON.stringify(png));
        const image = await page.evaluate(async url => [...new Uint8Array(await (await fetch(url)).arrayBuffer())], `${base}/receipts/${receiptId}/image`);
        writeFileSync(join(out, 'service-receipt.png'), Buffer.from(image));
    });
    await test('Direct sale UI stock deduction', async () => { await go('/receipts'); await button('Penjualan baru'); await button('Tambah produk'); await select('items.0.inventory_item_id', 1); await button('Simpan draf'); await button('Finalisasi bon'); assert.equal((await state()).stock, 18); });
    await go('/__qa/login/customer');
    await test('Customer own portal, admin forbidden, other receipt forbidden', async () => { assert.equal(await go('/portal'), 200); await textHas('B1001QA'); assert(!(await page.$eval('body', e => e.innerText)).includes('QA private other complaint')); assert.equal(await go('/customers'), 403); assert.equal(await go('/portal/receipts/1'), 404); assert.equal(await go('/portal/receipts/1/image'), 404); if (receiptId) { assert.equal(await go(`/portal/receipts/${receiptId}`), 200); } });
    await test('Customer service detail and private evidence through UI', async () => {
        await go('/portal'); await button('Detail servis'); await textHas('QA worn brake pad'); await textHas('QA brake evidence');
        const photo = await page.$('a[href*="/portal/documentation/"]'); assert(photo);
        const url = await photo.evaluate(e => e.href);
        const evidence = await page.evaluate(async url => { const response = await fetch(url); return {status: response.status, type: response.headers.get('content-type')}; }, url);
        assert.equal(evidence.status, 200); assert.equal(evidence.type, 'image/png');
    });
    await test('Mobile customer receipt navigation through UI', async () => {
        await page.setViewport({width:390,height:844}); await go('/portal');
        const link = await page.$(`a[href="${base}/portal/receipts/${receiptId}"]`); assert(link);
        await link.evaluate(e => e.scrollIntoView({block:'center'}));
        await Promise.all([page.waitForNavigation({waitUntil:'networkidle0'}), link.click()]);
        assert(page.url().endsWith(`/portal/receipts/${receiptId}`)); await textHas('40000.00');
        await page.setViewport({width:1440,height:1000});
    });
    await test('Booking modal visibility cancel Escape reset and mobile scroll', async () => {
        await go('/booking'); assert.equal(await page.$('form[wire\\:submit="submit"]'),null);
        await button('Buat booking'); await page.waitForSelector('dialog[open]');
        await page.waitForFunction(()=>document.activeElement?.closest('dialog[open]'));
        await fill('form.phone','081299999999'); await button('Batal'); await page.waitForFunction(()=>!document.querySelector('dialog[open]'));
        assert.equal(await page.evaluate(()=>document.activeElement?.textContent.trim()),'Buat booking');
        await button('Buat booking'); assert.equal(await page.$eval(model('form.phone'), e=>e.value),'');
        await page.keyboard.press('Escape'); await idle(); await page.waitForFunction(()=>!document.querySelector('dialog[open]'));
        await page.setViewport({width:390,height:844}); await button('Buat booking');
        const bounds=await page.$eval('dialog[open]', e=>({left:e.getBoundingClientRect().left,right:e.getBoundingClientRect().right,height:e.clientHeight,scroll:e.scrollHeight}));
        assert(bounds.left>=0 && bounds.right<=391); assert(bounds.scroll>bounds.height);
        await page.screenshot({path:join(out,'booking-modal-mobile.png'),fullPage:true});
        await button('Batal'); await page.setViewport({width:1440,height:1000});
    });
    await test('Customer booking UI submission'  , async () => {
        await go('/booking'); await button('Buat booking');
        for (const [name, value] of Object.entries({ name: 'QA Portal Owner', phone: '08120000001', license_plate: 'B 1001 QA', brand: 'Honda', model: 'Vario', current_mileage: '1500', arrival_time: '10:00', service_type: 'QA Maintenance', complaint: 'QA booking check' })) await fill('form.' + name, value);
        const date = await page.$eval(model('form.booking_date'), e => e.min); await fill('form.booking_date', date); await button('Ajukan booking'); assert.equal((await state()).booking_count, 1);
        await page.waitForFunction(()=>!document.querySelector('dialog[open]')); await textHas('Booking berhasil diajukan'); await textHas('QA Maintenance');
    });
    await test('Customer mobile portal', async () => { await page.setViewport({ width: 390, height: 844 }); await go('/portal'); const size = await page.evaluate(() => [innerWidth, document.documentElement.scrollWidth]); assert(size[1] <= size[0] + 1, JSON.stringify(size)); await page.screenshot({ path: join(out, 'customer-portal-mobile.png'), fullPage: true }); });
    await test('Unverified account booking then archived contact restoration and trusted linking UI', async () => {
        await page.setViewport({width:1440,height:1000}); await go('/__qa/login/reception'); await go('/booking'); await button('Buat booking');
        for (const [name,value] of Object.entries({name:'QA Reception Customer',phone:'08120000003',license_plate:'B 3003 QA',brand:'Honda',model:'Beat',current_mileage:'300',arrival_time:'10:00',service_type:'QA reception',complaint:'QA new reception complaint'})) await fill('form.'+name,value);
        await fill('form.booking_date', await page.$eval(model('form.booking_date'),e=>e.min));
        await button('Ajukan booking'); const booking=(await state()).bookings.at(-1); assert(booking);
        await go('/__qa/login/owner'); await go('/bookings');
        await page.click(`[wire\\:click="openBooking(${booking.id})"]`); await idle();
        for (const status of ['confirmed','arrived']) {
            await page.select('#booking-status',status); await idle(); await button('Simpan perubahan');
        }
        await textHas('Pulihkan data arsip');
        await page.click('[wire\\:model="detail.restore_archived"]');
        await page.click('[wire\\:model="detail.ownership_verified"]');
        await button('Terima servis'); await textHas('Servis berhasil dibuat');
        const data=await state(); const restored=data.customers.find(c=>c.name==='QA Archived Reception'); assert(restored?.user_id);
        assert.equal(data.reception_email_verified,null);
        await go('/__qa/login/reception'); await go('/portal'); await textHas('B3003QA');
    });
    await test('Horizontal logo upload navbar and mobile sidebar', async () => {
        await go('/__qa/login/owner'); await go('/workshop-settings');
        const imagePath = join(out, 'horizontal.png');
        const fixture = spawnSync('php', ['-r', '$im=imagecreatetruecolor(1600,560); imagefill($im,0,0,imagecolorallocate($im,250,250,250)); imagestring($im,5,100,260,"QA HORIZONTAL LOGO",imagecolorallocate($im,20,20,20)); imagepng($im,$argv[1]);', imagePath], { encoding: 'utf8' });
        assert.equal(fixture.status, 0, fixture.stderr);
        await (await page.$('#workshop-horizontal-logo')).uploadFile(imagePath); await idle();
        await page.waitForSelector('img[alt="Pratinjau logo horizontal baru"]');
        await button('Simpan identitas'); await textHas('Identitas bengkel disimpan.');
        for (const route of ['/', '/dashboard', '/booking']) {
            if (route === '/booking') await go('/__qa/login/customer');
            await go(route);
            const logo = await page.$eval('[data-workshop-brand] img', img => ({ url: img.src, alt: img.alt, text: img.parentElement.textContent.trim() }));
            assert.equal(logo.alt, 'AJM Bengkel'); assert.equal(logo.text, '');
            const response = await fetch(logo.url, { headers: { 'X-QA-Token': token } }); assert.equal(response.status, 200); assert.equal(response.headers.get('content-type'), 'image/png');
            await page.waitForFunction(() => [...document.querySelectorAll('[data-workshop-brand] img')].every(img => img.complete && img.naturalWidth === 1600));
        }
        await page.setViewport({ width: 390, height: 844 }); await go('/booking');
        assert((await page.evaluate(() => document.documentElement.scrollWidth)) <= 391);
        await page.screenshot({ path: join(out, 'horizontal-mobile-navbar.png'), fullPage: true });
        await page.click('[aria-label="Buka navigasi"]'); await idle();
        await (await page.$('ui-sidebar')).screenshot({ path: join(out, 'horizontal-mobile-sidebar.png') });
        await page.setViewport({ width: 1440, height: 1000 });
    });
    await test('Favicon upload and public admin customer head links', async () => {
        await go('/__qa/login/owner'); await go('/workshop-settings');
        const iconPath = join(out, 'favicon.png');
        const fixture = spawnSync('php', ['-r', '$im=imagecreatetruecolor(512,512); imagefill($im,0,0,imagecolorallocate($im,200,30,30)); imagepng($im,$argv[1]);', iconPath], { encoding: 'utf8' });
        assert.equal(fixture.status, 0, fixture.stderr);
        await (await page.$('#workshop-favicon')).uploadFile(iconPath); await idle();
        await page.waitForSelector('img[alt="Pratinjau favicon baru"]');
        await button('Simpan identitas'); await textHas('Identitas bengkel disimpan.');
        let iconUrl;
        for (const route of ['/', '/dashboard', '/booking']) {
            if (route === '/booking') await go('/__qa/login/customer');
            await go(route);
            const icons = await page.$$eval('link[rel="icon"]', links => links.map(link => ({ url: link.href, type: link.type })));
            assert.equal(icons.length, 1); assert.equal(icons[0].type, 'image/png');
            iconUrl ??= icons[0].url; assert.equal(icons[0].url, iconUrl);
            const response = await fetch(iconUrl, { headers: { 'X-QA-Token': token } });
            assert.equal(response.status, 200); assert.equal(response.headers.get('content-type'), 'image/png');
        }
    });
    await test('No browser JavaScript or console errors'   , async () => { assert.equal(errors.length, 0, JSON.stringify(errors)); });
    writeFileSync(join(out, 'final-state.json'), JSON.stringify(await state(), null, 2));
} finally {
    if (browser) await browser.close();
    server.kill('SIGTERM');
    await new Promise(resolve => { if (server.exitCode !== null) resolve(); else server.once('exit', resolve); });
    writeFileSync(join(out, 'server.log'), serverLog.replaceAll(token, '[REDACTED]'));
    writeFileSync(join(out, 'results.json'), JSON.stringify({ results, errors, warnings, network }, null, 2));
    const failed = results.filter(r => !r.pass);
    writeFileSync(join(out, 'report.md'), `# Real-browser QA\n\n${results.length - failed.length}/${results.length} checks passed. Chromium real browser; isolated SQLite.\n\n${results.map(r => `- ${r.pass ? 'PASS' : 'FAIL'} ${r.name}${r.error ? ': ' + r.error : ''}`).join('\n')}\n\nConsole errors: ${errors.length}. HTTP >=400 responses (includes intentional authorization checks): ${network.length}.\n\nScope ceiling: owner route smoke; customer workflows; walk-in/job/part/photo/receipt/payment/direct-sale UI. Mechanic-only permissions, all CRUD edit/archive, booking admin conversion, settings mutations, financial reversal not exhaustively exercised.\n`);
    console.log(JSON.stringify({ artifact_directory: out, passed: results.length - failed.length, checks: results.length, failures: failed, console_errors: errors.length }));
    if (failed.length) process.exitCode = 1;
}
