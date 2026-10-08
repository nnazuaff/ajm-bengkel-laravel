<?php

use App\Models\User;

it('creates an owner through the trusted console without storing a plaintext password', function () {
    $this->artisan('workshop:create-user', [
        'email' => 'owner@example.test',
        '--name' => 'Pemilik AJM',
        '--role' => 'owner',
    ])->expectsQuestion('Kata sandi (minimal 12 karakter)', 'Workshop!Strong2026')
        ->expectsQuestion('Ulangi kata sandi', 'Workshop!Strong2026')
        ->assertSuccessful();

    $user = User::where('email', 'owner@example.test')->firstOrFail();
    expect($user->getRawOriginal('role'))->toBe('owner')
        ->and($user->password)->not->toBe('Workshop!Strong2026')
        ->and($user->hasVerifiedEmail())->toBeTrue();
});

it('refuses duplicate staff emails instead of silently promoting existing accounts', function () {
    User::factory()->create(['email' => 'customer@example.test']);

    $this->artisan('workshop:create-user', [
        'email' => 'customer@example.test',
        '--name' => 'Pemilik AJM',
        '--role' => 'owner',
    ])->assertFailed();

    expect(User::where('email', 'customer@example.test')->firstOrFail()->getRawOriginal('role'))
        ->toBe('customer');
});
