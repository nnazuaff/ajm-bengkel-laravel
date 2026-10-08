<?php

use App\Models\User;

it('renders every usable admin module with protected navigation', function (string $path) {
    $this->actingAs(User::factory()->create(['role' => 'owner']))->get($path)
        ->assertOk()->assertSee('AJM Bengkel')->assertSee('main-content');
})->with(['/dashboard', '/bookings', '/services', '/customers', '/vehicles', '/inventory', '/receipts', '/payments', '/mechanics', '/history', '/audit-log', '/reports', '/workshop-settings']);

it('denies unrelated roles from all general administration pages', function (string $role, string $path) {
    $this->actingAs(User::factory()->create(['role' => $role]))->get($path)->assertForbidden();
})->with(['customer', 'mechanic'])->with(['/inventory', '/receipts', '/payments', '/mechanics', '/history', '/audit-log', '/reports', '/workshop-settings']);

it('keeps workshop identity editing owner-only', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/workshop-settings')->assertForbidden();
});
