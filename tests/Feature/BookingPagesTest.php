<?php

use App\Enums\BookingStatus;
use App\Enums\Role;
use App\Livewire\BookingRequest;
use App\Livewire\BookingReview;
use App\Livewire\Bookings;
use App\Livewire\CustomerBooking;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\ServiceOrder;
use App\Models\User;
use App\Models\Vehicle;
use Livewire\Livewire;

it('restricts the customer page to verified active customer actors on initial and subsequent requests', function () {
    foreach ([Role::Owner, Role::Admin, Role::Mechanic] as $role) {
        Livewire::actingAs(User::factory()->create(['role' => $role]))->test(CustomerBooking::class)->assertForbidden();
    }
    Livewire::actingAs(User::factory()->unverified()->create())->test(CustomerBooking::class)->assertForbidden();
    $user = User::factory()->create();
    $page = Livewire::actingAs($user)->test(CustomerBooking::class)->assertOk();
    User::whereKey($user->id)->update(['role' => Role::Mechanic]);
    $page->call('createBooking')->assertForbidden();
});

it('shows only owned bookings and cancels only the submitters own nonarrived request', function () {
    $user = User::factory()->create();
    $own = Booking::factory()->create(['submitted_by' => $user->id, 'booking_number' => 'BKG-own', 'admin_notes' => 'INTERNAL SECRET']);
    $other = Booking::factory()->create(['booking_number' => 'BKG-other', 'email' => $user->email, 'phone' => $own->phone]);
    $page = Livewire::actingAs($user)->test(CustomerBooking::class)
        ->assertSee('BKG-own')->assertDontSee('BKG-other')->assertDontSee('INTERNAL SECRET');
    $page->call('cancel', $own->id)->assertHasNoErrors();
    expect($own->fresh()->status)->toBe(BookingStatus::Cancelled);
    Livewire::actingAs($user)->test(CustomerBooking::class)->call('cancel', $other->id)->assertForbidden();
    $own->update(['status' => BookingStatus::Arrived]);
    Livewire::actingAs($user)->test(CustomerBooking::class)->call('cancel', $own->id)->assertForbidden();
});

// Revised flow resolves customer and vehicle during submission, not reception.
it('submits customer snapshots with field validation and creates master relations', function () {
    $user = User::factory()->create();
    $page = Livewire::actingAs($user)->test(BookingRequest::class)->call('openForm');
    $page->set('form', ['name' => ['malformed']])->call('submit')->assertHasErrors(['form.name', 'form.phone']);
    $page->set('form', [
        'name' => 'Pemesan', 'phone' => '081234567890', 'email' => '',
        'license_plate' => 'B 1234 ABC', 'brand' => 'Honda', 'model' => 'Vario', 'year' => '',
        'current_mileage' => 1000, 'booking_date' => now()->addDay()->toDateString(),
        'arrival_time' => '10:00', 'service_type' => 'Servis', 'complaint' => 'Bunyi rem', 'notes' => '',
        'submitted_by' => 999, 'status' => 'arrived',
    ])->call('submit')->assertHasNoErrors()->assertDispatched('booking-created')->assertSet('showForm', false);
    expect(Booking::sole()->submitted_by)->toBe($user->id)->and(Booking::sole()->status)->toBe(BookingStatus::Pending)
        ->and(Customer::count())->toBe(1)->and(Vehicle::count())->toBe(1)->and(ServiceOrder::count())->toBe(0)
        ->and(Booking::sole()->customer->user_id)->toBe($user->id);
});

it('enforces authenticated routes and customer verification', function () {
    $this->get(route('booking.mine'))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->unverified()->create())->get(route('booking.mine'))->assertRedirect(route('verification.notice'));
    $this->actingAs(User::factory()->create(['role' => Role::Mechanic]))->get(route('booking.mine'))->assertForbidden();
    $this->actingAs(User::factory()->create())->get(route('booking.mine'))->assertOk();
});

it('limits the admin queue and repeated mutations to owner and admin', function () {
    foreach ([Role::Customer, Role::Mechanic] as $role) {
        $user = User::factory()->create(['role' => $role]);
        Livewire::actingAs($user)->test(Bookings::class)->assertForbidden();
        $this->actingAs($user)->get(route('bookings.index'))->assertForbidden();
    }
    $admin = User::factory()->create(['role' => Role::Admin]);
    $page = Livewire::actingAs($admin)->test(Bookings::class)->assertOk();
    User::whereKey($admin->id)->update(['role' => Role::Mechanic]);
    $page->call('openBooking', 999)->assertForbidden();
});

it('reviews confirms reschedules arrives and converts a booking from the admin queue', function () {
    $admin = User::factory()->create(['role' => Role::Owner]);
    $booking = Booking::factory()->create();
    $page = Livewire::actingAs($admin)->test(BookingReview::class)
        ->call('openBooking', $booking->id)->assertSet('selectedBookingId', $booking->id)
        ->set('detail.status', 'confirmed')->call('saveBooking')->assertHasNoErrors()
        ->assertSet('showForm', true)->assertDispatched('booking-updated');
    expect($booking->fresh()->status)->toBe(BookingStatus::Confirmed);
    $page->set('detail.status', 'rescheduled')->set('detail.booking_date', now()->addDays(3)->toDateString())
        ->set('detail.arrival_time', '11:00')->call('saveBooking')->assertHasNoErrors();
    expect($booking->fresh()->booking_date->toDateString())->toBe(now()->addDays(3)->toDateString());
    $page->set('detail.status', 'arrived')->call('saveBooking')->assertHasNoErrors()
        ->set('detail.ownership_verified', true)->call('convert')->assertHasNoErrors()->assertSet('showForm', false)->assertDispatched('booking-updated');
    expect($booking->fresh()->status)->toBe(BookingStatus::ConvertedToService)
        ->and(ServiceOrder::sole()->booking_id)->toBe($booking->id);
    $page->call('openBooking', $booking->id)->call('convert')->assertHasNoErrors();
    expect(ServiceOrder::count())->toBe(1);
});

it('shows field errors for admin status and reschedule inputs without changing records', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $booking = Booking::factory()->create();
    $page = Livewire::actingAs($admin)->test(BookingReview::class)->call('openBooking', $booking->id);
    $page->set('detail.status', 'rejected')->call('saveBooking')->assertHasErrors('detail.admin_notes');
    $page->set('detail.status', 'rescheduled')->set('detail.arrival_time', '20:00')->call('saveBooking')->assertHasErrors('detail.arrival_time');
    $page->set('detail.status', 'converted_to_service')->call('saveBooking')->assertHasErrors('detail.status');
    expect($booking->fresh()->status)->toBe(BookingStatus::Pending);
});

it('shows stale save errors after another admin closes the booking', function () {
    $booking = Booking::factory()->create();
    $page = Livewire::actingAs(User::factory()->create(['role' => Role::Admin]))->test(BookingReview::class)
        ->call('openBooking', $booking->id);
    $booking->update(['status' => BookingStatus::Rejected, 'admin_notes' => 'Jadwal penuh.']);
    $page->call('saveBooking')->assertHasErrors('detail.status')
        ->assertSee('Perubahan status tidak tersedia. Muat ulang booking.');
});

it('supports escaped admin search status and date filters with an empty state', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $a = Booking::factory()->create(['name' => 'Nama%Unik', 'booking_number' => 'BKG-filter-a']);
    $b = Booking::factory()->create(['name' => 'NamaXUnik', 'booking_number' => 'BKG-filter-b', 'status' => BookingStatus::Confirmed]);
    $page = Livewire::actingAs($admin)->test(Bookings::class)->assertSee('data-workshop-search', false)
        ->set('search', 'Nama%Unik')->assertSee($a->booking_number)->assertDontSee($b->booking_number)
        ->set('search', '')->set('statusFilter', 'confirmed')->assertSee($b->booking_number)->assertDontSee($a->booking_number)
        ->set('dateFilter', now()->addDays(4)->toDateString())->assertSee('Tidak ada booking yang cocok');
});

it('rejects a malformed conversion mechanic field before invoking the typed action', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $booking = Booking::factory()->create(['status' => BookingStatus::Arrived]);
    Livewire::actingAs($admin)->test(BookingReview::class)->call('openBooking', $booking->id)
        ->set('detail.mechanic_id', ['bad'])->call('convert')->assertHasErrors('detail.mechanic_id');
    expect(ServiceOrder::count())->toBe(0);
});
