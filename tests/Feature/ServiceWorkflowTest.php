<?php

use App\Actions\UpdateServiceOrder;
use App\Enums\ServiceStatus;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\ServiceOrder;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

it('records a service start with a distinct diagnosis and audit event', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $order = ServiceOrder::factory()->create(['status' => ServiceStatus::Approved]);
    app(UpdateServiceOrder::class)->update($admin, $order, [
        'status' => ServiceStatus::InProgress->value,
        'diagnosis' => 'Kampas rem tipis.',
        'notes' => 'Pelanggan menyetujui penggantian.',
    ]);

    expect($order->fresh()->status)->toBe(ServiceStatus::InProgress)
        ->and($order->fresh()->started_at)->not->toBeNull()
        ->and($order->fresh()->diagnosis)->toBe('Kampas rem tipis.')
        ->and($order->fresh()->complaint)->toBe($order->complaint)
        ->and(AuditLog::where('action', 'service.updated')->count())->toBe(1);
});

it('rejects illegal jumps and final record edits', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $order = ServiceOrder::factory()->create(['status' => ServiceStatus::Waiting]);
    expect(fn () => app(UpdateServiceOrder::class)->update($admin, $order, ['status' => 'delivered']))
        ->toThrow(ValidationException::class);

    $order->update(['status' => ServiceStatus::Delivered]);
    expect(fn () => app(UpdateServiceOrder::class)->update($admin, $order, ['status' => 'delivered', 'diagnosis' => 'Ubah']))
        ->toThrow(ValidationException::class);
});

it('restricts mechanics to assigned orders and work-only changes', function () {
    $mechanic = User::factory()->create(['role' => 'mechanic']);
    $other = User::factory()->create(['role' => 'mechanic']);
    $order = ServiceOrder::factory()->create(['mechanic_id' => $mechanic->id, 'status' => 'in_progress']);

    expect(fn () => app(UpdateServiceOrder::class)->update($other, $order, ['status' => 'completed']))
        ->toThrow(AuthorizationException::class);
    expect(fn () => app(UpdateServiceOrder::class)->update($mechanic, $order, ['status' => 'cancelled']))
        ->toThrow(ValidationException::class);
    expect(fn () => app(UpdateServiceOrder::class)->update($mechanic, $order, ['status' => 'in_progress', 'mechanic_id' => $other->id]))
        ->toThrow(ValidationException::class);

    app(UpdateServiceOrder::class)->update($mechanic, $order, ['status' => 'completed', 'diagnosis' => 'Rem diperiksa.']);
    expect($order->fresh()->completed_at)->not->toBeNull();
});

it('rejects explicitly cleared cancellation reasons instead of reusing old notes', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $order = ServiceOrder::factory()->create(['notes' => 'Catatan lama']);
    expect(fn () => app(UpdateServiceOrder::class)->update($admin, $order, ['status' => 'cancelled', 'notes' => null]))
        ->toThrow(ValidationException::class);
    expect($order->fresh()->status)->toBe(ServiceStatus::Waiting);
});

it('preserves required diagnosis throughout post-approval stages', function (string $current, string $next) {
    $admin = User::factory()->create(['role' => 'admin']);
    $order = ServiceOrder::factory()->create(['status' => $current, 'diagnosis' => 'Kampas tipis.']);
    expect(fn () => app(UpdateServiceOrder::class)->update($admin, $order, ['status' => $next, 'diagnosis' => null]))
        ->toThrow(ValidationException::class);
    expect($order->fresh()->diagnosis)->toBe('Kampas tipis.');
})->with([['approved', 'in_progress'], ['in_progress', 'waiting_part'], ['completed', 'ready_for_pickup'], ['ready_for_pickup', 'delivered']]);

it('keeps service ownership separate from unrelated customer accounts', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $owner->id]);
    $vehicle = Vehicle::factory()->create(['customer_id' => $customer->id]);
    $order = ServiceOrder::factory()->create(['vehicle_id' => $vehicle->id, 'customer_id' => $customer->id]);

    expect(Gate::forUser($owner)->allows('view', $order))->toBeTrue()
        ->and(Gate::forUser($other)->allows('view', $order))->toBeFalse()
        ->and(Gate::forUser($owner)->allows('update', $order))->toBeFalse();
});
