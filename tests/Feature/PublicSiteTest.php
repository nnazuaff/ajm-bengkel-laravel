<?php

use App\Models\Customer;
use App\Models\Receipt;
use App\Models\Vehicle;
use App\Models\WorkshopSetting;
use Illuminate\Support\Facades\Storage;

it('serves a responsive public home using only configured workshop identity', function () {
    WorkshopSetting::current()->update(['name' => 'Bengkel Uji', 'phone' => '628112345678', 'address' => 'Alamat terkonfigurasi']);

    $this->get('/')->assertOk()->assertSee('Bengkel Uji')->assertSee('628112345678')
        ->assertSee('Alamat terkonfigurasi')->assertSee('Tanpa akun')
        ->assertSee(route('booking.guest'))->assertSee(route('login'))->assertSee(route('register'))
        ->assertSee('name="viewport"', false)->assertSee('md:grid-cols-2', false)
        ->assertDontSee('08:00')->assertDontSee('Rp 50.000');
});

it('omits unset contacts and all private customer transaction data on the public page', function () {
    WorkshopSetting::current()->update(['phone' => '', 'address' => '']);
    $customer = Customer::factory()->create(['name' => 'PRIVATE-CUSTOMER-SECRET']);
    Vehicle::factory()->create(['customer_id' => $customer->id, 'license_plate' => 'B9999SECRET']);
    Receipt::factory()->create(['customer_id' => $customer->id, 'receipt_number' => 'BON-PRIVATE-SECRET', 'status' => 'final']);
    $this->get('/')->assertOk()->assertDontSee('PRIVATE-CUSTOMER-SECRET')->assertDontSee('B9999SECRET')
        ->assertDontSee('BON-PRIVATE-SECRET')->assertDontSee('Hubungi bengkel')->assertDontSee('Telepon / WhatsApp');
    $this->get(route('booking.mine'))->assertRedirect(route('login'));
});

it('renders only safe configured public logo images', function () {
    Storage::fake('public');
    $disk = Storage::disk('public');
    $disk->put('workshop/logo.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aY9sAAAAASUVORK5CYII='));
    WorkshopSetting::current()->update(['logo_path' => 'workshop/logo.png']);
    $this->get('/')->assertOk()->assertSee($disk->url('workshop/logo.png'));
    WorkshopSetting::current()->update(['logo_path' => 'https://untrusted.test/logo.png']);
    $this->get('/')->assertOk()->assertDontSee('untrusted.test');
    WorkshopSetting::current()->update(['logo_path' => '../secret.png']);
    $this->get('/')->assertOk()->assertDontSee('../secret.png');
});
