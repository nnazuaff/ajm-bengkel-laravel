<?php

use App\Actions\ConvertBooking;
use App\Livewire\BookingRequest;
use App\Livewire\BookingReview;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\ServiceOrder;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

it('fills the booking snapshot only from a motor belonging to the linked account', function () {
    $account = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $account->id]);
    $vehicle = Vehicle::factory()->create(['customer_id' => $customer->id, 'license_plate' => 'B1234OWN', 'latest_mileage' => 1234]);
    $other = Vehicle::factory()->create();
    $component = Livewire::actingAs($account)->test(BookingRequest::class)->call('openForm')
        ->assertSee('Pilih motor saya')->call('useVehicle', $vehicle->id)
        ->assertSet('form.license_plate', 'B1234OWN')->assertSet('form.current_mileage', 1234)->assertSet('form.phone', $customer->phone);
    $component->call('useVehicle', $other->id)->assertNotFound();
});

it('receives a booking and links its account only after explicit ownership confirmation', function () {
    $actor = User::factory()->create(['role' => 'admin']);
    $account = User::factory()->create();
    $booking = Booking::factory()->create(['status' => 'arrived', 'submitted_by' => $account->id]);
    expect(fn () => app(ConvertBooking::class)->convert($actor, $booking, null, ['link_account' => true]))->toThrow(ValidationException::class);
    expect(ServiceOrder::count())->toBe(0)->and(Customer::count())->toBe(0);
    $order = app(ConvertBooking::class)->convert($actor, $booking, null, ['link_account' => true, 'ownership_verified' => true]);
    expect($order->customer->user_id)->toBe($account->id);
    expect(AuditLog::where('action', 'customer.account_linked')->count())->toBe(1);
    app(ConvertBooking::class)->convert($actor, $booking, null, ['link_account' => true, 'ownership_verified' => true]);
    expect(AuditLog::where('action', 'customer.account_linked')->count())->toBe(1);
});

it('requires explicit restoration of archived contact or vehicle and keeps history intact', function (bool $hasVehicle) {
    $actor = User::factory()->create(['role' => 'owner']);
    $booking = Booking::factory()->create(['status' => 'arrived']);
    $customer = Customer::factory()->create(['phone' => $booking->phone]);
    $vehicle = $hasVehicle ? Vehicle::factory()->create(['customer_id' => $customer->id, 'license_plate' => $booking->license_plate, 'latest_mileage' => 0]) : null;
    $vehicle?->delete();
    $customer->delete();
    expect(fn () => app(ConvertBooking::class)->convert($actor, $booking, null, ['restore_archived' => true]))->toThrow(ValidationException::class);
    $order = app(ConvertBooking::class)->convert($actor, $booking, null, ['restore_archived' => true, 'ownership_verified' => true]);
    expect($order->customer_id)->toBe($customer->id)->and($customer->fresh()->trashed())->toBeFalse();
    if ($vehicle) {
        expect($order->vehicle_id)->toBe($vehicle->id)->and($vehicle->fresh()->trashed())->toBeFalse();
    }
    expect(AuditLog::where('action', 'customer.restored')->count())->toBe(1);
})->with([false, true]);

it('does not replace another linked account or give one account two customer records', function (string $conflict) {
    $actor = User::factory()->create(['role' => 'admin']);
    $account = User::factory()->create();
    $booking = Booking::factory()->create(['status' => 'arrived', 'submitted_by' => $account->id]);
    if ($conflict === 'target') {
        Customer::factory()->create(['phone' => $booking->phone, 'user_id' => User::factory()->create()->id]);
    } else {
        Customer::factory()->create(['user_id' => $account->id]);
    }
    expect(fn () => app(ConvertBooking::class)->convert($actor, $booking, null, ['link_account' => true, 'ownership_verified' => true]))->toThrow(ValidationException::class);
    expect(ServiceOrder::count())->toBe(0)->and(Customer::count())->toBe(1);
})->with(['target', 'account']);

it('rolls restoration and intake back when account-link audit fails', function () {
    $actor = User::factory()->create(['role' => 'owner']);
    $booking = Booking::factory()->create(['status' => 'arrived', 'submitted_by' => User::factory()->create()->id]);
    $customer = Customer::factory()->create(['phone' => $booking->phone]);
    $customer->delete();
    AuditLog::creating(function ($audit) {
        if ($audit->action === 'customer.account_linked') {
            throw new RuntimeException('Audit failed');
        }
    });
    try {
        expect(fn () => app(ConvertBooking::class)->convert($actor, $booking, null, ['link_account' => true, 'restore_archived' => true, 'ownership_verified' => true]))->toThrow(RuntimeException::class);
    } finally {
        AuditLog::flushEventListeners();
    }
    expect($customer->fresh()->trashed())->toBeTrue()->and($customer->fresh()->user_id)->toBeNull()->and(ServiceOrder::count())->toBe(0)->and(Vehicle::count())->toBe(0);
});

it('shows restoration and linking controls and receives through the admin form', function () {
    $account = User::factory()->create();
    $booking = Booking::factory()->create(['status' => 'arrived', 'submitted_by' => $account->id]);
    $customer = Customer::factory()->create(['phone' => $booking->phone]);
    $customer->delete();
    Livewire::actingAs(User::factory()->create(['role' => 'admin']))->test(BookingReview::class)
        ->call('openBooking', $booking->id)->assertSee('Pulihkan data arsip')->assertSet('detail.link_account', true)
        ->set('detail.restore_archived', true)->call('convert')->assertHasErrors('detail.ownership_verified')
        ->set('detail.ownership_verified', true)->call('convert')->assertHasNoErrors();
    expect($customer->fresh()->user_id)->toBe($account->id)->and($customer->fresh()->trashed())->toBeFalse();
});
