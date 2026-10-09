<?php

use App\Enums\Role;
use App\Livewire\CustomerPortal;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\ServiceDocumentation;
use App\Models\ServiceItem;
use App\Models\ServiceJob;
use App\Models\ServiceOrder;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

function portalCustomer(User $user): Customer
{
    $customer = Customer::factory()->create();
    $customer->forceFill(['user_id' => $user->id])->save();

    return $customer;
}

it('shows only vehicles linked through the trusted customer account', function () {
    $user = User::factory()->create();
    $customer = portalCustomer($user);
    $vehicle = Vehicle::factory()->create(['customer_id' => $customer->id, 'license_plate' => 'B1111OWN']);
    $other = Vehicle::factory()->create(['license_plate' => 'B2222OTHER']);
    ServiceOrder::factory()->create(['customer_id' => $customer->id, 'vehicle_id' => $vehicle->id, 'complaint' => 'Keluhan milik saya']);

    Livewire::actingAs($user)->test(CustomerPortal::class)
        ->assertSee($vehicle->license_plate)
        ->assertDontSee($other->license_plate)
        ->assertSee('Portal pelanggan');
});

it('shows archived owned vehicle service evidence without draft receipts or internal notes', function () {
    $user = User::factory()->create();
    $customer = portalCustomer($user);
    $vehicle = Vehicle::factory()->create(['customer_id' => $customer->id]);
    $order = ServiceOrder::factory()->create(['customer_id' => $customer->id, 'vehicle_id' => $vehicle->id,
        'complaint' => 'Suara rem saya', 'diagnosis' => 'Kampas tipis', 'notes' => 'Catatan internal rahasia', 'status' => 'delivered']);
    ServiceJob::factory()->create(['service_order_id' => $order->id, 'name' => 'Ganti kampas rem']);
    Receipt::factory()->create(['service_order_id' => $order->id, 'customer_id' => $customer->id, 'status' => 'draft', 'receipt_number' => 'BON-DRAFT-SECRET']);
    $vehicle->delete();

    Livewire::actingAs($user)->test(CustomerPortal::class)
        ->call('selectVehicle', $vehicle->id)
        ->call('selectOrder', $order->id)
        ->assertSee('Diarsipkan')->assertSee('Suara rem saya')->assertSee('Kampas tipis')
        ->assertSee('Ganti kampas rem')->assertSee('Bon belum diterbitkan')
        ->assertDontSee('BON-DRAFT-SECRET')->assertDontSee('Catatan internal rahasia');
});

it('rejects sequential vehicle and order selection belonging to another account', function () {
    $user = User::factory()->create();
    portalCustomer($user);
    $other = ServiceOrder::factory()->create();
    Livewire::actingAs($user)->test(CustomerPortal::class)->call('selectVehicle', $other->vehicle_id)->assertNotFound();
    Livewire::actingAs($user)->test(CustomerPortal::class)->call('selectOrder', $other->id)->assertNotFound();
});

it('does not link a matching email or expose archived customer history', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['email' => $user->email]);
    $vehicle = Vehicle::factory()->create(['customer_id' => $customer->id]);
    Livewire::actingAs($user)->test(CustomerPortal::class)->assertSee('Histori lama menunggu verifikasi identitas')->assertDontSee($vehicle->license_plate);
    $customer->forceFill(['user_id' => $user->id])->save();
    $customer->delete();
    Livewire::actingAs($user)->test(CustomerPortal::class)->assertSee('Histori lama menunggu verifikasi identitas')->assertDontSee($vehicle->license_plate);
});

it('locks browser-selected identifiers', function ($property) {
    $user = User::factory()->create();
    portalCustomer($user);
    expect(fn () => Livewire::actingAs($user)->test(CustomerPortal::class)->set($property, 123))
        ->toThrow(CannotUpdateLockedPropertyException::class);
})->with(['selectedVehicle', 'selectedOrder']);

it('rechecks ownership after trusted account is unlinked between requests', function () {
    $user = User::factory()->create();
    $customer = portalCustomer($user);
    $vehicle = Vehicle::factory()->create(['customer_id' => $customer->id]);
    $component = Livewire::actingAs($user)->test(CustomerPortal::class)->call('selectVehicle', $vehicle->id);
    $customer->forceFill(['user_id' => null])->save();
    $component->call('$refresh')->assertNotFound();
});

it('blocks noncustomer unverified archived and stale-role component access', function () {
    foreach ([User::factory()->create(['role' => Role::Admin]), User::factory()->unverified()->create()] as $user) {
        Livewire::actingAs($user)->test(CustomerPortal::class)->assertForbidden();
    }
    $user = User::factory()->create();
    $component = Livewire::actingAs($user)->test(CustomerPortal::class);
    User::whereKey($user->id)->update(['role' => Role::Mechanic]);
    $component->call('$refresh')->assertForbidden();
    $user = User::factory()->create();
    $user->delete();
    Livewire::actingAs($user)->test(CustomerPortal::class)->assertForbidden();
});

it('serves owned final receipt snapshots and actual PNG privately through customer links', function ($status) {
    $user = User::factory()->create();
    $customer = portalCustomer($user);
    $receipt = Receipt::factory()->create(['customer_id' => $customer->id, 'status' => $status,
        'workshop_snapshot' => ['name' => 'Bengkel saat transaksi'], 'customer_snapshot' => ['name' => 'Nama saat transaksi']]);
    $this->actingAs($user)->get(route('customer.receipts.show', $receipt))->assertOk()
        ->assertSee('Bengkel saat transaksi')->assertSee('Nama saat transaksi')
        ->assertSee(route('customer.receipts.image', $receipt))->assertDontSee(route('receipts.image', $receipt))
        ->assertSee(route('portal'))->assertHeader('X-Content-Type-Options', 'nosniff');
    $response = $this->get(route('customer.receipts.image', $receipt))->assertOk()
        ->assertHeader('Content-Type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff');
    expect($response->headers->get('Cache-Control'))->toContain('private')->toContain('no-store');
    expect(substr($response->getContent(), 0, 8))->toBe("\x89PNG\r\n\x1a\n");
    expect(getimagesizefromstring($response->getContent())[0])->toBe(800);
})->with(['final', 'paid', 'voided']);

it('denies drafts other customers general receipts and archived customers on both receipt endpoints', function ($kind) {
    $user = User::factory()->create();
    $customer = portalCustomer($user);
    $receipt = Receipt::factory()->create(['customer_id' => $kind === 'other' ? Customer::factory()->create()->id : ($kind === 'general' ? null : $customer->id), 'status' => $kind === 'draft' ? 'draft' : 'final']);
    if ($kind === 'archived') {
        $customer->delete();
    }
    foreach (['customer.receipts.show', 'customer.receipts.image'] as $name) {
        $this->actingAs($user)->get(route($name, $receipt))->assertNotFound();
    }
})->with(['draft', 'other', 'general', 'archived']);

it('requires verified customer authentication on every portal read route', function ($route) {
    $user = User::factory()->create();
    $customer = portalCustomer($user);
    $receipt = Receipt::factory()->create(['customer_id' => $customer->id, 'status' => 'final']);
    $order = ServiceOrder::factory()->create(['customer_id' => $customer->id]);
    $photo = ServiceDocumentation::factory()->create(['service_order_id' => $order->id]);
    $url = $route === 'portal' ? route('portal') : route($route, $route === 'customer.documentation.show' ? $photo : $receipt);
    $this->get($url)->assertRedirect(route('login'));
    $this->actingAs(User::factory()->unverified()->create())->get($url)->assertRedirect(route('verification.notice'));
    foreach ([Role::Admin, Role::Owner, Role::Mechanic] as $role) {
        $this->actingAs(User::factory()->create(['role' => $role]))->get($url)->assertForbidden();
    }
})->with(['portal', 'customer.receipts.show', 'customer.receipts.image', 'customer.documentation.show']);

it('streams only owned private documentation including terminal service evidence', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    $customer = portalCustomer($user);
    $vehicle = Vehicle::factory()->create(['customer_id' => $customer->id]);
    $order = ServiceOrder::factory()->create(['customer_id' => $customer->id, 'vehicle_id' => $vehicle->id, 'status' => 'delivered']);
    $photo = ServiceDocumentation::factory()->create(['service_order_id' => $order->id, 'path' => 'service-documentation/'.$order->id.'/'.str_repeat('a', 32).'.png']);
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aY9sAAAAASUVORK5CYII=');
    Storage::disk('local')->put($photo->path, $png);
    $response = $this->actingAs($user)->get(route('customer.documentation.show', $photo))->assertOk()
        ->assertHeader('Content-Type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff');
    expect($response->headers->get('Cache-Control'))->toContain('private')->toContain('no-store');
    expect($response->headers->get('Content-Disposition'))->toStartWith('inline');
    expect($response->streamedContent())->toBe($png);
    $this->get(route('documentation.show', $photo))->assertForbidden();
    $vehicle->delete();
    $this->get(route('customer.documentation.show', $photo))->assertOk();
});

it('denies documentation tampering missing unsafe deleted and foreign files', function ($kind) {
    Storage::fake('local');
    $user = User::factory()->create();
    $customer = portalCustomer($user);
    $vehicle = Vehicle::factory()->create(['customer_id' => $customer->id]);
    $order = ServiceOrder::factory()->create(['customer_id' => $customer->id, 'vehicle_id' => $vehicle->id]);
    $photo = ServiceDocumentation::factory()->create(['service_order_id' => $kind === 'other' ? ServiceOrder::factory()->create()->id : $order->id]);
    if ($kind === 'path') {
        $photo->update(['path' => '../secrets.png']);
    }
    if ($kind === 'disk') {
        $photo->update(['disk' => 'public']);
    }
    if ($kind === 'mime') {
        Storage::disk('local')->put($photo->path, '<svg onload="alert(1)"></svg>');
    }
    if ($kind === 'deleted') {
        $photo->delete();
    }
    if ($kind === 'archived') {
        $customer->delete();
    }
    $this->actingAs($user)->get(route('customer.documentation.show', $photo))->assertNotFound();
})->with(['other', 'path', 'disk', 'mime', 'missing', 'deleted', 'archived']);

it('renders own jobs parts photos payments and direct-sale receipt without mutating domain data', function () {
    $user = User::factory()->create();
    $customer = portalCustomer($user);
    $vehicle = Vehicle::factory()->create(['customer_id' => $customer->id]);
    $order = ServiceOrder::factory()->create(['customer_id' => $customer->id, 'vehicle_id' => $vehicle->id]);
    ServiceItem::factory()->create(['service_order_id' => $order->id, 'description' => 'Busi snapshot lama', 'unit_price' => '15000.00']);
    ServiceDocumentation::factory()->create(['service_order_id' => $order->id, 'caption' => 'Bukti milik saya']);
    $receipt = Receipt::factory()->create(['customer_id' => $customer->id, 'service_order_id' => $order->id, 'status' => 'paid', 'grand_total' => '15000.00', 'payment_status' => 'paid']);
    Payment::factory()->create(['receipt_id' => $receipt->id, 'amount' => '15000.00', 'method' => 'transfer']);
    $direct = Receipt::factory()->create(['customer_id' => $customer->id, 'status' => 'voided', 'void_reason' => 'Koreksi transaksi']);
    $foreign = Receipt::factory()->create(['customer_id' => Customer::factory()->create()->id, 'status' => 'paid']);
    $before = [$order->fresh()->toArray(), $receipt->fresh()->toArray(), StockMovement::count()];
    Livewire::actingAs($user)->test(CustomerPortal::class)->call('selectOrder', $order->id)
        ->assertSee('Busi snapshot lama')->assertSee('15000.00')->assertSee('Bukti milik saya')
        ->assertSee('Transfer')->assertSee('Lunas')->assertSee($direct->receipt_number)->assertSee('Dibatalkan')
        ->assertDontSee($foreign->receipt_number)->assertDontSee('service-documentation/');
    expect([$order->fresh()->toArray(), $receipt->fresh()->toArray(), StockMovement::count()])->toBe($before);
});

it('refuses an order whose vehicle belongs to another customer despite matching order customer', function () {
    $user = User::factory()->create();
    $customer = portalCustomer($user);
    $order = ServiceOrder::factory()->create(['customer_id' => $customer->id]);
    Livewire::actingAs($user)->test(CustomerPortal::class)->assertDontSee($order->service_number)
        ->call('selectOrder', $order->id)->assertNotFound();
});

it('denies stale verified roles at receipt and photo controller boundaries', function () {
    $user = User::factory()->create();
    $customer = portalCustomer($user);
    $receipt = Receipt::factory()->create(['customer_id' => $customer->id, 'status' => 'final']);
    $order = ServiceOrder::factory()->create(['customer_id' => $customer->id]);
    $photo = ServiceDocumentation::factory()->create(['service_order_id' => $order->id]);
    $this->actingAs($user);
    User::whereKey($user->id)->update(['role' => Role::Admin]);
    $this->get(route('customer.receipts.show', $receipt))->assertForbidden();
    $this->get(route('customer.receipts.image', $receipt))->assertForbidden();
    $this->get(route('customer.documentation.show', $photo))->assertForbidden();
});

it('does not treat a submitted booking matching customer phone as proof of ownership', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['email' => $user->email, 'phone' => '6281234567890']);
    $vehicle = Vehicle::factory()->create(['customer_id' => $customer->id]);
    Booking::factory()->create(['submitted_by' => $user->id, 'phone' => $customer->phone, 'license_plate' => $vehicle->license_plate]);
    Livewire::actingAs($user)->test(CustomerPortal::class)->assertSee('Histori lama menunggu verifikasi identitas')
        ->assertSee(route('booking.mine'))->assertDontSee($vehicle->license_plate);
    expect($customer->fresh()->user_id)->toBeNull();
});

it('excludes deleted documentation from the portal gallery', function () {
    $user = User::factory()->create();
    $customer = portalCustomer($user);
    $vehicle = Vehicle::factory()->create(['customer_id' => $customer->id]);
    $order = ServiceOrder::factory()->create(['customer_id' => $customer->id, 'vehicle_id' => $vehicle->id]);
    $photo = ServiceDocumentation::factory()->create(['service_order_id' => $order->id, 'caption' => 'DELETED-PHOTO-SECRET']);
    $photo->delete();
    Livewire::actingAs($user)->test(CustomerPortal::class)->call('selectOrder', $order->id)
        ->assertDontSee('DELETED-PHOTO-SECRET')->assertDontSee(route('customer.documentation.show', $photo));
});

it('shows active payment sums and truthful reversal status on the customer receipt', function () {
    $user = User::factory()->create();
    $customer = portalCustomer($user);
    $receipt = Receipt::factory()->create(['customer_id' => $customer->id, 'status' => 'final', 'grand_total' => '200.00', 'payment_status' => 'partial']);
    Payment::factory()->create(['receipt_id' => $receipt->id, 'amount' => '50.00']);
    Payment::factory()->create(['receipt_id' => $receipt->id, 'amount' => '80.00', 'reversed_at' => now(), 'reversal_reason' => 'Koreksi']);
    $this->actingAs($user)->get(route('customer.receipts.show', $receipt))->assertOk()
        ->assertSee('Sebagian')->assertSee('Dibalik dalam pembukuan')->assertSee('Rp 50.00')->assertSee('Rp 150.00');
    $this->get(route('receipts.show', $receipt))->assertForbidden();
    $this->get(route('receipts.image', $receipt))->assertForbidden();
});

it('rechecks changed ownership of a selected vehicle and order on render', function ($kind) {
    $user = User::factory()->create();
    $customer = portalCustomer($user);
    $vehicle = Vehicle::factory()->create(['customer_id' => $customer->id]);
    $order = ServiceOrder::factory()->create(['customer_id' => $customer->id, 'vehicle_id' => $vehicle->id]);
    $component = Livewire::actingAs($user)->test(CustomerPortal::class)->call('selectOrder', $order->id);
    $other = Customer::factory()->create();
    if ($kind === 'vehicle') {
        $vehicle->update(['customer_id' => $other->id]);
    } else {
        $order->update(['customer_id' => $other->id]);
    }
    $component->call('$refresh')->assertNotFound();
})->with(['vehicle', 'order']);
