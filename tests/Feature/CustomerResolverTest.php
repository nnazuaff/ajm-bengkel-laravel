<?php

use App\Actions\CustomerResolver;
use App\Actions\VehicleResolver;
use App\Models\Customer;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

it('reuses normalized contacts without changing trusted details', function () {
    $customer = Customer::factory()->create(['phone' => '6281234567890', 'name' => 'Nama asli']);
    $resolved = app(CustomerResolver::class)->resolve(['name' => 'Nama input', 'phone' => '+62 812-3456-7890']);
    expect($resolved->id)->toBe($customer->id)->and($resolved->name)->toBe('Nama asli');
});

it('links only new contacts and never claims old history by phone', function () {
    $user = User::factory()->create(['phone' => '6281234567890']);
    $customer = app(CustomerResolver::class)->forUser($user, ['name' => $user->name]);
    expect($customer->user_id)->toBe($user->id);
    $other = User::factory()->create(['phone' => '6281234567890']);
    expect(app(CustomerResolver::class)->forUser($other, ['name' => 'Other'])->user_id)->toBe($user->id);
    expect(Customer::count())->toBe(1);
});

it('registration links new records but leaves matched history private', function () {
    $offline = Customer::factory()->create(['phone' => '6281234567890']);
    $this->post(route('register.store'), ['name' => 'New account', 'email' => 'new@example.test', 'phone' => '081234567890', 'password' => 'password', 'password_confirmation' => 'password'])->assertSessionHasNoErrors();
    expect(Customer::count())->toBe(1)->and($offline->fresh()->user_id)->toBeNull();
    auth()->logout();
    $this->post(route('register.store'), ['name' => 'Fresh account', 'email' => 'fresh@example.test', 'phone' => '081234567899', 'password' => 'password', 'password_confirmation' => 'password'])->assertSessionHasNoErrors();
    expect(Customer::where('phone', '6281234567899')->sole()->user_id)->toBe(User::where('email', 'fresh@example.test')->sole()->id);
});

it('audits normalization conflicts before changing any master', function () {
    $a = Customer::factory()->create(['phone' => '6281234567890']);
    $b = Customer::factory()->create(['phone' => '6281999999999']);
    DB::table('customers')->where('id', $b->id)->update(['phone' => '0812 3456 7890']);
    $migration = require database_path('migrations/2026_10_08_000022_normalize_workshop_contacts.php');
    expect(fn () => $migration->up())->toThrow(RuntimeException::class);
    expect($b->fresh()->getRawOriginal('phone'))->toBe('0812 3456 7890');
});

it('reuses own vehicle and rejects another owners plate', function () {
    $customer = Customer::factory()->create();
    $vehicle = Vehicle::factory()->create(['customer_id' => $customer->id, 'license_plate' => 'D1234ABC']);
    $input = ['license_plate' => 'd 1234 abc', 'brand' => 'Changed', 'model' => 'Changed'];
    expect(app(VehicleResolver::class)->resolve($customer, $input)->id)->toBe($vehicle->id);
    expect(fn () => app(VehicleResolver::class)->resolve(Customer::factory()->create(), $input))->toThrow(ValidationException::class);
});
