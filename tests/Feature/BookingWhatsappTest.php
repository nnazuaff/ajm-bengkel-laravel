<?php

use App\Livewire\BookingRequest;
use App\Livewire\BookingReview;
use App\Livewire\GuestBooking;
use App\Models\Booking;
use App\Models\User;
use App\Models\WorkshopSetting;
use App\Support\BookingWhatsapp;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

function whatsappBookingInput(): array
{
    return ['name' => 'Pemesan & teman', 'phone' => '081234567890', 'email' => '',
        'license_plate' => 'D 1234 ABC', 'brand' => 'Honda', 'model' => 'Vario', 'year' => '',
        'current_mileage' => 1000, 'booking_date' => now()->addDay()->toDateString(),
        'arrival_time' => '10:00', 'service_type' => 'Servis rutin', 'complaint' => 'Rem bunyi & berat', 'notes' => ''];
}

it('returns a confirmation for the persisted booking for guests and customers', function (bool $authenticated) {
    RateLimiter::clear('guest-booking:127.0.0.1');
    WorkshopSetting::current()->update(['phone' => '0812 9999 8888']);
    if ($authenticated) {
        Livewire::actingAs(User::factory()->create(['role' => 'customer']));
    }
    $component = Livewire::test($authenticated ? BookingRequest::class : GuestBooking::class);
    if ($authenticated) {
        $component->call('openForm');
    }
    $component->set('form', whatsappBookingInput())->call('submit')->assertHasNoErrors();
    $booking = Booking::sole();
    $url = BookingWhatsapp::url($booking);
    expect($url)->toStartWith('https://wa.me/6281299998888?text=');
    parse_str(parse_url($url, PHP_URL_QUERY), $query);
    expect($query['text'])->toContain($booking->booking_number, $booking->name, $booking->phone,
        'D1234ABC', 'Honda Vario', '10:00', 'Rem bunyi & berat', 'tolong dicek');
    $component->assertReturned(['url' => $url, 'number' => $booking->booking_number]);
    expect($booking->status->value)->toBe('pending');
    if (! $authenticated) {
        $component->assertDontSee('Isi kontak kamu, data motor')
            ->assertDontSee('Booking tanpa akun')
            ->assertSee('Menunggu konfirmasi bengkel');
        expect(substr_count($component->html(), 'Booking berhasil diajukan'))->toBe(1);
    }
})->with([false, true]);

it('does not open whatsapp or save a booking on invalid submission', function () {
    Livewire::test(GuestBooking::class)->call('submit')->assertHasErrors()->assertReturned(null);
    expect(Booking::count())->toBe(0);
});

it('disables whatsapp when the configured destination is missing or unsafe', function (string $phone) {
    WorkshopSetting::current()->update(['phone' => $phone]);
    expect(BookingWhatsapp::url(Booking::factory()->create()))->toBeNull();
})->with(['', 'javascript:alert(1)', '123', 'https://evil.test']);

it('labels an anonymous booking sender as Guest rather than workshop staff', function () {
    $booking = Booking::factory()->create(['submitted_by' => null]);
    Livewire::actingAs(User::factory()->create(['role' => 'admin']))->test(BookingReview::class)
        ->call('openBooking', $booking->id)->assertSee('Guest')->assertDontSee('Petugas bengkel');
});
