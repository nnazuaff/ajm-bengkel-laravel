<?php

use App\Models\User;

it('provides one-click theme controls on public and authentication navigation', function (string $path) {
    $this->get($path)->assertOk()
        ->assertSee('data-theme-toggle', false)
        ->assertSee('Ganti ke tema siang')
        ->assertSee('data-theme-sun', false)
        ->assertSee('data-theme-moon', false);
})->with(['/', '/booking/guest', '/check-in', '/login', '/register', '/forgot-password']);

it('keeps theme controls in customer and staff navigation', function (string $role, string $path) {
    $this->actingAs(User::factory()->create(['role' => $role]))->get($path)
        ->assertOk()->assertSee('data-theme-toggle', false);
})->with([['customer', '/portal'], ['customer', '/booking'], ['customer', '/settings/profile'], ['owner', '/customers'], ['mechanic', '/services']]);

it('limits appearance settings to day and night', function () {
    $this->actingAs(User::factory()->create())->get(route('appearance.edit'))
        ->assertOk()->assertSee('Siang')->assertSee('Malam')->assertDontSee('value="system"', false);
});
