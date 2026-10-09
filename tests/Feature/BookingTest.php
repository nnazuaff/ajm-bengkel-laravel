<?php

use App\Actions\ConvertBooking;
use App\Actions\CreateBooking;
use App\Actions\UpdateBooking;
use App\Enums\BookingStatus;
use App\Enums\Role;
use App\Enums\ServiceSource;
use App\Enums\ServiceStatus;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\ServiceOrder;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\WorkshopInput;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

function bookingInput(array $overrides = []): array
{
    return array_replace([
        'name' => 'Pelanggan Booking', 'phone' => '0812 3456 7890', 'email' => 'booking@example.test',
        'license_plate' => 'b 1234 abc', 'brand' => 'Honda', 'model' => 'Vario', 'year' => 2023,
        'current_mileage' => 1500, 'booking_date' => now()->addDay()->toDateString(),
        'arrival_time' => '09:00', 'service_type' => 'Servis berkala', 'complaint' => 'Rem berisik', 'notes' => 'Pagi',
    ], $overrides);
}

// Revised flow creates master relations at submission; snapshots remain request-time evidence.
it('records an authenticated booking snapshot with resolved master records', function () {
    $user = User::factory()->create();
    $booking = app(CreateBooking::class)->create($user, bookingInput(['status' => 'converted_to_service', 'submitted_by' => 999]));

    expect($booking->status)->toBe(BookingStatus::Pending)
        ->and($booking->submitted_by)->toBe($user->id)
        ->and($booking->phone)->toBe('6281234567890')
        ->and($booking->license_plate)->toBe('B1234ABC')
        ->and($booking->booking_number)->toStartWith('BKG-')
        ->and(Customer::count())->toBe(1)->and(Vehicle::count())->toBe(1)->and(ServiceOrder::count())->toBe(0)
        ->and($booking->customer->user_id)->toBe($user->id)
        ->and($booking->vehicle->customer_id)->toBe($booking->customer_id)
        ->and($booking->vehicle->latest_mileage)->toBe(1500);
    $this->assertDatabaseHas('audit_logs', ['action' => 'booking.created', 'actor_id' => $user->id]);
    expect(json_encode(AuditLog::first()->context))->not->toContain($booking->phone);
});

it('allows admin review rescheduling and arrival but forbids forged conversion', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $booking = app(CreateBooking::class)->create(User::factory()->create(), bookingInput());
    $action = app(UpdateBooking::class);
    $action->update($admin, $booking, ['status' => 'confirmed']);
    $action->update($admin, $booking, ['status' => 'rescheduled', 'booking_date' => now()->addDays(2)->toDateString(), 'arrival_time' => '10:00']);
    expect($booking->fresh()->status)->toBe(BookingStatus::Rescheduled);
    $action->update($admin, $booking, ['status' => 'arrived']);
    expect($booking->fresh()->status)->toBe(BookingStatus::Arrived);
    expect(fn () => $action->update($admin, $booking, ['status' => 'converted_to_service']))->toThrow(ValidationException::class);
});

it('only lets a verified submitter cancel a nonarrived booking', function () {
    $user = User::factory()->create();
    $other = User::factory()->create(['email' => 'someone@example.test']);
    $booking = app(CreateBooking::class)->create($user, bookingInput(['email' => $other->email]));
    expect(fn () => app(UpdateBooking::class)->cancel($other, $booking))->toThrow(AuthorizationException::class);
    app(UpdateBooking::class)->cancel($user, $booking);
    expect($booking->fresh()->status)->toBe(BookingStatus::Cancelled);
    expect(fn () => app(UpdateBooking::class)->cancel($user, $booking))->toThrow(AuthorizationException::class);
});

it('requires a reason to reject and makes terminal status immutable', function () {
    $admin = User::factory()->create(['role' => Role::Owner]);
    $booking = app(CreateBooking::class)->create($admin, bookingInput());
    expect($booking->submitted_by)->toBeNull();
    expect(fn () => app(UpdateBooking::class)->update($admin, $booking, ['status' => 'rejected']))->toThrow(ValidationException::class);
    app(UpdateBooking::class)->update($admin, $booking, ['status' => 'rejected', 'admin_notes' => 'Slot penuh']);
    expect(fn () => app(UpdateBooking::class)->update($admin, $booking, ['status' => 'confirmed']))->toThrow(ValidationException::class);
});

it('validates booking snapshots and slot boundaries', function ($overrides, $field) {
    $user = User::factory()->create();
    try {
        app(CreateBooking::class)->create($user, bookingInput($overrides));
        $this->fail('Invalid booking accepted');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey($field);
    }
    expect(Booking::count())->toBe(0);
})->with([
    [['name' => ['bad']], 'name'], [['phone' => ['bad']], 'phone'], [['phone' => '62abc1234567'], 'phone'],
    [['license_plate' => ['bad']], 'license_plate'], [['current_mileage' => -1], 'current_mileage'],
    [['current_mileage' => 2147483648], 'current_mileage'], [['arrival_time' => '07:59'], 'arrival_time'],
    [['arrival_time' => '17:01'], 'arrival_time'], [['arrival_time' => ['bad']], 'arrival_time'],
    [['booking_date' => '2000-01-01'], 'booking_date'], [['booking_date' => '2200-01-01'], 'booking_date'],
    [['booking_date' => ['bad']], 'booking_date'], [['notes' => str_repeat('x', 5001)], 'notes'],
]);

it('denies unverified customers mechanics archived users and stale forged roles', function () {
    foreach ([User::factory()->unverified()->create(), User::factory()->create(['role' => Role::Mechanic])] as $actor) {
        expect(fn () => app(CreateBooking::class)->create($actor, bookingInput()))->toThrow(AuthorizationException::class);
    }
    $actor = User::factory()->create(['role' => Role::Admin]);
    User::whereKey($actor->id)->update(['role' => Role::Mechanic]);
    expect(fn () => app(CreateBooking::class)->create($actor, bookingInput()))->toThrow(AuthorizationException::class);
    $actor = User::factory()->create();
    $actor->delete();
    expect(fn () => app(CreateBooking::class)->create($actor, bookingInput()))->toThrow(AuthorizationException::class);
});

it('requires reschedule slots and rejects malformed admin scalars', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $booking = app(CreateBooking::class)->create($admin, bookingInput());
    foreach ([['status' => 'rescheduled'], ['status' => ['bad']], ['status' => 'confirmed', 'admin_notes' => ['bad']]] as $input) {
        expect(fn () => app(UpdateBooking::class)->update($admin, $booking, $input))->toThrow(ValidationException::class);
    }
});

it('rolls back booking creation if its audit cannot be saved', function () {
    AuditLog::creating(fn () => throw new RuntimeException('audit failure'));
    try {
        expect(fn () => app(CreateBooking::class)->create(User::factory()->create(), bookingInput()))->toThrow(RuntimeException::class);
    } finally {
        AuditLog::flushEventListeners();
    }
    expect(Booking::count())->toBe(0)->and(DB::table('document_sequences')->count())->toBe(0);
});

function arrivedBooking(User $admin, array $overrides = []): Booking
{
    // Legacy snapshot fixture exercises pre-revision reception compatibility.
    $input = bookingInput($overrides);
    $input['phone'] = WorkshopInput::phone($input['phone']);
    $input['license_plate'] = WorkshopInput::plate($input['license_plate']);
    $booking = Booking::factory()->create([...$input, 'submitted_by' => User::factory()->create()->id]);
    app(UpdateBooking::class)->update($admin, $booking, ['status' => 'confirmed']);

    return app(UpdateBooking::class)->update($admin, $booking, ['status' => 'arrived']);
}

it('converts an arrived snapshot atomically exactly once into a waiting booking service', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $mechanic = User::factory()->create(['role' => Role::Mechanic]);
    $booking = arrivedBooking($admin);
    $order = app(ConvertBooking::class)->convert($admin, $booking, $mechanic->id);
    $again = app(ConvertBooking::class)->convert($admin, $booking);
    expect($again->id)->toBe($order->id)->and(ServiceOrder::count())->toBe(1)
        ->and($order->source)->toBe(ServiceSource::Booking)->and($order->status)->toBe(ServiceStatus::Waiting)
        ->and($order->booking_id)->toBe($booking->id)->and($order->mechanic_id)->toBe($mechanic->id)
        ->and($order->current_mileage)->toBe($booking->current_mileage)->and($order->complaint)->toBe($booking->complaint)
        ->and($order->notes)->toBe($booking->notes)->and($booking->fresh()->status)->toBe(BookingStatus::ConvertedToService)
        ->and($order->customer->user_id)->toBeNull()->and($order->service_number)->toStartWith('SRV-');
    $this->assertDatabaseHas('audit_logs', ['action' => 'booking.converted', 'entity_id' => $booking->id]);
    expect(fn () => app(ConvertBooking::class)->convert($booking->submitter, $booking))->toThrow(AuthorizationException::class);
});

it('reuses the existing vehicle only when the snapshot phone matches its active owner', function () {
    $admin = User::factory()->create(['role' => Role::Owner]);
    $owner = Customer::factory()->create(['phone' => '6281234567890', 'name' => 'Nama asli']);
    $vehicle = Vehicle::factory()->create(['customer_id' => $owner->id, 'license_plate' => 'B1234ABC', 'latest_mileage' => 1000]);
    $booking = arrivedBooking($admin);
    $order = app(ConvertBooking::class)->convert($admin, $booking);
    expect($order->vehicle_id)->toBe($vehicle->id)->and($order->customer_id)->toBe($owner->id)
        ->and(Customer::count())->toBe(1)->and(Vehicle::count())->toBe(1)
        ->and($owner->fresh()->name)->toBe('Nama asli')->and($vehicle->fresh()->latest_mileage)->toBe(1500);
});

it('reuses canonical phone customer for a new vehicle without linking the booking account', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $owner = Customer::factory()->create(['phone' => '6281234567890', 'name' => 'Jangan ubah']);
    $order = app(ConvertBooking::class)->convert($admin, arrivedBooking($admin));
    expect($order->customer_id)->toBe($owner->id)->and(Customer::count())->toBe(1)
        ->and($owner->fresh()->name)->toBe('Jangan ubah')->and($owner->fresh()->user_id)->toBeNull();
});

it('rejects conversion before arrival and after rejection cancellation', function ($status) {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $booking = Booking::factory()->create(['status' => $status]);
    expect(fn () => app(ConvertBooking::class)->convert($admin, $booking))->toThrow(ValidationException::class);
    expect(ServiceOrder::count())->toBe(0)->and(Customer::count())->toBe(0);
})->with(['pending', 'confirmed', 'rescheduled', 'rejected', 'cancelled']);

it('rejects conflicting owner archived masters or regressing mileage without side effects', function ($case) {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $owner = Customer::factory()->create(['phone' => $case === 'owner' ? '6281111111111' : '6281234567890']);
    $vehicle = Vehicle::factory()->create(['customer_id' => $owner->id, 'license_plate' => 'B1234ABC', 'latest_mileage' => $case === 'mileage' ? 2000 : 1000]);
    if ($case === 'archived_vehicle') {
        $vehicle->delete();
    }
    if ($case === 'archived_customer') {
        $owner->delete();
    }
    $booking = arrivedBooking($admin);
    expect(fn () => app(ConvertBooking::class)->convert($admin, $booking))->toThrow(ValidationException::class);
    expect(ServiceOrder::count())->toBe(0)->and($booking->fresh()->status)->toBe(BookingStatus::Arrived)
        ->and($vehicle->fresh()->customer_id)->toBe($owner->id);
})->with(['owner', 'archived_vehicle', 'archived_customer', 'mileage']);

it('rolls conversion and master records back when the final audit fails', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $booking = arrivedBooking($admin);
    AuditLog::creating(function ($log) {
        if ($log->action === 'booking.converted') {
            throw new RuntimeException('audit failure');
        }
    });
    try {
        expect(fn () => app(ConvertBooking::class)->convert($admin, $booking))->toThrow(RuntimeException::class);
    } finally {
        AuditLog::flushEventListeners();
    }
    expect(ServiceOrder::count())->toBe(0)->and(Customer::count())->toBe(0)->and(Vehicle::count())->toBe(0)
        ->and($booking->fresh()->status)->toBe(BookingStatus::Arrived);
});

it('protects one order per booking with a database unique constraint', function () {
    $booking = Booking::factory()->create(['status' => BookingStatus::Arrived]);
    $admin = User::factory()->create(['role' => Role::Admin]);
    $order = app(ConvertBooking::class)->convert($admin, $booking);
    $copy = $order->replicate();
    $copy->service_number = 'SRV-other';
    expect(fn () => $copy->save())->toThrow(UniqueConstraintViolationException::class);
});

it('rejects conversion while the vehicle already has an active service', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $booking = arrivedBooking($admin);
    $owner = Customer::factory()->create(['phone' => $booking->phone]);
    $vehicle = Vehicle::factory()->create(['customer_id' => $owner->id, 'license_plate' => $booking->license_plate, 'latest_mileage' => 1000]);
    ServiceOrder::factory()->create(['customer_id' => $owner->id, 'vehicle_id' => $vehicle->id]);
    expect(fn () => app(ConvertBooking::class)->convert($admin, $booking))->toThrow(ValidationException::class);
    expect(ServiceOrder::count())->toBe(1)->and($booking->fresh()->status)->toBe(BookingStatus::Arrived)
        ->and($vehicle->fresh()->latest_mileage)->toBe(1000);
});

it('does not reuse or link a customer solely through email when creating a new vehicle', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $owner = Customer::factory()->create(['email' => 'booking@example.test', 'phone' => '6281111111111']);
    $booking = arrivedBooking($admin);
    $order = app(ConvertBooking::class)->convert($admin, $booking);
    expect($order->customer_id)->not->toBe($owner->id);
    expect(Customer::count())->toBe(2)->and($order->customer->user_id)->toBeNull()->and($owner->fresh()->user_id)->toBeNull();
});

it('rolls back a booking status update when its audit fails', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $booking = arrivedBooking($admin);
    AuditLog::creating(fn () => throw new RuntimeException('audit failure'));
    try {
        expect(fn () => app(UpdateBooking::class)->update($admin, $booking, ['status' => 'cancelled', 'admin_notes' => 'Batal']))->toThrow(RuntimeException::class);
    } finally {
        AuditLog::flushEventListeners();
    }
    expect($booking->fresh()->status)->toBe(BookingStatus::Arrived)->and($booking->fresh()->admin_notes)->toBeNull();
});

it('rejects an inconsistent converted booking instead of creating another service', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $booking = Booking::factory()->create(['status' => BookingStatus::ConvertedToService]);
    expect(fn () => app(ConvertBooking::class)->convert($admin, $booking))->toThrow(ValidationException::class);
    expect(ServiceOrder::count())->toBe(0);
});
