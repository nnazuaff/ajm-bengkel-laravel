<?php

use App\Actions\LinkCustomerAccount;
use App\Enums\Role;
use App\Livewire\CustomerAccount;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

it('does not infer ownership from matching contact details', function () {
    $actor = User::factory()->create(['role' => Role::Admin]);
    $account = User::factory()->create(['email' => 'same@example.test']);
    $customer = Customer::factory()->create(['email' => $account->email]);
    Livewire::actingAs($actor)->test(CustomerAccount::class, ['customerId' => $customer->id])->assertSet('userId', '');
    expect($customer->fresh()->user_id)->toBeNull();
    $this->assertDatabaseCount('audit_logs', 0);
});

it('excludes ineligible accounts from the native options', function () {
    $actor = User::factory()->create(['role' => Role::Admin]);
    $customer = Customer::factory()->create();
    $component = Livewire::actingAs($actor)->test(CustomerAccount::class, ['customerId' => $customer->id]);
    $unverified = User::factory()->unverified()->create();
    $archived = User::factory()->create();
    $archived->delete();
    $mechanic = User::factory()->create(['role' => Role::Mechanic]);
    $owned = User::factory()->create();
    Customer::factory()->create(['user_id' => $owned->id])->delete();
    $valid = User::factory()->create();
    $component->call('$refresh')->assertSee($valid->email)
        ->assertDontSee($unverified->email)->assertDontSee($archived->email)
        ->assertDontSee($mechanic->email)->assertDontSee($owned->email);
    $component->set('userId', (string) $unverified->id)->set('ownershipVerified', true)
        ->call('save')->assertHasErrors(['userId']);
    expect($customer->fresh()->user_id)->toBeNull();
});

it('unlinks through the panel only after acknowledgement', function () {
    $actor = User::factory()->create(['role' => Role::Owner]);
    $account = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $account->id]);
    Livewire::actingAs($actor)->test(CustomerAccount::class, ['customerId' => $customer->id])
        ->assertSet('userId', (string) $account->id)->assertSee($account->email)
        ->set('userId', '')->call('save')->assertHasErrors(['ownershipVerified'])
        ->set('ownershipVerified', true)->call('save')->assertHasNoErrors()
        ->assertSet('userId', '')->assertSet('ownershipVerified', false)
        ->assertSet('customerId', null)->assertSet('showForm', false)
        ->assertDispatched('customer-account-saved');
    expect($customer->fresh()->user_id)->toBeNull()
        ->and(AuditLog::where('action', 'customer.account_linked')->sole()->context)
        ->toBe(['previous_user_id' => $account->id, 'new_user_id' => null]);
});

it('rejects invalid submitted account identifiers in the panel', function (string $identifier) {
    $actor = User::factory()->create(['role' => Role::Admin]);
    $customer = Customer::factory()->create();
    Livewire::actingAs($actor)->test(CustomerAccount::class, ['customerId' => $customer->id])
        ->set('userId', $identifier)->set('ownershipVerified', true)
        ->call('save')->assertHasErrors(['userId']);
    expect($customer->fresh()->user_id)->toBeNull();
})->with(['not-an-id', '1.5', '0', '-1']);

it('does not mutate archived customer masters', function () {
    $actor = User::factory()->create(['role' => Role::Admin]);
    $customer = Customer::factory()->create();
    $customer->delete();
    $account = User::factory()->create();
    expect(fn () => app(LinkCustomerAccount::class)->link($actor, $customer, $account->id, true))
        ->toThrow(ModelNotFoundException::class);
    expect(Customer::withTrashed()->findOrFail($customer->id)->user_id)->toBeNull();
});

it('relinks or unlinks using the locked current owner rather than a stale relation', function (bool $unlink) {
    $actor = User::factory()->create(['role' => Role::Owner]);
    $old = User::factory()->create();
    $current = User::factory()->create();
    $next = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $old->id]);
    $customer->load('user');
    Customer::whereKey($customer->id)->update(['user_id' => $current->id]);
    $result = app(LinkCustomerAccount::class)->link($actor, $customer, $unlink ? null : $next->id, true);
    expect($result->user_id)->toBe($unlink ? null : $next->id)
        ->and($result->user?->id)->toBe($unlink ? null : $next->id);
    expect(AuditLog::where('action', 'customer.account_linked')->sole()->context)
        ->toBe(['previous_user_id' => $current->id, 'new_user_id' => $unlink ? null : $next->id]);
})->with([false, true]);

it('rolls the owner change back if its audit cannot be written', function (bool $unlink) {
    $actor = User::factory()->create(['role' => Role::Admin]);
    $old = User::factory()->create();
    $next = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $old->id]);
    AuditLog::creating(fn () => throw new RuntimeException('Audit unavailable'));
    try {
        expect(fn () => app(LinkCustomerAccount::class)->link($actor, $customer, $unlink ? null : $next->id, true))
            ->toThrow(RuntimeException::class, 'Audit unavailable');
    } finally {
        AuditLog::flushEventListeners();
    }
    expect($customer->fresh()->user_id)->toBe($old->id);
    $this->assertDatabaseCount('audit_logs', 0);
})->with([false, true]);

it('denies the linking panel to customers mechanics and guests', function (?Role $role) {
    $customer = Customer::factory()->create();
    if ($role) {
        $this->actingAs(User::factory()->create(['role' => $role]));
    }
    Livewire::test(CustomerAccount::class, ['customerId' => $customer->id])->assertForbidden();
})->with([Role::Customer, Role::Mechanic, null]);

it('rechecks actor authority on hydrated linking requests', function () {
    $actor = User::factory()->create(['role' => Role::Admin]);
    $customer = Customer::factory()->create();
    $component = Livewire::actingAs($actor)->test(CustomerAccount::class, ['customerId' => $customer->id]);
    User::whereKey($actor->id)->update(['role' => 'customer']);
    $component->call('save')->assertForbidden();
    expect($customer->fresh()->user_id)->toBeNull();
});

it('locks the customer target against client tampering', function () {
    $actor = User::factory()->create(['role' => Role::Admin]);
    $customer = Customer::factory()->create();
    $other = Customer::factory()->create();
    expect(fn () => Livewire::actingAs($actor)->test(CustomerAccount::class, ['customerId' => $customer->id])
        ->set('customerId', $other->id))->toThrow(CannotUpdateLockedPropertyException::class);
});

it('keeps the selected account visible when the account search excludes it', function () {
    $actor = User::factory()->create(['role' => Role::Admin]);
    $account = User::factory()->create(['email' => 'selected@example.test']);
    $customer = Customer::factory()->create();
    Livewire::actingAs($actor)->test(CustomerAccount::class, ['customerId' => $customer->id])
        ->set('userId', (string) $account->id)->set('search', 'no matching account')
        ->assertSeeHtml('value="'.$account->id.'"')
        ->assertSet('userId', (string) $account->id);
});

it('resets acknowledgement when the selected account changes', function () {
    $actor = User::factory()->create(['role' => Role::Admin]);
    $customer = Customer::factory()->create();
    $account = User::factory()->create();
    Livewire::actingAs($actor)->test(CustomerAccount::class, ['customerId' => $customer->id])
        ->set('ownershipVerified', true)->set('userId', (string) $account->id)
        ->assertSet('ownershipVerified', false);
});

it('renders a trusted linking panel with explicit acknowledgement and confirmations', function () {
    $actor = User::factory()->create(['role' => Role::Admin]);
    $account = User::factory()->create();
    $customer = Customer::factory()->create();
    Livewire::actingAs($actor)->test(CustomerAccount::class, ['customerId' => $customer->id])
        ->assertSet('userId', '')
        ->assertSet('ownershipVerified', false)
        ->assertSee('Akun portal pelanggan')
        ->assertSee($account->email)
        ->assertSee('Tidak dicocokkan otomatis')->assertSeeHtml('wire:confirm=')
        ->set('userId', (string) $account->id)
        ->call('save')->assertHasErrors(['ownershipVerified'])
        ->set('ownershipVerified', true)
        ->call('save')->assertHasNoErrors()
        ->assertSet('ownershipVerified', false)
        ->assertSet('showForm', false)->assertSet('customerId', null)
        ->assertDispatched('customer-account-saved');
    expect($customer->fresh()->user_id)->toBe($account->id);
});

it('bounds account options and requires refining the search rather than silently hiding accounts', function () {
    $actor = User::factory()->create(['role' => Role::Admin]);
    User::factory()->count(51)->create();
    $last = User::factory()->create(['name' => 'Zzz Akun Terakhir', 'email' => 'specific@example.test']);
    $customer = Customer::factory()->create();
    Livewire::actingAs($actor)->test(CustomerAccount::class, ['customerId' => $customer->id])
        ->assertSee('Hasil dibatasi 50 akun. Persempit pencarian nama atau email.')
        ->assertDontSee($last->email)
        ->set('search', 'specific@example.test')
        ->assertSee($last->email)
        ->assertDontSee('Hasil dibatasi 50 akun.')
        ->set('search', '%')
        ->assertSee('Tidak ada akun yang cocok.');
});

it('converts a database unique race to a field error while preserving the old owner', function () {
    $actor = User::factory()->create(['role' => Role::Admin]);
    $old = User::factory()->create();
    $new = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $old->id]);
    Customer::updating(fn () => throw new UniqueConstraintViolationException('sqlite', 'update customers', [], new RuntimeException('Unique account race')));
    try {
        try {
            app(LinkCustomerAccount::class)->link($actor, $customer, $new->id, true);
            $this->fail('Race accepted');
        } catch (ValidationException $exception) {
            expect($exception->errors())->toHaveKey('userId');
        }
    } finally {
        Customer::flushEventListeners();
    }
    expect($customer->fresh()->user_id)->toBe($old->id);
    $this->assertDatabaseCount('audit_logs', 0);
});

it('rejects unauthorized or stale actors before any mutation', function (string $state) {
    $actor = User::factory()->create(['role' => Role::Owner]);
    if ($state === 'archived') {
        $actor->delete();
    } else {
        User::whereKey($actor->id)->update(['role' => $state]);
    }
    $customer = Customer::factory()->create();
    $account = User::factory()->create();
    expect(fn () => app(LinkCustomerAccount::class)->link($actor, $customer, $account->id, true))
        ->toThrow(AuthorizationException::class);
    expect($customer->fresh()->user_id)->toBeNull();
    $this->assertDatabaseCount('audit_logs', 0);
})->with(['customer', 'mechanic', 'archived']);

it('accepts only active verified customer accounts', function (string $state) {
    $actor = User::factory()->create(['role' => Role::Admin]);
    $account = User::factory()->create();
    match ($state) {
        'unverified' => $account->forceFill(['email_verified_at' => null])->save(),
        'archived' => $account->delete(),
        'missing' => $account->forceDelete(),
        default => $account->forceFill(['role' => $state])->save(),
    };
    $customer = Customer::factory()->create();
    try {
        app(LinkCustomerAccount::class)->link($actor, $customer, $account->id, true);
        $this->fail('Invalid account accepted');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('userId');
    }
    expect($customer->fresh()->user_id)->toBeNull();
    $this->assertDatabaseCount('audit_logs', 0);
})->with(['unverified', 'archived', 'missing', 'mechanic', 'admin', 'owner']);

it('rejects an account already owned by another customer including archived masters', function (bool $archived) {
    $actor = User::factory()->create(['role' => Role::Admin]);
    $account = User::factory()->create();
    $other = Customer::factory()->create(['user_id' => $account->id]);
    if ($archived) {
        $other->delete();
    }
    $old = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $old->id]);
    try {
        app(LinkCustomerAccount::class)->link($actor, $customer, $account->id, true);
        $this->fail('Duplicate account accepted');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('userId');
    }
    expect($customer->fresh()->user_id)->toBe($old->id);
    $this->assertDatabaseCount('audit_logs', 0);
})->with([false, true]);

it('requires offline ownership acknowledgement for linking relinking and unlinking', function (string $operation) {
    $actor = User::factory()->create(['role' => Role::Owner]);
    $old = User::factory()->create();
    $new = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $operation === 'link' ? null : $old->id]);
    $previous = $customer->user_id;
    try {
        app(LinkCustomerAccount::class)->link($actor, $customer, $operation === 'unlink' ? null : $new->id, false);
        $this->fail('Acknowledgement required');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('ownershipVerified');
    }
    expect($customer->fresh()->user_id)->toBe($previous);
    $this->assertDatabaseCount('audit_logs', 0);
})->with(['link', 'relink', 'unlink']);

it('links an explicitly selected verified customer account with an ID only audit', function () {
    $actor = User::factory()->create(['role' => Role::Admin]);
    $customer = Customer::factory()->create();
    $account = User::factory()->create();
    $original = $customer->only(['name', 'phone', 'email', 'address', 'notes']);

    $linked = app(LinkCustomerAccount::class)->link($actor, $customer, $account->id, true);

    expect($linked->user_id)->toBe($account->id)
        ->and($customer->fresh()->user_id)->toBe($account->id)
        ->and($customer->fresh()->only(array_keys($original)))->toBe($original);
    $audit = AuditLog::where('action', 'customer.account_linked')->sole();
    expect($audit->actor_id)->toBe($actor->id)
        ->and($audit->entity_type)->toBe(Customer::class)
        ->and($audit->entity_id)->toBe($customer->id)
        ->and($audit->context)->toBe(['previous_user_id' => null, 'new_user_id' => $account->id]);
});
