<?php

use App\Actions\ConvertBooking;
use App\Actions\CreateBooking;
use App\Enums\BookingStatus;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\ServiceOrder;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

function revisionBookingInput(array $overrides = []): array
{
    return array_replace([
        'name' => 'Nama input', 'phone' => '0812 3456 7890', 'email' => 'guest@example.test',
        'license_plate' => 'b 1234 abc', 'brand' => 'Honda', 'model' => 'Vario',
        'current_mileage' => 100, 'booking_date' => now()->addDay()->toDateString(),
        'arrival_time' => '09:00', 'service_type' => 'Servis', 'complaint' => 'Rem bunyi',
    ], $overrides);
}

it('guest creates master relations with null actor and ignores forged ownership', function () {
    $booking = app(CreateBooking::class)->guest(revisionBookingInput(['submitted_by' => 123, 'customer_id' => 123, 'vehicle_id' => 123, 'status' => 'confirmed']));
    expect($booking->submitted_by)->toBeNull()->and($booking->customer->user_id)->toBeNull()
        ->and($booking->vehicle->customer_id)->toBe($booking->customer_id)
        ->and($booking->status)->toBe(BookingStatus::Pending)
        ->and($booking->vehicle->latest_mileage)->toBe(100);
    $this->assertDatabaseHas('audit_logs', ['action' => 'booking.created', 'actor_id' => null]);
});

it('rolls booking creation back when its audit is silently vetoed', function () {
    AuditLog::creating(fn () => false);
    try {
        expect(fn () => app(CreateBooking::class)->guest(revisionBookingInput()))->toThrow(RuntimeException::class);
    } finally {
        AuditLog::flushEventListeners();
    }
    expect(Booking::count())->toBe(0)->and(Customer::count())->toBe(0)->and(Vehicle::count())->toBe(0)
        ->and(DB::table('document_sequences')->count())->toBe(0);
});

it('guest rejects archived or foreign masters without disclosure restoration or side effects', function (string $case) {
    $customer = Customer::factory()->create(['phone' => $case === 'foreign' ? '6281777777777' : '6281234567890', 'name' => 'PRIVATE OWNER NAME']);
    $vehicle = Vehicle::factory()->create(['customer_id' => $customer->id, 'license_plate' => 'B1234ABC', 'latest_mileage' => 0]);
    if ($case === 'customer') {
        $customer->delete();
    }
    if ($case === 'vehicle') {
        $vehicle->delete();
    }
    try {
        app(CreateBooking::class)->guest(revisionBookingInput());
        $this->fail('Invalid master accepted');
    } catch (ValidationException $exception) {
        expect(json_encode($exception->errors()))->not->toContain('PRIVATE OWNER NAME')->not->toContain('6281777777777');
    }
    expect(Booking::count())->toBe(0)->and(Customer::withTrashed()->count())->toBe(1)->and(Vehicle::withTrashed()->count())->toBe(1)
        ->and($customer->fresh()->trashed())->toBe($case === 'customer')->and($vehicle->fresh()->trashed())->toBe($case === 'vehicle');
})->with(['customer', 'vehicle', 'foreign']);

it('uses trusted linked customer contact snapshots instead of forged request contact', function () {
    $account = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $account->id, 'name' => 'Nama terpercaya', 'phone' => '6281999999999', 'email' => 'trusted@example.test']);
    $booking = app(CreateBooking::class)->create($account, revisionBookingInput());
    expect($booking->customer_id)->toBe($customer->id)->and($booking->name)->toBe($customer->name)
        ->and($booking->phone)->toBe($customer->phone)->and($booking->email)->toBe($customer->email);
});

it('rejects duplicate guest open slots without returning existing booking or overwriting master data', function () {
    $customer = Customer::factory()->create(['phone' => '6281234567890', 'name' => 'Nama asli']);
    $vehicle = Vehicle::factory()->create(['customer_id' => $customer->id, 'license_plate' => 'B1234ABC', 'latest_mileage' => 0]);
    Booking::factory()->create(['customer_id' => $customer->id, 'vehicle_id' => $vehicle->id, 'booking_date' => now()->addDay()->toDateString(), 'arrival_time' => '09:00', 'status' => 'confirmed']);
    try {
        app(CreateBooking::class)->guest(revisionBookingInput());
        $this->fail('Duplicate booking accepted');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('arrival_time');
    }
    expect(Booking::count())->toBe(1)->and($customer->fresh()->name)->toBe('Nama asli')->and($vehicle->fresh()->latest_mileage)->toBe(0);
});

it('limits guest action attempts to five per minute by request IP even with invalid input', function () {
    request()->server->set('REMOTE_ADDR', '192.0.2.101');
    for ($i = 0; $i < 5; $i++) {
        try {
            app(CreateBooking::class)->guest([]);
        } catch (ValidationException $exception) {
            expect($exception->errors())->toHaveKey('name');
        }
    }
    try {
        app(CreateBooking::class)->guest([]);
        $this->fail('Sixth guest attempt accepted');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('throttle')->not->toHaveKey('name');
    }
});

it('converts authoritative booking relations even after trusted master contacts change', function () {
    $actor = User::factory()->create(['role' => 'admin']);
    $customer = Customer::factory()->create(['phone' => '6281999999999']);
    $vehicle = Vehicle::factory()->create(['customer_id' => $customer->id, 'license_plate' => 'D9999NEW', 'latest_mileage' => 0]);
    $booking = Booking::factory()->create(['customer_id' => $customer->id, 'vehicle_id' => $vehicle->id, 'status' => 'arrived']);
    $order = app(ConvertBooking::class)->convert($actor, $booking);
    expect($order->customer_id)->toBe($customer->id)->and($order->vehicle_id)->toBe($vehicle->id)
        ->and(Customer::count())->toBe(1)->and(Vehicle::count())->toBe(1);
});

it('rejects inconsistent related vehicle ownership instead of falling back to snapshot matching', function () {
    $actor = User::factory()->create(['role' => 'admin']);
    $vehicle = Vehicle::factory()->create();
    $booking = Booking::factory()->create(['customer_id' => Customer::factory()->create()->id, 'vehicle_id' => $vehicle->id, 'status' => 'arrived']);
    expect(fn () => app(ConvertBooking::class)->convert($actor, $booking))->toThrow(ValidationException::class);
    expect(ServiceOrder::count())->toBe(0)->and($booking->fresh()->status)->toBe(BookingStatus::Arrived);
});

it('rejects an idempotent converted order whose related vehicle no longer belongs to the booking customer', function () {
    $actor = User::factory()->create(['role' => 'admin']);
    $vehicle = Vehicle::factory()->create(['latest_mileage' => 0]);
    $booking = Booking::factory()->create(['customer_id' => $vehicle->customer_id, 'vehicle_id' => $vehicle->id, 'status' => 'arrived']);
    app(ConvertBooking::class)->convert($actor, $booking);
    $vehicle->update(['customer_id' => Customer::factory()->create()->id]);
    expect(fn () => app(ConvertBooking::class)->convert($actor, $booking))->toThrow(ValidationException::class);
});

it('rolls conversion back when final audit is silently vetoed', function () {
    $actor = User::factory()->create(['role' => 'admin']);
    $vehicle = Vehicle::factory()->create(['latest_mileage' => 0]);
    $booking = Booking::factory()->create(['customer_id' => $vehicle->customer_id, 'vehicle_id' => $vehicle->id, 'status' => 'arrived']);
    AuditLog::creating(fn ($audit) => $audit->action === 'booking.converted' ? false : null);
    try {
        expect(fn () => app(ConvertBooking::class)->convert($actor, $booking))->toThrow(RuntimeException::class);
    } finally {
        AuditLog::flushEventListeners();
    }
    expect(ServiceOrder::count())->toBe(0)->and($vehicle->fresh()->latest_mileage)->toBe(0)
        ->and($booking->fresh()->status)->toBe(BookingStatus::Arrived);
});

it('does not require redundant ownership confirmation for an already trusted related account', function () {
    $actor = User::factory()->create(['role' => 'admin']);
    $account = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $account->id]);
    $vehicle = Vehicle::factory()->create(['customer_id' => $customer->id, 'latest_mileage' => 0]);
    $booking = Booking::factory()->create(['customer_id' => $customer->id, 'vehicle_id' => $vehicle->id, 'submitted_by' => $account->id, 'status' => 'arrived']);
    $order = app(ConvertBooking::class)->convert($actor, $booking, null, ['link_account' => true]);
    expect($order->customer_id)->toBe($customer->id)->and(AuditLog::where('action', 'customer.account_linked')->count())->toBe(0);
});

it('requires staff confirmation before linking a fresh related offline customer to its submitter', function () {
    $actor = User::factory()->create(['role' => 'admin']);
    $account = User::factory()->create();
    $customer = Customer::factory()->create();
    $vehicle = Vehicle::factory()->create(['customer_id' => $customer->id, 'latest_mileage' => 0]);
    $booking = Booking::factory()->create(['customer_id' => $customer->id, 'vehicle_id' => $vehicle->id, 'submitted_by' => $account->id, 'status' => 'arrived']);
    expect(fn () => app(ConvertBooking::class)->convert($actor, $booking, null, ['link_account' => true]))->toThrow(ValidationException::class);
    app(ConvertBooking::class)->convert($actor, $booking, null, ['link_account' => true, 'ownership_verified' => true]);
    expect($customer->fresh()->user_id)->toBe($account->id);
});

it('keeps an existing different account customer link even when reception confirms fresh booking ownership', function () {
    $actor = User::factory()->create(['role' => 'admin']);
    $account = User::factory()->create();
    $alreadyLinked = Customer::factory()->create(['user_id' => $account->id]);
    $customer = Customer::factory()->create();
    $vehicle = Vehicle::factory()->create(['customer_id' => $customer->id, 'latest_mileage' => 0]);
    $booking = Booking::factory()->create(['customer_id' => $customer->id, 'vehicle_id' => $vehicle->id, 'submitted_by' => $account->id, 'status' => 'arrived']);
    expect(fn () => app(ConvertBooking::class)->convert($actor, $booking, null, ['link_account' => true, 'ownership_verified' => true]))->toThrow(ValidationException::class);
    expect($customer->fresh()->user_id)->toBeNull()->and($alreadyLinked->fresh()->user_id)->toBe($account->id)
        ->and(ServiceOrder::count())->toBe(0)->and($vehicle->fresh()->latest_mileage)->toBe(0);
});

it('backfills only canonical owner phone and plate matches without changing snapshots or account links', function () {
    $migration = require database_path('migrations/2026_10_08_000024_add_booking_master_relations.php');
    $migration->down();
    $account = User::factory()->create();
    $customer = Customer::factory()->create(['phone' => '6281234567890']);
    $vehicle = Vehicle::factory()->create(['customer_id' => $customer->id, 'license_plate' => 'B1234ABC']);
    $matching = Booking::factory()->create(['phone' => '0812 3456 7890', 'license_plate' => 'b 1234 abc', 'submitted_by' => $account->id]);
    $mismatch = Booking::factory()->create(['phone' => '6281999999999', 'license_plate' => 'B1234ABC']);
    $before = $matching->getAttributes();
    $migration->up();
    expect($matching->fresh()->customer->id)->toBe($customer->id)
        ->and($matching->fresh()->vehicle->id)->toBe($vehicle->id)
        ->and($matching->fresh()->only(array_keys($before)))->toEqual($matching->only(array_keys($before)))
        ->and($mismatch->fresh()->customer_id)->toBeNull()
        ->and($mismatch->fresh()->vehicle_id)->toBeNull()
        ->and($customer->fresh()->user_id)->toBeNull();
});
