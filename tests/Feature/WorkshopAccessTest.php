<?php

use App\Models\User;

it('public registration cannot grant a staff role', function () {
    $this->post(route('register.store'), [
        'name' => 'Rizki Pratama',
        'phone' => '081234567890',
        'email' => 'rizki@example.test',
        'password' => 'password',
        'password_confirmation' => 'password',
        'role' => 'owner',
    ])->assertSessionHasNoErrors();

    expect(User::where('email', 'rizki@example.test')->firstOrFail()->getRawOriginal('role'))
        ->toBe('customer');
});

it('customers do not see an operational dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->get('/dashboard')->assertRedirect('/portal');
});

it('protects staff modules from guests', function (string $path) {
    $this->get($path)->assertRedirect(route('login'));
})->with(['/customers', '/vehicles', '/services', '/bookings']);

it('denies customers access to operational modules', function (string $path) {
    $this->actingAs(User::factory()->create())->get($path)->assertForbidden();
})->with(['/customers', '/vehicles', '/services', '/bookings']);

it('denies mechanics general customer and vehicle management', function (string $path) {
    $this->actingAs(User::factory()->create(['role' => 'mechanic']))->get($path)->assertForbidden();
})->with(['/customers', '/vehicles', '/bookings']);

it('renders integrated staff pages without unresolved navigation', function (string $path) {
    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->get($path)->assertOk()->assertSee('AJM Bengkel')->assertSee('main-content');
})->with(['/dashboard', '/customers', '/vehicles', '/services', '/bookings']);

it('restricts online booking pages to customer accounts', function (string $role) {
    $this->actingAs(User::factory()->create(['role' => $role]))
        ->get('/booking')->assertForbidden();
})->with(['owner', 'admin', 'mechanic']);

it('permits verified customers to open their own booking page', function () {
    $this->actingAs(User::factory()->create())->get('/booking')->assertOk();
});

it('does not mass assign role changes from profile input', function () {
    $user = User::factory()->create();
    $user->fill(['name' => 'Rizki Pratama', 'role' => 'owner'])->save();
    expect($user->fresh()->getRawOriginal('role'))->toBe('customer');
});

it('rejects login for a soft-deleted account', function () {
    $user = User::factory()->create();
    $user->delete();
    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors('email');
    $this->assertGuest();
});
