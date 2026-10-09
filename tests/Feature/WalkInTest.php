<?php

use App\Actions\ReceiveWalkIn;
use App\Enums\ServiceStatus;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\ServiceOrder;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

it('receives an existing vehicle and snapshots its mileage atomically', function () {
    $actor = User::factory()->create(['role' => 'admin']);
    $vehicle = Vehicle::factory()->create(['latest_mileage' => 1000]);
    $order = app(ReceiveWalkIn::class)->receive($actor, [
        'vehicle_id' => $vehicle->id,
        'current_mileage' => 1200,
        'complaint' => 'Rem belakang berbunyi.',
    ]);

    expect($order->status)->toBe(ServiceStatus::Waiting)
        ->and($order->customer_id)->toBe($vehicle->customer_id)
        ->and($order->current_mileage)->toBe(1200)
        ->and($vehicle->fresh()->latest_mileage)->toBe(1200)
        ->and(AuditLog::where('action', 'service.received')->count())->toBe(1);
});

it('creates a customer and vehicle in the same intake', function () {
    $actor = User::factory()->create(['role' => 'owner']);
    $order = app(ReceiveWalkIn::class)->receive($actor, [
        'customer' => ['name' => 'Sari Wulandari', 'phone' => '0812 3456 7890'],
        'vehicle' => ['license_plate' => 'b 1234 abc', 'brand' => 'Honda', 'model' => 'Vario'],
        'current_mileage' => 500,
        'complaint' => 'Ganti oli mesin.',
    ]);

    expect($order->vehicle->license_plate)->toBe('B1234ABC')
        ->and($order->customer->phone)->toBe('6281234567890')
        ->and(Customer::count())->toBe(1)
        ->and(Vehicle::count())->toBe(1);
});

it('reuses an existing phone contact without overwriting its profile', function () {
    $actor = User::factory()->create(['role' => 'admin']);
    $customer = Customer::factory()->create(['name' => 'Sari Wulandari', 'phone' => '6281234567890']);
    $order = app(ReceiveWalkIn::class)->receive($actor, [
        'customer' => ['name' => 'Jangan timpa nama', 'phone' => '+62 81234567890'],
        'vehicle' => ['license_plate' => 'D 9981 JK', 'brand' => 'Yamaha', 'model' => 'Mio'],
        'current_mileage' => 1500,
        'complaint' => 'Servis CVT.',
    ]);

    expect($order->customer_id)->toBe($customer->id)
        ->and($customer->fresh()->name)->toBe('Sari Wulandari')
        ->and(Customer::count())->toBe(1);
});

// Revised intake reuses same-owner plates; it still rejects foreign ownership and active orders.
it('reuses canonical phone and plate without overwriting trusted master details', function () {
    $actor = User::factory()->create(['role' => 'admin']);
    $customer = Customer::factory()->create(['phone' => '6281234567890', 'name' => 'Nama asli']);
    $vehicle = Vehicle::factory()->create(['customer_id' => $customer->id, 'license_plate' => 'B1234ABC', 'brand' => 'Honda', 'latest_mileage' => 100]);
    $order = app(ReceiveWalkIn::class)->receive($actor, [
        'customer' => ['name' => 'Nama input', 'phone' => '0812 3456 7890'],
        'vehicle' => ['license_plate' => 'b 1234 abc', 'brand' => 'Yamaha', 'model' => 'Input'],
        'current_mileage' => 150, 'complaint' => 'Periksa rem',
    ]);
    expect($order->customer_id)->toBe($customer->id)->and($order->vehicle_id)->toBe($vehicle->id)
        ->and($customer->fresh()->name)->toBe('Nama asli')->and($vehicle->fresh()->brand)->toBe('Honda')
        ->and(Customer::count())->toBe(1)->and(Vehicle::count())->toBe(1);
});

it('rejects same plate under another owner without leaking or changing that owner', function () {
    $actor = User::factory()->create(['role' => 'admin']);
    $vehicle = Vehicle::factory()->create(['license_plate' => 'B1234ABC', 'latest_mileage' => 0]);
    expect(fn () => app(ReceiveWalkIn::class)->receive($actor, [
        'customer' => ['name' => 'Nama baru', 'phone' => '0812 3456 7890'],
        'vehicle' => ['license_plate' => 'b 1234 abc', 'brand' => 'Honda', 'model' => 'Beat'],
        'current_mileage' => 100, 'complaint' => 'Periksa rem',
    ]))->toThrow(ValidationException::class);
    expect(ServiceOrder::count())->toBe(0)->and(Customer::count())->toBe(1)->and(Vehicle::count())->toBe(1)
        ->and($vehicle->fresh()->latest_mileage)->toBe(0);
});

it('rejects lower mileage and a non-mechanic assignment', function () {
    $actor = User::factory()->create(['role' => 'admin']);
    $vehicle = Vehicle::factory()->create(['latest_mileage' => 5000]);
    $customerAccount = User::factory()->create();
    $data = ['vehicle_id' => $vehicle->id, 'current_mileage' => 4900, 'complaint' => 'Periksa rem.'];

    expect(fn () => app(ReceiveWalkIn::class)->receive($actor, $data))->toThrow(ValidationException::class);
    $data['current_mileage'] = 5100;
    $data['mechanic_id'] = $customerAccount->id;
    expect(fn () => app(ReceiveWalkIn::class)->receive($actor, $data))->toThrow(ValidationException::class);
    expect(ServiceOrder::count())->toBe(0)->and($vehicle->fresh()->latest_mileage)->toBe(5000);
});

it('rolls all intake data back when the audit write fails', function () {
    $actor = User::factory()->create(['role' => 'admin']);
    AuditLog::creating(fn () => throw new RuntimeException('Audit unavailable'));

    try {
        app(ReceiveWalkIn::class)->receive($actor, [
            'customer' => ['name' => 'Dimas Saputra', 'phone' => '081298765432'],
            'vehicle' => ['license_plate' => 'F 4512 NO', 'brand' => 'Honda', 'model' => 'Beat'],
            'current_mileage' => 250,
            'complaint' => 'Periksa lampu.',
        ]);
        $this->fail('The audit failure must roll back the intake.');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('Audit unavailable');
    } finally {
        AuditLog::flushEventListeners();
    }

    expect(Customer::count())->toBe(0)
        ->and(Vehicle::count())->toBe(0)
        ->and(ServiceOrder::count())->toBe(0)
        ->and(DB::table('document_sequences')->count())->toBe(0);
});

it('rolls intake back when its audit is silently vetoed', function () {
    $actor = User::factory()->create(['role' => 'admin']);
    $vehicle = Vehicle::factory()->create(['latest_mileage' => 0]);
    AuditLog::creating(fn () => false);
    try {
        expect(fn () => app(ReceiveWalkIn::class)->receive($actor, ['vehicle_id' => $vehicle->id, 'current_mileage' => 100, 'complaint' => 'Periksa rem']))->toThrow(RuntimeException::class);
    } finally {
        AuditLog::flushEventListeners();
    }
    expect(ServiceOrder::count())->toBe(0)->and($vehicle->fresh()->latest_mileage)->toBe(0);
});

it('rejects customer and mechanic intake actions', function (string $role) {
    $actor = User::factory()->create(['role' => $role]);
    expect(fn () => app(ReceiveWalkIn::class)->receive($actor, []))
        ->toThrow(AuthorizationException::class);
})->with(['customer', 'mechanic']);

it('allows checkin mechanics only on their own behalf while preserving manager-only manual intake', function () {
    $actor = User::factory()->create(['role' => 'mechanic']);
    $other = User::factory()->create(['role' => 'mechanic']);
    $vehicle = Vehicle::factory()->create(['latest_mileage' => 0]);
    $data = ['vehicle_id' => $vehicle->id, 'current_mileage' => 100, 'complaint' => 'Periksa rem', 'mechanic_id' => $other->id];
    expect(fn () => app(ReceiveWalkIn::class)->receive($actor, $data))->toThrow(AuthorizationException::class);
    $order = app(ReceiveWalkIn::class)->receiveFromCheckIn($actor, $data);
    expect($order->mechanic_id)->toBe($actor->id)->and($order->received_by)->toBe($actor->id);
});

it('rejects revoked privileges at each checkin intake request', function () {
    $actor = User::factory()->create(['role' => 'mechanic']);
    User::whereKey($actor->id)->update(['role' => 'customer']);
    expect(fn () => app(ReceiveWalkIn::class)->receiveFromCheckIn($actor, []))->toThrow(AuthorizationException::class);
});

it('rejects a selected vehicle belonging to a different selected customer', function () {
    $actor = User::factory()->create(['role' => 'admin']);
    $vehicle = Vehicle::factory()->create(['latest_mileage' => 0]);
    $customer = Customer::factory()->create();
    expect(fn () => app(ReceiveWalkIn::class)->receive($actor, ['vehicle_id' => $vehicle->id, 'customer_id' => $customer->id, 'current_mileage' => 100, 'complaint' => 'Periksa rem']))->toThrow(ValidationException::class);
    expect(ServiceOrder::count())->toBe(0)->and($vehicle->fresh()->latest_mileage)->toBe(0);
});

it('rejects an invalid customer phone consistently with customer management', function () {
    $actor = User::factory()->create(['role' => 'admin']);
    expect(fn () => app(ReceiveWalkIn::class)->receive($actor, [
        'customer' => ['name' => 'Rizki Pratama', 'phone' => '62012345678'],
        'vehicle' => ['license_plate' => 'B 1235 ABC', 'brand' => 'Honda', 'model' => 'Beat'],
        'current_mileage' => 1200,
        'complaint' => 'Periksa rem.',
    ]))->toThrow(ValidationException::class);
});

it('requires a nonblank complaint at the action boundary', function () {
    $actor = User::factory()->create(['role' => 'admin']);
    $vehicle = Vehicle::factory()->create();
    expect(fn () => app(ReceiveWalkIn::class)->receive($actor, [
        'vehicle_id' => $vehicle->id,
        'current_mileage' => $vehicle->latest_mileage,
        'complaint' => '   ',
    ]))->toThrow(ValidationException::class);
});
