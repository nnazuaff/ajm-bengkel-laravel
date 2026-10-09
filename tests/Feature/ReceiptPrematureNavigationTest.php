<?php

use App\Models\Receipt;
use App\Models\ServiceOrder;
use App\Models\User;

it('redirects unfinished service receipt requests to a clear service notice', function () {
    $user = User::factory()->create(['role' => 'admin']);
    $order = ServiceOrder::factory()->create(['status' => 'waiting']);
    foreach (['receipts.create', 'receipts.index'] as $route) {
        $this->actingAs($user)->get(route($route, ['service_order_id' => $order->id]))
            ->assertRedirect(route('services.detail', $order->id))->assertSessionHas('status', 'Bon dapat dibuat setelah servis selesai.');
    }
    $this->get(route('services.detail', $order->id))->assertOk()->assertSee('Bon dapat dibuat setelah servis selesai.')->assertDontSee('>Bon servis<', false);
    expect(Receipt::count())->toBe(0);
});
