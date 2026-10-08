<?php

use App\Models\Booking;
use App\Models\InventoryItem;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\ServiceOrder;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('staff users can visit the dashboard', function () {
    $user = User::factory()->create(['role' => 'admin']);
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

it('shows real operational counts on the dashboard', function () {
    ServiceOrder::factory()->count(2)->create(['status' => 'waiting']);
    ServiceOrder::factory()->create(['status' => 'in_progress']);
    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->get('/dashboard')->assertOk()
        ->assertViewHas('metrics', fn (array $metrics) => $metrics['waiting'] === 2 && $metrics['in_progress'] === 1);
});

it('shows today bookings only to managers', function () {
    Booking::factory()->create(['booking_date' => today(), 'status' => 'pending']);
    Booking::factory()->create(['booking_date' => today(), 'status' => 'cancelled']);
    Booking::factory()->create(['booking_date' => today()->addDay(), 'status' => 'pending']);
    $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/dashboard')->assertOk()
        ->assertViewHas('metrics', fn (array $metrics) => $metrics['bookings_today'] === 1);
    $this->actingAs(User::factory()->create(['role' => 'mechanic']))->get('/dashboard')->assertOk()
        ->assertViewHas('metrics', fn (array $metrics) => ! array_key_exists('bookings_today', $metrics));
});

it('shows stock risk unpaid bon and actual payment revenue to managers', function () {
    InventoryItem::factory()->create(['current_stock' => 2, 'minimum_stock' => 3]);
    InventoryItem::factory()->create(['current_stock' => 0, 'minimum_stock' => 3]);
    Receipt::factory()->create(['status' => 'final', 'payment_status' => 'unpaid']);
    $receipt = Receipt::factory()->create(['status' => 'paid', 'payment_status' => 'paid']);
    Payment::factory()->create(['receipt_id' => $receipt->id, 'amount' => '12500.25', 'paid_at' => now()]);
    $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/dashboard')->assertOk()
        ->assertViewHas('metrics', fn (array $metrics) => $metrics['low_stock'] === 1 && $metrics['out_of_stock'] === 1 && $metrics['unpaid_receipts'] === 1 && $metrics['revenue_today'] === '12500.25');
});

it('limits mechanic dashboard counts to their assigned orders', function () {
    $mechanic = User::factory()->create(['role' => 'mechanic']);
    ServiceOrder::factory()->create(['status' => 'waiting', 'mechanic_id' => $mechanic->id]);
    ServiceOrder::factory()->create(['status' => 'waiting']);
    $this->actingAs($mechanic)->get('/dashboard')->assertOk()
        ->assertViewHas('metrics', fn (array $metrics) => $metrics['waiting'] === 1);
});
