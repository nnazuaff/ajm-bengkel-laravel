<?php

use App\Actions\ManageCheckInCode;
use App\Actions\ProcessCheckIn;
use App\Livewire\PublicCheckIn;
use App\Models\AuditLog;
use App\Models\CheckIn;
use App\Models\Customer;
use App\Models\User;
use Livewire\Livewire;

it('never replaces or logs in a preexisting account using public contact fields', function () {
    $owner = User::factory()->create(['role' => 'owner']);
    $existing = User::factory()->create(['phone' => '6281234567890']);
    $customer = Customer::factory()->create(['user_id' => $existing->id, 'phone' => $existing->phone]);
    $original = $existing->getRawOriginal('password');
    $code = app(ManageCheckInCode::class)->generate($owner);
    $panel = Livewire::test(PublicCheckIn::class)->set('name', 'Impostor')->set('phone', '081234567890')->set('code', $code->code)->set('email', 'new@example.test')->update(calls: [['method' => 'submit', 'params' => [], 'path' => '']], updates: ['password' => 'safe-password-123', 'password_confirmation' => 'safe-password-123']);
    $panel->assertHasErrors('phone');
    expect(User::count())->toBe(2)->and(CheckIn::count())->toBe(0);
    $this->assertGuest();
    expect($existing->fresh()->getRawOriginal('password'))->toBe($original)->and($customer->fresh()->user_id)->toBe($existing->id);
});

it('keeps offline history inaccessible until reception identity confirmation', function () {
    $owner = User::factory()->create(['role' => 'owner']);
    $customer = Customer::factory()->create(['phone' => '6281234567890']);
    $code = app(ManageCheckInCode::class)->generate($owner);
    $panel = Livewire::test(PublicCheckIn::class)->set('name', 'Guest')->set('phone', '081234567890')->set('code', $code->code)->set('email', 'new@example.test')->update(calls: [['method' => 'submit', 'params' => [], 'path' => '']], updates: ['password' => 'safe-password-123', 'password_confirmation' => 'safe-password-123']);
    expect($customer->fresh()->user_id)->toBeNull();
    app(ProcessCheckIn::class)->process($owner, CheckIn::sole(), ['identity_verified' => true, 'vehicle' => ['license_plate' => 'D1234AA', 'brand' => 'Honda', 'model' => 'Vario'], 'current_mileage' => 1, 'complaint' => 'Check']);
    $panel->call('refreshStatus')->assertRedirect(route('portal'));
    expect($customer->fresh()->user_id)->toBe(auth()->id());
});

it('does not transfer a waiting browser handoff to a second submitter', function () {
    $owner = User::factory()->create(['role' => 'owner']);
    $code = app(ManageCheckInCode::class)->generate($owner);
    Livewire::test(PublicCheckIn::class)->set('name', 'Guest')->set('phone', '081234567890')->set('code', $code->code)->set('email', 'new@example.test')->update(calls: [['method' => 'submit', 'params' => [], 'path' => '']], updates: ['password' => 'safe-password-123', 'password_confirmation' => 'safe-password-123']);
    $hash = CheckIn::sole()->browser_token_hash;
    session()->forget('check_in');
    Livewire::test(PublicCheckIn::class)->set('name', 'Other')->set('phone', '081234567890')->set('code', $code->code)->set('email', 'new@example.test')->update(calls: [['method' => 'submit', 'params' => [], 'path' => '']], updates: ['password' => 'safe-password-123', 'password_confirmation' => 'safe-password-123'])->assertHasErrors();
    expect(CheckIn::count())->toBe(1)->and(CheckIn::sole()->browser_token_hash)->toBe($hash);
    $this->assertGuest();
});

it('rolls identity activation back when service audit is vetoed', function () {
    $owner = User::factory()->create(['role' => 'owner']);
    $code = app(ManageCheckInCode::class)->generate($owner);
    Livewire::test(PublicCheckIn::class)->set('name', 'Guest')->set('phone', '081234567890')->set('code', $code->code)->set('email', 'new@example.test')->update(calls: [['method' => 'submit', 'params' => [], 'path' => '']], updates: ['password' => 'safe-password-123', 'password_confirmation' => 'safe-password-123']);
    AuditLog::creating(fn ($audit) => $audit->action === 'check_in.converted' ? false : null);
    try {
        expect(fn () => app(ProcessCheckIn::class)->process($owner, CheckIn::sole(), ['identity_verified' => true, 'vehicle' => ['license_plate' => 'D1234AA', 'brand' => 'Honda', 'model' => 'Vario'], 'current_mileage' => 1, 'complaint' => 'Check']))->toThrow(RuntimeException::class);
    } finally {
        AuditLog::flushEventListeners();
    }
    expect(User::find(CheckIn::sole()->account_id)->identity_verified_at)->toBeNull();
    $this->assertGuest();
});

it('expires and cancels browser handoffs without login', function () {
    $owner = User::factory()->create(['role' => 'owner']);
    $code = app(ManageCheckInCode::class)->generate($owner);
    $panel = Livewire::test(PublicCheckIn::class)->set('name', 'Guest')->set('phone', '081234567890')->set('code', $code->code)->set('email', 'new@example.test')->update(calls: [['method' => 'submit', 'params' => [], 'path' => '']], updates: ['password' => 'safe-password-123', 'password_confirmation' => 'safe-password-123']);
    app(ProcessCheckIn::class)->cancel($owner, CheckIn::sole());
    $panel->call('refreshStatus')->assertSet('status', 'cancelled')->assertNoRedirect();
    CheckIn::sole()->update(['browser_access_expires_at' => now()->subSecond()]);
    $panel->call('refreshStatus')->assertSet('status', 'expired')->assertNoRedirect();
    $this->assertGuest();
});

it('continues an already authenticated customers own confirmed checkin without replacing the session', function () {
    $owner = User::factory()->create(['role' => 'owner']);
    $user = User::factory()->create(['phone' => '6281234567890']);
    $customer = Customer::factory()->create(['user_id' => $user->id, 'phone' => $user->phone]);
    $code = app(ManageCheckInCode::class)->generate($owner);
    $panel = Livewire::actingAs($user)->test(PublicCheckIn::class)->set('name', 'Customer')->set('phone', $user->phone)->set('code', $code->code)->set('email', 'new@example.test')->update(calls: [['method' => 'submit', 'params' => [], 'path' => '']], updates: ['password' => 'safe-password-123', 'password_confirmation' => 'safe-password-123']);
    app(ProcessCheckIn::class)->process($owner, CheckIn::sole(), ['vehicle' => ['license_plate' => 'D1234AA', 'brand' => 'Honda', 'model' => 'Vario'], 'current_mileage' => 1, 'complaint' => 'Check']);
    $panel->call('refreshStatus')->assertRedirect(route('portal'));
    $this->assertAuthenticatedAs($user);
});

it('allows a passwordless phone customer to open profile without a fabricated email', function () {
    $user = User::factory()->create(['email' => null, 'identity_verified_at' => now()]);
    Livewire::actingAs($user)->test('pages::settings.profile')->assertSet('email', '')->assertDontSee('Your email address is unverified.');
});

it('creates an account and waits for verified reception before logging this browser in', function () {
    $mechanic = User::factory()->create(['role' => 'mechanic']);
    $code = app(ManageCheckInCode::class)->generate($mechanic);
    $panel = Livewire::test(PublicCheckIn::class)->set('name', 'Tamu')->set('phone', '081234567890')->set('code', $code->code)->set('email', 'new@example.test')->update(calls: [['method' => 'submit', 'params' => [], 'path' => '']], updates: ['password' => 'safe-password-123', 'password_confirmation' => 'safe-password-123'])->assertHasNoErrors();
    $customer = Customer::sole();
    expect($customer->user_id)->not->toBeNull();
    $this->assertGuest();
    $panel->call('refreshStatus')->assertNoRedirect();
    $this->get(route('check-in'))->assertSee('Tunggu konfirmasi mekanik');
    $this->assertGuest();
    $input = ['vehicle' => ['license_plate' => 'D1234AA', 'brand' => 'Honda', 'model' => 'Vario'], 'current_mileage' => 10, 'complaint' => 'Cek'];
    expect(CheckIn::sole()->requires_identity_verification)->toBeFalse();
    app(ProcessCheckIn::class)->process($mechanic, CheckIn::sole(), $input);
    $panel->call('refreshStatus')->assertRedirect(route('portal'));
    $this->assertAuthenticatedAs($customer->user);
    $this->get(route('portal'))->assertOk()->assertSee('D1234AA');
    expect(CheckIn::sole()->browser_token_hash)->toBeNull();
});
