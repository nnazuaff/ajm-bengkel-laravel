<?php

use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Laravel\Fortify\Features;

test('forgot password shows a development notice instead of an email form', function () {
    $this->get(route('password.request'))->assertOk()
        ->assertSee('Fitur ini masih dalam pengembangan.')
        ->assertSee('Kembali ke login')
        ->assertDontSee('name="email"', false)
        ->assertDontSee('email-password-reset-link-button');
    $this->get(route('login'))->assertOk()->assertSee(route('password.request'), false);
    expect(Features::enabled(Features::resetPasswords()))->toBeFalse();
});

test('direct forgot password requests cannot send email or create reset tokens', function () {
    Notification::fake();
    $user = User::factory()->create();
    $this->post('/forgot-password', ['email' => $user->email])->assertStatus(405);
    Notification::assertNothingSent();
    $this->assertDatabaseCount('password_reset_tokens', 0);
});

test('old reset links redirect to the development notice and cannot change passwords', function () {
    Notification::fake();
    $user = User::factory()->create();
    $hash = $user->password;
    $token = Password::broker()->createToken($user);
    $this->get('/reset-password/'.$token.'?email='.urlencode($user->email))
        ->assertRedirect('/forgot-password');
    $this->post('/reset-password', [
        'token' => $token, 'email' => $user->email,
        'password' => 'Changed-password-123!', 'password_confirmation' => 'Changed-password-123!',
    ])->assertNotFound();
    expect($user->fresh()->password)->toBe($hash);
    Notification::assertNothingSent();
});
