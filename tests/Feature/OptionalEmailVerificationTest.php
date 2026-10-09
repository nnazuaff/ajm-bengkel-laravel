<?php

use App\Actions\LinkCustomerAccount;
use App\Enums\Role;
use App\Livewire\BookingRequest;
use App\Livewire\CustomerAccount;
use App\Livewire\CustomerBooking;
use App\Livewire\CustomerPortal;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Receipt;
use App\Models\ServiceDocumentation;
use App\Models\ServiceOrder;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\Process\Process;

it('uses environment defaults and explicit verification overrides', function (string $environment, string|false $override, bool $required) {
    $process = new Process([
        PHP_BINARY, '-r', 'require "vendor/autoload.php"; $app = new Illuminate\Foundation\Application(getcwd()); $app->instance("config", new Illuminate\Config\Repository); $config = require "config/fortify.php"; var_export($config["require_email_verification"]);',
    ], base_path(), ['APP_ENV' => $environment, 'AUTH_REQUIRE_EMAIL_VERIFICATION' => $override]);
    $process->mustRun();
    expect(trim($process->getOutput()))->toBe($required ? 'true' : 'false');
})->with([
    ['local', false, false], ['testing', false, true], ['production', false, true],
    ['local', 'true', true], ['production', 'false', false],
]);

it('defaults email verification to required in testing', function () {
    expect(config('fortify.require_email_verification'))->toBeTrue();
});

it('suppresses registration verification mail without fabricating verification when disabled', function () {
    config(['fortify.require_email_verification' => false]);
    Notification::fake();

    $this->post(route('register.store'), [
        'name' => 'Optional verification', 'phone' => '081234567890', 'email' => 'optional@example.com',
        'password' => 'password', 'password_confirmation' => 'password',
    ])->assertSessionHasNoErrors()->assertRedirect('/dashboard');

    $user = User::where('email', 'optional@example.com')->firstOrFail();
    expect($user->email_verified_at)->toBeNull()->and($user->hasVerifiedEmail())->toBeFalse();
    expect(Customer::count())->toBe(1);
    Notification::assertNothingSent();
    $this->get(route('booking.mine'))->assertOk();
    $this->get(route('portal'))->assertOk();
    $this->get(route('appearance.edit'))->assertOk();
    $this->get(route('customers.index'))->assertForbidden();
});

it('serves only trusted owned receipts and photos to unverified customers when disabled', function () {
    config(['fortify.require_email_verification' => false]);
    Storage::fake('local');
    $user = User::factory()->unverified()->create();
    $customer = Customer::factory()->create(['user_id' => $user->id]);
    $vehicle = Vehicle::factory()->create(['customer_id' => $customer->id]);
    $order = ServiceOrder::factory()->create(['customer_id' => $customer->id, 'vehicle_id' => $vehicle->id]);
    $receipt = Receipt::factory()->create(['customer_id' => $customer->id, 'service_order_id' => $order->id, 'status' => 'final']);
    $photo = ServiceDocumentation::factory()->create(['service_order_id' => $order->id, 'path' => 'service-documentation/'.$order->id.'/'.str_repeat('a', 32).'.png']);
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aY9sAAAAASUVORK5CYII=');
    Storage::disk('local')->put($photo->path, $png);

    Livewire::actingAs($user)->test(CustomerPortal::class)->call('selectOrder', $order->id)->assertSee($vehicle->license_plate);
    $this->get(route('customer.receipts.show', $receipt))->assertOk();
    $this->get(route('customer.receipts.image', $receipt))->assertOk()->assertHeader('Content-Type', 'image/png');
    $this->get(route('customer.documentation.show', $photo))->assertOk()->assertStreamedContent($png);
    $this->get(route('customer.receipts.show', Receipt::factory()->create(['status' => 'final'])))->assertNotFound();
    $this->get(route('customer.documentation.show', ServiceDocumentation::factory()->create()))->assertNotFound();
    $this->get(route('receipts.show', $receipt))->assertForbidden();
    $this->get(route('documentation.show', $photo))->assertForbidden();
    $customer->delete();
    $this->get(route('customer.receipts.show', $receipt))->assertNotFound();
    $this->get(route('customer.documentation.show', $photo))->assertNotFound();
});

it('allows explicit staff linking of unverified accounts when disabled without relaxing ownership confirmation', function () {
    config(['fortify.require_email_verification' => false]);
    $actor = User::factory()->unverified()->create(['role' => Role::Admin]);
    $user = User::factory()->unverified()->create();
    $customer = Customer::factory()->create(['email' => $user->email]);
    $archived = User::factory()->unverified()->create();
    $archived->delete();
    $mechanic = User::factory()->unverified()->create(['role' => Role::Mechanic]);
    $owned = User::factory()->unverified()->create();
    Customer::factory()->create(['user_id' => $owned->id])->delete();

    $panel = Livewire::actingAs($actor)->test(CustomerAccount::class, ['customerId' => $customer->id])
        ->assertSee($user->email)->assertDontSee($archived->email)->assertDontSee($mechanic->email)
        ->assertDontSee($owned->email)->assertSee('Verifikasi email tidak')->assertSee('diwajibkan; verifikasi identitas dan kepemilikan tetap wajib.')
        ->assertDontSee('Akun pelanggan terverifikasi')->assertSet('userId', '')
        ->set('userId', (string) $user->id)->call('save')->assertHasErrors(['ownershipVerified']);
    expect($customer->fresh()->user_id)->toBeNull();
    $panel->set('ownershipVerified', true)->call('save')->assertHasNoErrors();
    expect($customer->fresh()->user_id)->toBe($user->id)->and($user->fresh()->email_verified_at)->toBeNull();
    $this->actingAs($user)->get(route('portal'))->assertOk();
});

it('hides verification prompts and suppresses profile resend when disabled', function () {
    config(['fortify.require_email_verification' => false]);
    Notification::fake();
    $user = User::factory()->unverified()->create();
    Livewire::actingAs($user)->test('pages::settings.profile')
        ->assertDontSee('Your email address is unverified.')
        ->assertSet('showDeleteUser', true)
        ->call('resendVerificationNotification')->assertSessionMissing('status');
    Notification::assertNothingSent();
    expect($user->fresh()->email_verified_at)->toBeNull();
});

it('persists verification middleware for real Livewire updates', function () {
    config(['fortify.require_email_verification' => false]);
    $user = User::factory()->unverified()->create();
    $html = $this->actingAs($user)->get(route('customers.index'))->assertForbidden();
    $user->forceFill(['role' => Role::Admin])->save();
    $html = $this->get(route('customers.index'))->assertOk()->getContent();
    preg_match('/wire:snapshot="([^"]+)"/', $html, $matches);
    $snapshot = html_entity_decode($matches[1], ENT_QUOTES);
    $payload = ['components' => [['snapshot' => $snapshot, 'updates' => [], 'calls' => [['path' => '', 'method' => '$refresh', 'params' => []]]]]];
    $this->postJson(Livewire::getUpdateUri(), $payload, ['X-Livewire' => ''])->assertOk();
    Livewire::flushState();
    config(['fortify.require_email_verification' => true]);
    $this->postJson(Livewire::getUpdateUri(), $payload, ['X-Livewire' => ''])->assertForbidden();
});

it('reuses offline masters without claiming history during unverified booking', function () {
    config(['fortify.require_email_verification' => false]);
    $user = User::factory()->unverified()->create();
    $customer = Customer::factory()->create(['email' => $user->email, 'phone' => '6281234567890']);
    $vehicle = Vehicle::factory()->create(['customer_id' => $customer->id]);
    Livewire::actingAs($user)->test(BookingRequest::class)->call('openForm')->set('form', [
        'name' => 'Pemesan', 'phone' => $customer->phone, 'email' => $user->email,
        'license_plate' => $vehicle->license_plate, 'brand' => 'Honda', 'model' => 'Vario', 'year' => '',
        'current_mileage' => 1000, 'booking_date' => now()->addDay()->toDateString(),
        'arrival_time' => '10:00', 'service_type' => 'Servis', 'complaint' => 'Bunyi rem', 'notes' => '',
    ])->call('submit')->assertHasNoErrors()->assertDispatched('booking-created');
    expect(Booking::sole()->submitted_by)->toBe($user->id)
        ->and(Customer::count())->toBe(1)->and(Vehicle::count())->toBe(1)
        ->and($customer->fresh()->user_id)->toBeNull();
    Livewire::test(CustomerPortal::class)->assertSee('Histori lama menunggu verifikasi identitas')->assertDontSee($vehicle->license_plate);
});

it('does not weaken active roles or ownership on subsequent component requests when disabled', function () {
    config(['fortify.require_email_verification' => false]);
    $user = User::factory()->unverified()->create();
    $portal = Livewire::actingAs($user)->test(CustomerPortal::class);
    $booking = Livewire::test(CustomerBooking::class);
    $user->delete();
    $portal->call('$refresh')->assertForbidden();
    $booking->call('createBooking')->assertForbidden();
    foreach ([Role::Admin, Role::Owner, Role::Mechanic] as $role) {
        Livewire::actingAs(User::factory()->unverified()->create(['role' => $role]))->test(CustomerPortal::class)->assertForbidden();
        Livewire::test(CustomerBooking::class)->assertForbidden();
    }
});

it('rejects staff linking of archived noncustomer or already-owned unverified accounts even when disabled', function (string $state) {
    config(['fortify.require_email_verification' => false]);
    $actor = User::factory()->create(['role' => Role::Admin]);
    $user = User::factory()->unverified()->create();
    match ($state) {
        'archived' => $user->delete(),
        'owned' => Customer::factory()->create(['user_id' => $user->id])->delete(),
        default => $user->forceFill(['role' => $state])->save(),
    };
    $customer = Customer::factory()->create();
    expect(fn () => app(LinkCustomerAccount::class)->link($actor, $customer, $user->id, true))
        ->toThrow(ValidationException::class);
    expect($customer->fresh()->user_id)->toBeNull();
})->with(['archived', 'owned', 'mechanic', 'admin', 'owner']);

it('keeps native email verification routes and notifications when required', function () {
    config(['fortify.require_email_verification' => true]);
    Notification::fake();
    $user = User::factory()->unverified()->create();
    event(new Registered($user));
    Notification::assertSentTo($user, VerifyEmail::class);
    $this->actingAs($user)->get(route('portal'))->assertRedirect(route('verification.notice'));
    $this->get(route('booking.mine'))->assertRedirect(route('verification.notice'));
    $this->get(route('appearance.edit'))->assertRedirect(route('verification.notice'));
});
