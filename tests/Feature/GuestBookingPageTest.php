<?php

use App\Livewire\GuestBooking;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Vehicle;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

beforeEach(function () {
    RateLimiter::clear('guest-booking:127.0.0.1');
});

it('offers public booking without redirecting guests to login', function () {
    $this->get('/booking/guest')->assertOk()->assertSee('Booking tanpa akun')->assertSee('Telepon / WhatsApp');
});

it('submits a guest booking without exposing existing customer records', function () {
    $component = Livewire::test(GuestBooking::class)->set('form', [
        'name' => 'Tamu bengkel', 'phone' => '081234567890', 'email' => null,
        'license_plate' => 'd 1234 abc', 'brand' => 'Honda', 'model' => 'Vario', 'year' => null,
        'current_mileage' => 500, 'booking_date' => now()->addDay()->toDateString(),
        'arrival_time' => '09:00', 'service_type' => 'Servis rutin', 'complaint' => 'Rem bunyi', 'notes' => null,
    ])->call('submit')->assertHasNoErrors()->assertSet('submitted', true)->assertSee('Booking berhasil diajukan');
    $booking = Booking::sole();
    expect($booking->submitted_by)->toBeNull()->and($booking->customer_id)->toBe(Customer::sole()->id)
        ->and($booking->vehicle_id)->toBe(Vehicle::sole()->id);
    expect($component->html())->not->toContain('Rem bunyi');
});

it('retains invalid guest drafts with inline errors', function () {
    Livewire::test(GuestBooking::class)->call('submit')->assertHasErrors('form.phone')
        ->assertSet('submitted', false);
    expect(Booking::count())->toBe(0);
});
