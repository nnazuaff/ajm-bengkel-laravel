<?php

use App\Actions\ManageCheckInCode;
use App\Actions\ProcessCheckIn;
use App\Actions\SubmitCheckIn;
use App\Livewire\PublicCheckIn;
use App\Models\CheckIn;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

it('lets logged customers check in with trusted contact and prevents pending duplicates of any age', function () {
    $actor = User::factory()->create(['role' => 'owner']);
    $user = User::factory()->create(['phone' => '6281234567890']);
    $customer = Customer::factory()->create(['user_id' => $user->id, 'phone' => $user->phone]);
    $code = app(ManageCheckInCode::class)->generate($actor);
    $this->actingAs($user)->get(route('portal'))->assertSee('Check-in');
    $first = app(SubmitCheckIn::class)->submit(['code' => $code->code, 'name' => 'Forged', 'phone' => '081234567899'], null, $user);
    expect($first->customer_id)->toBe($customer->id)->and(User::count())->toBe(2)->and(Customer::count())->toBe(1);
    $this->actingAs($user)->get(route('portal'))->assertSee('Check-in masih menunggu');
    Livewire::actingAs($user)->test(PublicCheckIn::class)->assertSet('submitted', true);
    $first->update(['checked_in_at' => now()->subDays(3)]);
    expect(fn () => app(SubmitCheckIn::class)->submit(['code' => $code->code], null, $user))->toThrow(ValidationException::class);
    app(ProcessCheckIn::class)->cancel($actor, $first);
    Livewire::actingAs($user)->test(PublicCheckIn::class)->assertSet('submitted', false);
    $second = app(SubmitCheckIn::class)->submit(['code' => $code->code], null, $user);
    expect($second->id)->not->toBe($first->id);
});

it('requires reception verification for an offline master and never logs into a matched existing email', function () {
    $owner = User::factory()->create(['role' => 'owner']);
    $customer = Customer::factory()->create(['phone' => '6281234567890']);
    $code = app(ManageCheckInCode::class)->generate($owner);
    $panel = Livewire::test(PublicCheckIn::class)->set('code', $code->code)->set('name', 'Guest')->set('phone', $customer->phone)
        ->set('email', 'new@example.test')->update(calls: [['method' => 'submit', 'params' => [], 'path' => '']], updates: ['password' => 'safe-password-123', 'password_confirmation' => 'safe-password-123'])->assertHasNoErrors();
    expect($customer->fresh()->user_id)->toBeNull()->and(CheckIn::sole()->requires_identity_verification)->toBeTrue();
    expect(fn () => app(ProcessCheckIn::class)->process($owner, CheckIn::sole(), ['vehicle' => ['license_plate' => 'D1234AA', 'brand' => 'Honda', 'model' => 'Beat'], 'current_mileage' => 1, 'complaint' => 'Check']))->toThrow(ValidationException::class);
    $panel->call('refreshStatus')->assertNoRedirect();
    $this->assertGuest();
});

it('rejects guest registration without credentials and never returns entered passwords in snapshot', function () {
    $owner = User::factory()->create(['role' => 'owner']);
    $code = app(ManageCheckInCode::class)->generate($owner);
    Livewire::test(PublicCheckIn::class)->set('code', $code->code)->set('name', 'Guest')->set('phone', '081234567890')
        ->update(calls: [['method' => 'submit', 'params' => [], 'path' => '']], updates: ['password' => 'secret-test-only'])->assertHasErrors(['email', 'password'])->assertSet('password', '')->assertSet('password_confirmation', '');
    expect(Customer::count())->toBe(0)->and(CheckIn::count())->toBe(0);
});

it('clears credentials when a different browser attempts an already waiting checkin', function () {
    $owner = User::factory()->create(['role' => 'owner']);
    $code = app(ManageCheckInCode::class)->generate($owner);
    Livewire::test(PublicCheckIn::class)->set('code', $code->code)->set('name', 'First')
        ->set('phone', '081234567890')->set('email', 'first@example.test')
        ->update(calls: [['method' => 'submit', 'params' => [], 'path' => '']], updates: ['password' => 'first-test-password', 'password_confirmation' => 'first-test-password'])->assertHasNoErrors();
    session()->forget('check_in');
    $component = Livewire::test(PublicCheckIn::class)->set('code', $code->code)->set('name', 'Second')
        ->set('phone', '081234567890')->set('email', 'second@example.test')
        ->update(calls: [['method' => 'submit', 'params' => [], 'path' => '']], updates: ['password' => 'second-test-password', 'password_confirmation' => 'second-test-password'])->assertHasErrors('phone')->assertSet('password', '')->assertSet('password_confirmation', '');
    expect($component->html())->not->toContain('second-test-password');
    expect(CheckIn::count())->toBe(1);
});

it('removes the account configuration action from customer list', function () {
    $this->actingAs(User::factory()->create(['role' => 'owner']))->get(route('customers.index'))->assertOk()->assertDontSee('Akses akun');
});

it('registers a reusable email password account and accepts a genuinely new customer without an identity checkbox', function () {
    $actor = User::factory()->create(['role' => 'mechanic']);
    $code = app(ManageCheckInCode::class)->generate($actor);
    $panel = Livewire::test(PublicCheckIn::class)->set('code', $code->code)->set('name', 'Customer')
        ->set('phone', '081234567890')->set('email', 'customer@example.test')
        ->update(calls: [['method' => 'submit', 'params' => [], 'path' => '']], updates: ['password' => 'safe-password-123', 'password_confirmation' => 'safe-password-123'])->assertHasNoErrors();
    $user = User::where('email', 'customer@example.test')->firstOrFail();
    expect(Hash::check('safe-password-123', $user->password))->toBeTrue();
    app(ProcessCheckIn::class)->process($actor, CheckIn::sole(), ['vehicle' => ['license_plate' => 'D1234AA', 'brand' => 'Honda', 'model' => 'Beat'], 'current_mileage' => 1, 'complaint' => 'Check']);
    $panel->call('refreshStatus')->assertRedirect(route('portal'));
    auth()->logout();
    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'safe-password-123'])->assertSessionHasNoErrors();
    $this->assertAuthenticatedAs($user);
    $this->get(route('portal'))->assertOk();
});
