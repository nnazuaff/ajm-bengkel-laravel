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

it('rejects customer and mechanic intake actions', function (string $role) {
    $actor = User::factory()->create(['role' => $role]);
    expect(fn () => app(ReceiveWalkIn::class)->receive($actor, []))
        ->toThrow(AuthorizationException::class);
})->with(['customer', 'mechanic']);

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
