<?php

use App\Actions\ManageCheckInCode;
use App\Actions\ProcessCheckIn;
use App\Actions\SubmitCheckIn;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\ServiceOrder;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

it('accepts a rotating code and reuses a waiting contact', function () {
    $owner = User::factory()->create(['role' => 'owner']);
    $code = app(ManageCheckInCode::class)->generate($owner);
    $input = ['code' => $code->code, 'name' => 'Tamu', 'phone' => '081234567890'];
    $first = app(SubmitCheckIn::class)->submit($input);
    $second = app(SubmitCheckIn::class)->submit($input);
    expect($first->id)->toBe($second->id)->and(Customer::count())->toBe(1);
    app(ManageCheckInCode::class)->disable($owner);
    expect(fn () => app(SubmitCheckIn::class)->submit($input))->toThrow(ValidationException::class);
});

it('processes check in atomically once and self assigns the mechanic', function () {
    $mechanic = User::factory()->create(['role' => 'mechanic']);
    $code = app(ManageCheckInCode::class)->generate($mechanic);
    $checkIn = app(SubmitCheckIn::class)->submit(['code' => $code->code, 'name' => 'Tamu', 'phone' => '081234567891']);
    $input = ['vehicle' => ['license_plate' => 'D 1234 ABC', 'brand' => 'Honda', 'model' => 'Vario'], 'current_mileage' => 100, 'identity_verified' => true, 'complaint' => 'Rem bunyi'];
    $order = app(ProcessCheckIn::class)->process($mechanic, $checkIn, $input);
    expect(app(ProcessCheckIn::class)->process($mechanic, $checkIn, $input)->id)->toBe($order->id)
        ->and($order->mechanic_id)->toBe($mechanic->id)->and(ServiceOrder::count())->toBe(1);
});

it('rejects foreign checkin vehicles without partial records', function () {
    $actor = User::factory()->create(['role' => 'admin']);
    $code = app(ManageCheckInCode::class)->generate($actor);
    $checkIn = app(SubmitCheckIn::class)->submit(['code' => $code->code, 'name' => 'Tamu', 'phone' => '081234567892']);
    $foreign = Vehicle::factory()->create();
    expect(fn () => app(ProcessCheckIn::class)->process($actor, $checkIn, ['vehicle_id' => $foreign->id, 'current_mileage' => 100, 'identity_verified' => true, 'complaint' => 'Cek']))->toThrow(ValidationException::class);
    expect(ServiceOrder::count())->toBe(0);
});

it('rejects invalid expired codes and throttles attempts without creating customers', function () {
    $actor = User::factory()->create(['role' => 'owner']);
    $code = app(ManageCheckInCode::class)->generate($actor);
    $code->update(['expires_at' => now()->subMinute()]);
    for ($i = 0; $i < 5; $i++) {
        expect(fn () => app(SubmitCheckIn::class)->submit(['code' => $code->code, 'name' => 'Tamu', 'phone' => '081234567890']))->toThrow(ValidationException::class);
    }
    try {
        app(SubmitCheckIn::class)->submit([]);
        $this->fail('Throttle missing');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('code');
    }
    expect(Customer::count())->toBe(0);
});

it('rolls checkin conversion back when audit fails', function () {
    $actor = User::factory()->create(['role' => 'admin']);
    $code = app(ManageCheckInCode::class)->generate($actor);
    $checkIn = app(SubmitCheckIn::class)->submit(['code' => $code->code, 'name' => 'Tamu', 'phone' => '081234567890']);
    AuditLog::creating(fn ($audit) => $audit->action === 'check_in.converted' ? false : null);
    try {
        expect(fn () => app(ProcessCheckIn::class)->process($actor, $checkIn, ['vehicle' => ['license_plate' => 'D1234AA', 'brand' => 'Honda', 'model' => 'Vario'], 'current_mileage' => 1, 'identity_verified' => true, 'complaint' => 'Cek']))->toThrow(RuntimeException::class);
    } finally {
        AuditLog::flushEventListeners();
    }
    expect(ServiceOrder::count())->toBe(0)->and(Vehicle::count())->toBe(0)
        ->and($checkIn->fresh()->status->value)->toBe('waiting');
});

it('protects staff queue and QR from guests and customers', function () {
    foreach (['/check-ins', '/check-ins/qr'] as $path) {
        $this->get($path)->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get($path)->assertForbidden();
        auth()->logout();
    }
    $this->get('/check-in')->assertOk();
});

it('encrypts active codes and excludes them from audit and model serialization', function () {
    $code = app(ManageCheckInCode::class)->generate(User::factory()->create(['role' => 'admin']));
    expect($code->getRawOriginal('code'))->not->toBe($code->code)
        ->and($code->toArray())->not->toHaveKey('code')
        ->and(json_encode(AuditLog::latest()->first()->context))->not->toContain($code->code);
});

it('rolls code rotation back if its audit is vetoed', function () {
    $actor = User::factory()->create(['role' => 'admin']);
    $code = app(ManageCheckInCode::class)->generate($actor);
    $previous = $code->code;
    AuditLog::creating(fn () => false);
    try {
        expect(fn () => app(ManageCheckInCode::class)->generate($actor))->toThrow(RuntimeException::class);
    } finally {
        AuditLog::flushEventListeners();
    }
    expect($code->fresh()->code)->toBe($previous);
});

it('rolls cancellation back if its audit is vetoed', function () {
    $actor = User::factory()->create(['role' => 'admin']);
    $code = app(ManageCheckInCode::class)->generate($actor);
    $entry = app(SubmitCheckIn::class)->submit(['code' => $code->code, 'name' => 'Test', 'phone' => '081234567890']);
    AuditLog::creating(fn () => false);
    try {
        expect(fn () => app(ProcessCheckIn::class)->cancel($actor, $entry))->toThrow(RuntimeException::class);
    } finally {
        AuditLog::flushEventListeners();
    }
    expect($entry->fresh()->status->value)->toBe('waiting');
});

it('does not return another mechanics service from an idempotent checkin retry', function () {
    $actor = User::factory()->create(['role' => 'mechanic']);
    $other = User::factory()->create(['role' => 'mechanic']);
    $code = app(ManageCheckInCode::class)->generate($actor);
    $entry = app(SubmitCheckIn::class)->submit(['code' => $code->code, 'name' => 'Test', 'phone' => '081234567890']);
    $input = ['vehicle' => ['license_plate' => 'D1234AA', 'brand' => 'Honda', 'model' => 'Vario'], 'current_mileage' => 1, 'identity_verified' => true, 'complaint' => 'Check'];
    app(ProcessCheckIn::class)->process($actor, $entry, $input);
    expect(fn () => app(ProcessCheckIn::class)->process($other, $entry, $input))->toThrow(AuthorizationException::class);
});

it('denies customers code management', function () {
    expect(fn () => app(ManageCheckInCode::class)->generate(User::factory()->create()))->toThrow(AuthorizationException::class);
});
