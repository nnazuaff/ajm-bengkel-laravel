<?php

use App\Actions\ManageStaff;
use App\Enums\Role;
use App\Enums\ServiceStatus;
use App\Models\AuditLog;
use App\Models\ServiceJob;
use App\Models\ServiceOrder;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

it('updates staff through trusted assignment without changing credentials', function () {
    $owner = User::factory()->create(['role' => Role::Owner]);
    $staff = User::factory()->create(['role' => Role::Mechanic]);
    $password = $staff->password;
    app(ManageStaff::class)->save($owner, $staff, ['name' => 'Kasir Baru', 'role' => 'admin', 'password' => 'untrusted', 'email' => 'hacked@example.test']);
    expect($staff->fresh()->role)->toBe(Role::Admin)->and($staff->fresh()->name)->toBe('Kasir Baru')
        ->and($staff->fresh()->password)->toBe($password)->and($staff->fresh()->email)->toBe($staff->email);
    $audit = AuditLog::where('action', 'staff.updated')->firstOrFail();
    expect($audit->context)->toBe(['before' => ['name' => $staff->name, 'role' => 'mechanic'], 'after' => ['name' => 'Kasir Baru', 'role' => 'admin']]);
});

it('prevents demoting or deactivating the last owner', function () {
    $owner = User::factory()->create(['role' => Role::Owner]);
    expect(fn () => app(ManageStaff::class)->save($owner, $owner, ['name' => 'Owner', 'role' => 'admin']))->toThrow(ValidationException::class);
    expect(fn () => app(ManageStaff::class)->deactivate($owner, $owner))->toThrow(ValidationException::class);
    expect($owner->fresh()->role)->toBe(Role::Owner);
});

it('blocks self deactivation even with another owner', function () {
    $owner = User::factory()->create(['role' => Role::Owner]);
    User::factory()->create(['role' => Role::Owner]);
    expect(fn () => app(ManageStaff::class)->deactivate($owner, $owner))->toThrow(ValidationException::class);
});

it('blocks mechanic role loss and deactivation with unfinished assigned orders', function (ServiceStatus $status) {
    $owner = User::factory()->create(['role' => Role::Owner]);
    $mechanic = User::factory()->create(['role' => Role::Mechanic]);
    ServiceOrder::factory()->create(['mechanic_id' => $mechanic->id, 'status' => $status]);
    expect(fn () => app(ManageStaff::class)->save($owner, $mechanic, ['name' => 'Mechanic', 'role' => 'admin']))->toThrow(ValidationException::class);
    expect(fn () => app(ManageStaff::class)->deactivate($owner, $mechanic))->toThrow(ValidationException::class);
    expect($mechanic->fresh()->role)->toBe(Role::Mechanic);
})->with([ServiceStatus::Waiting, ServiceStatus::InProgress, ServiceStatus::Completed, ServiceStatus::ReadyForPickup]);

it('blocks mechanic role loss with active jobs assigned on another mechanic order', function (string $operation) {
    $owner = User::factory()->create(['role' => Role::Owner]);
    $mechanic = User::factory()->create(['role' => Role::Mechanic]);
    $order = ServiceOrder::factory()->create(['status' => ServiceStatus::InProgress]);
    ServiceJob::factory()->create(['service_order_id' => $order->id, 'mechanic_id' => $mechanic->id, 'status' => 'pending']);
    expect(fn () => $operation === 'save'
        ? app(ManageStaff::class)->save($owner, $mechanic, ['name' => 'Mechanic', 'role' => 'admin'])
        : app(ManageStaff::class)->deactivate($owner, $mechanic))->toThrow(ValidationException::class);
    expect($mechanic->fresh()->role)->toBe(Role::Mechanic);
    $this->assertNotSoftDeleted($mechanic);
})->with(['save', 'deactivate']);

it('soft deletes eligible staff and retains audited history', function () {
    $owner = User::factory()->create(['role' => Role::Owner]);
    $mechanic = User::factory()->create(['role' => Role::Mechanic]);
    ServiceOrder::factory()->create(['mechanic_id' => $mechanic->id, 'status' => ServiceStatus::Delivered]);
    app(ManageStaff::class)->deactivate($owner, $mechanic);
    $this->assertSoftDeleted($mechanic);
    expect(AuditLog::where('action', 'staff.deactivated')->firstOrFail()->entity_id)->toBe($mechanic->id);
});

it('rejects non owner and stale owner authority', function () {
    $owner = User::factory()->create(['role' => Role::Owner]);
    $target = User::factory()->create(['role' => Role::Mechanic]);
    User::whereKey($owner->id)->update(['role' => 'admin']);
    expect(fn () => app(ManageStaff::class)->save($owner, $target, ['name' => 'Changed', 'role' => 'owner']))->toThrow(AuthorizationException::class);
    expect(fn () => app(ManageStaff::class)->deactivate($owner, $target))->toThrow(AuthorizationException::class);
});

it('does not promote customers or accept invalid staff roles', function () {
    $owner = User::factory()->create(['role' => Role::Owner]);
    $customer = User::factory()->create();
    $staff = User::factory()->create(['role' => Role::Mechanic]);
    expect(fn () => app(ManageStaff::class)->save($owner, $customer, ['name' => 'Customer', 'role' => 'admin']))->toThrow(AuthorizationException::class);
    expect(fn () => app(ManageStaff::class)->save($owner, $staff, ['name' => 'Staff', 'role' => 'customer']))->toThrow(ValidationException::class);
});

it('rolls staff changes back when the audit write fails', function (string $operation) {
    $owner = User::factory()->create(['role' => Role::Owner]);
    $target = User::factory()->create(['role' => Role::Mechanic]);
    AuditLog::creating(fn () => throw new RuntimeException('Audit unavailable'));
    try {
        expect(fn () => $operation === 'save'
            ? app(ManageStaff::class)->save($owner, $target, ['name' => 'Changed', 'role' => 'admin'])
            : app(ManageStaff::class)->deactivate($owner, $target))->toThrow(RuntimeException::class);
    } finally {
        AuditLog::flushEventListeners();
    }
    expect($target->fresh()->role)->toBe(Role::Mechanic)->and($target->fresh()->name)->toBe($target->name);
    $this->assertNotSoftDeleted($target);
})->with(['save', 'deactivate']);

it('creates verified staff with hashed password and secret free audit', function () {
    $owner = User::factory()->create(['role' => Role::Owner]);
    $input = ['name' => 'Staf Baru', 'email' => 'staff@example.test', 'role' => 'mechanic', 'password' => 'Test-only!Password123', 'password_confirmation' => 'Test-only!Password123'];
    $staff = app(ManageStaff::class)->create($owner, $input);
    expect($staff->role)->toBe(Role::Mechanic)->and($staff->hasVerifiedEmail())->toBeTrue()
        ->and(Hash::check($input['password'], $staff->password))->toBeTrue();
    expect(AuditLog::where('action', 'staff.created')->firstOrFail()->context)->toBe(['role' => 'mechanic']);
});

it('validates provisioned staff email roles and password', function (array $override, string $field) {
    $owner = User::factory()->create(['role' => Role::Owner]);
    $archived = User::factory()->create(['email' => 'archived@example.test']);
    $archived->delete();
    $input = array_merge(['name' => 'Staf Baru', 'email' => 'staff@example.test', 'role' => 'mechanic', 'password' => 'Test-only!Password123', 'password_confirmation' => 'Test-only!Password123'], $override);
    try {
        app(ManageStaff::class)->create($owner, $input);
        test()->fail('Validation expected');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey($field);
    }
})->with([
    [['email' => 'archived@example.test'], 'email'],
    [['role' => 'customer'], 'role'],
    [['password' => 'short', 'password_confirmation' => 'short'], 'password'],
    [['password_confirmation' => 'different'], 'password'],
]);

it('rolls provisioning back on audit failure and rejects stale actor', function () {
    $owner = User::factory()->create(['role' => Role::Owner]);
    $input = ['name' => 'Staf Baru', 'email' => 'staff@example.test', 'role' => 'mechanic', 'password' => 'Test-only!Password123', 'password_confirmation' => 'Test-only!Password123'];
    AuditLog::creating(fn () => throw new RuntimeException('Audit unavailable'));
    try {
        expect(fn () => app(ManageStaff::class)->create($owner, $input))->toThrow(RuntimeException::class);
    } finally {
        AuditLog::flushEventListeners();
    }
    $this->assertDatabaseMissing('users', ['email' => 'staff@example.test']);
    User::whereKey($owner->id)->update(['role' => 'admin']);
    expect(fn () => app(ManageStaff::class)->create($owner, $input))->toThrow(AuthorizationException::class);
});
