<?php

use App\Actions\ManageReceipt;
use App\Livewire\Payments;
use App\Livewire\Receipts;
use App\Models\Receipt;
use App\Models\ServiceOrder;
use App\Models\User;
use App\Models\WorkshopSetting;
use Livewire\Livewire;

it('allows admin finance pages and denies other roles including output routes', function (string $role, int $status) {
    $actor = User::factory()->state(['role' => $role])->create();
    $receipt = Receipt::factory()->create(['status' => 'final']);
    $this->actingAs($actor)->get(route('receipts.index'))->assertStatus($status);
    $this->get(route('payments.index'))->assertStatus($status);
    $this->get(route('receipts.show', $receipt))->assertStatus($status);
    $this->get(route('receipts.image', $receipt))->assertStatus($status);
})->with([['owner', 200], ['admin', 200], ['mechanic', 403], ['customer', 403]]);

it('keeps receipt intake off the list page', function () {
    $actor = User::factory()->create(['role' => 'admin']);
    Livewire::actingAs($actor)->test(Receipts::class)
        ->assertDontSee('wire:click="create"', false)
        ->assertDontSee('Simpan draf');
});

it('links to dedicated receipt pages and filters the list', function () {
    $actor = User::factory()->create(['role' => 'admin']);
    $draft = Receipt::factory()->create(['receipt_number' => 'BON-DRAFT-FILTER', 'transaction_date' => '2026-10-01 10:00:00']);
    $final = Receipt::factory()->create(['receipt_number' => 'BON-FINAL-FILTER', 'status' => 'final', 'payment_status' => 'partial', 'transaction_date' => '2026-10-02 10:00:00']);
    Livewire::actingAs($actor)->test(Receipts::class)
        ->assertSee(route('receipts.create'), false)->assertSee(route('receipts.edit', $draft), false)
        ->assertSee('BON-DRAFT-FILTER')->assertSee('BON-FINAL-FILTER')
        ->set('search', 'BON-DRAFT')->assertSee('BON-DRAFT-FILTER')->assertDontSee('BON-FINAL-FILTER')
        ->set('search', '')->set('status', 'final')->assertSee('BON-FINAL-FILTER')->assertDontSee('BON-DRAFT-FILTER')
        ->set('status', '')->set('paymentStatus', 'partial')->assertSee('BON-FINAL-FILTER')->assertDontSee('BON-DRAFT-FILTER')
        ->set('paymentStatus', '')->set('date', '2026-10-01')->assertSee('BON-DRAFT-FILTER')->assertDontSee('BON-FINAL-FILTER');
});

it('redirects legacy receipt links to the editor', function () {
    $actor = User::factory()->create(['role' => 'admin']);
    $order = ServiceOrder::factory()->create(['status' => 'completed']);
    $receipt = Receipt::factory()->create(['service_order_id' => $order->id]);
    $this->actingAs($actor)->get(route('receipts.index', ['receipt_id' => $receipt->id]))
        ->assertRedirect(route('receipts.edit', $receipt));
    $this->get(route('receipts.index', ['service_order_id' => $order->id]))
        ->assertRedirect(route('receipts.edit', $receipt));
    expect(Receipt::count())->toBe(1);
});

it('redirects guests before receipt and image access', function () {
    $receipt = Receipt::factory()->create(['status' => 'final']);
    $this->get(route('receipts.show', $receipt))->assertRedirect(route('login'));
    $this->get(route('receipts.image', $receipt))->assertRedirect(route('login'));
});

it('filters the payment list independently of the editor', function () {
    $actor = User::factory()->create(['role' => 'admin']);
    $receipt = Receipt::factory()->create(['status' => 'final', 'grand_total' => '30000.00']);
    app(ManageReceipt::class)->pay($actor, $receipt, ['amount' => '30000.00', 'method' => 'cash', 'paid_at' => now()->format('Y-m-d H:i:s')]);
    Livewire::actingAs($actor)->test(Payments::class)->assertSee('30000.00')->set('search', 'does not exist')->assertDontSee('30000.00');
});

it('redirects legacy service receipt intake without mutating on GET', function () {
    $actor = User::factory()->create(['role' => 'admin']);
    $order = ServiceOrder::factory()->create(['status' => 'completed']);
    $this->actingAs($actor)->get(route('receipts.index', ['service_order_id' => $order->id]))
        ->assertRedirect(route('receipts.create', ['service_order_id' => $order->id]));
    expect(Receipt::count())->toBe(0);
});

it('downloads protected PNG snapshots without later master changes', function () {
    $actor = User::factory()->state(['role' => 'admin'])->create();
    $receipt = app(ManageReceipt::class)->create($actor, ['items' => [['type' => 'custom', 'description' => 'Cuci motor', 'quantity' => 1, 'unit_price' => '100.00']]]);
    app(ManageReceipt::class)->finalize($actor, $receipt);
    WorkshopSetting::current()->update(['name' => 'New Master Name']);
    $this->actingAs($actor)->get(route('receipts.show', $receipt))->assertOk()->assertSee('AJM Bengkel')->assertDontSee('New Master Name')->assertSee('Cuci motor');
    $response = $this->get(route('receipts.image', $receipt))->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff');
    expect(substr($response->getContent(), 0, 8))->toBe("\x89PNG\r\n\x1a\n");
});

it('does not export draft documents', function () {
    $actor = User::factory()->state(['role' => 'admin'])->create();
    $receipt = Receipt::factory()->create();
    $this->actingAs($actor)->get(route('receipts.show', $receipt))->assertStatus(409);
    $this->get(route('receipts.image', $receipt))->assertStatus(409);
});
