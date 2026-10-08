<?php

use App\Livewire\BookingRequest;
use App\Livewire\CustomerBooking;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\User;
use App\Models\Vehicle;
use Livewire\Livewire;

it('shows only the booking list until the separate booking modal is opened', function () {
    $user = User::factory()->create();
    $own = Booking::factory()->create(['submitted_by' => $user->id]);
    Livewire::actingAs($user)->test(CustomerBooking::class)
        ->assertSee('Buat booking')->assertSee($own->booking_number)
        ->assertDontSeeHtml('wire:model="form.phone"')
        ->call('createBooking')->assertDispatched('open-booking-request');
    Livewire::test(BookingRequest::class)->assertSet('showForm', false)
        ->call('openForm')->assertSet('showForm', true)->assertSee('Ajukan booking')
        ->assertSet('form.email', $user->email);
});

it('keeps invalid forms open and resets cancelled drafts when reopened', function () {
    Livewire::actingAs(User::factory()->create())->test(BookingRequest::class)
        ->call('openForm')->set('form.phone', 'invalid')->call('submit')
        ->assertHasErrors('form.phone')->assertSet('showForm', true)
        ->call('closeForm')->assertSet('showForm', false)->assertHasNoErrors()
        ->call('openForm')->assertSet('form.phone', '')->assertHasNoErrors();
    expect(Booking::count())->toBe(0);
});

it('closes the modal and refreshes only the customers own booking list after save', function () {
    $user = User::factory()->create();
    $page = Livewire::actingAs($user)->test(CustomerBooking::class)->set('paginators.page', 2);
    Livewire::test(BookingRequest::class)->call('openForm')->set('form', [
        'name' => 'Pemesan', 'phone' => '081234567890', 'email' => '',
        'license_plate' => 'B 1234 ABC', 'brand' => 'Honda', 'model' => 'Vario', 'year' => '',
        'current_mileage' => 1000, 'booking_date' => now()->addDay()->toDateString(),
        'arrival_time' => '10:00', 'service_type' => 'Servis', 'complaint' => 'Bunyi rem', 'notes' => '',
    ])->call('submit')->assertHasNoErrors()->assertSet('showForm', false)->assertDispatched('booking-created');
    $page->dispatch('booking-created')->assertSet('paginators.page', 1)
        ->assertSee(Booking::sole()->booking_number)->assertSee('Booking berhasil diajukan');
    expect(Customer::count())->toBe(0)->and(Vehicle::count())->toBe(0);
});

it('protects the modal from noncustomer roles and stale role changes', function () {
    foreach (['owner', 'admin', 'mechanic'] as $role) {
        Livewire::actingAs(User::factory()->create(['role' => $role]))->test(BookingRequest::class)->assertForbidden();
    }
    $user = User::factory()->create();
    $modal = Livewire::actingAs($user)->test(BookingRequest::class)->call('openForm');
    $user->forceFill(['role' => 'mechanic'])->save();
    $modal->call('submit')->assertForbidden();
});
