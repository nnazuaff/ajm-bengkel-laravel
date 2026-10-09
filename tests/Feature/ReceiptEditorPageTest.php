<?php

use App\Actions\ManageReceipt;
use App\Enums\Role;
use App\Livewire\ReceiptEditor;
use App\Models\InventoryItem;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\ServiceOrder;
use App\Models\User;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

it('keeps receipt editing on its own page after saving', function () {
    $actor = User::factory()->create(['role' => 'admin']);
    $component = Livewire::actingAs($actor)->test(ReceiptEditor::class)
        ->assertSee('Simpan draf')->assertDontSee('Daftar bon')
        ->set('items', [['type' => 'custom', 'description' => 'Biaya cuci', 'quantity' => 2, 'unit_price' => '15000.25', 'inventory_item_id' => null]])
        ->set('discount', '0.50')->call('save')->assertHasNoErrors()
        ->assertRedirect(route('receipts.edit', Receipt::sole()->id));
    $component = Livewire::test(ReceiptEditor::class, ['receipt' => Receipt::sole()->id])
        ->assertSee('Finalisasi bon')->assertDontSee('Daftar bon')
        ->call('finalize')->assertHasNoErrors()
        ->set('amount', '30000.00')->set('method', 'cash')->call('pay')->assertHasNoErrors()->assertSee('Lunas');
    expect(Receipt::first()->grand_total)->toBe('30000.00')->and(Receipt::first()->payments()->count())->toBe(1);
});

it('denies forged editor mutations after role changes', function () {
    $actor = User::factory()->create(['role' => 'admin']);
    $component = Livewire::actingAs($actor)->test(ReceiptEditor::class);
    $actor->role = Role::Mechanic;
    $actor->save();
    $component->call('save')->assertForbidden();
});

it('protects editor routes by role', function (string $role, int $status) {
    $receipt = Receipt::factory()->create();
    $this->actingAs(User::factory()->create(['role' => $role]))
        ->get(route('receipts.create'))->assertStatus($status);
    $this->get(route('receipts.edit', $receipt))->assertStatus($status);
})->with([['owner', 200], ['admin', 200], ['mechanic', 403], ['customer', 403]]);

it('protects editor routes from guests', function () {
    $receipt = Receipt::factory()->create();
    $this->get(route('receipts.create'))->assertRedirect(route('login'));
    $this->get(route('receipts.edit', $receipt))->assertRedirect(route('login'));
});

it('loads the routed receipt without rendering the receipt list', function () {
    $receipt = Receipt::factory()->create();
    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->get(route('receipts.edit', $receipt))->assertOk()
        ->assertSee($receipt->receipt_number)->assertSee('Kembali ke daftar bon')->assertDontSee('Daftar bon');
});

it('prefills completed service intake without mutating on GET', function () {
    $order = ServiceOrder::factory()->create(['status' => 'completed']);
    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->get(route('receipts.create', ['service_order_id' => $order->id]))->assertOk()->assertSee($order->service_number);
    expect(Receipt::count())->toBe(0);
});

it('opens the existing service receipt instead of a second draft', function () {
    $order = ServiceOrder::factory()->create(['status' => 'completed']);
    $receipt = Receipt::factory()->create(['service_order_id' => $order->id]);
    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->get(route('receipts.create', ['service_order_id' => $order->id]))->assertRedirect(route('receipts.edit', $receipt));
    expect(Receipt::count())->toBe(1);
});

it('rejects missing and unfinished service intake', function () {
    $order = ServiceOrder::factory()->create(['status' => 'waiting']);
    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->get(route('receipts.create', ['service_order_id' => $order->id]))->assertRedirect(route('services.detail', $order->id));
    $this->get(route('receipts.create', ['service_order_id' => 999999]))->assertNotFound();
    $this->get(route('receipts.edit', ['receipt' => 999999]))->assertNotFound();
    expect(Receipt::count())->toBe(0);
});

it('locks the editor record identifier', function () {
    $actor = User::factory()->create(['role' => 'admin']);
    $receipt = Receipt::factory()->create();
    Livewire::actingAs($actor)->test(ReceiptEditor::class, ['receipt' => $receipt->id])
        ->set('editingId', 999999);
})->throws(CannotUpdateLockedPropertyException::class);

it('validates malformed rows without rendering failures or partial drafts', function (array $rows) {
    $actor = User::factory()->create(['role' => 'admin']);
    Livewire::actingAs($actor)->test(ReceiptEditor::class)->set('items', $rows)->call('save')->assertHasErrors();
    expect(Receipt::count())->toBe(0);
})->with([
    'missing fields' => [[[]]],
    'scalar row' => [['invalid']],
    'null row' => [[null]],
    'unknown type' => [[['type' => 'forged']]],
    'zero quantity' => [[['type' => 'custom', 'description' => 'Cuci', 'quantity' => 0, 'unit_price' => '100.00']]],
    'negative money' => [[['type' => 'custom', 'description' => 'Cuci', 'quantity' => 1, 'unit_price' => '-1']]],
]);

it('refreshes product pricing on finalize and restores stock on owner void', function () {
    $actor = User::factory()->create(['role' => 'owner']);
    $product = InventoryItem::factory()->create(['selling_price' => '599.00', 'current_stock' => 5]);
    $component = Livewire::actingAs($actor)->test(ReceiptEditor::class)
        ->call('addItem', 'product')->set('items.0.inventory_item_id', $product->id)
        ->set('items.0.quantity', 2)->set('items.0.unit_price', '1.00')
        ->call('save')->assertHasNoErrors()->assertRedirect(route('receipts.edit', Receipt::sole()->id));
    $component = Livewire::test(ReceiptEditor::class, ['receipt' => Receipt::sole()->id])->assertSee('1198.00');
    $product->update(['selling_price' => '600.00']);
    $component->call('finalize')->assertHasNoErrors()->assertSee('1200.00')
        ->assertDontSee('Simpan draf')->assertSee('Unduh PNG')
        ->set('amount', '600.00')->call('pay')->assertHasNoErrors()->assertSee('Sebagian');
    expect($product->refresh()->current_stock)->toBe(3);
    $receipt = Receipt::first();
    $component->set('reason', 'Koreksi transaksi')->call('reversePayment', $receipt->payments()->first()->id)
        ->assertHasNoErrors()->assertSee('Dibalik: Koreksi transaksi')
        ->set('reason', 'Barang dikembalikan')->call('void')->assertHasNoErrors()->assertSee('Dibatalkan');
    expect($product->refresh()->current_stock)->toBe(5)
        ->and($receipt->refresh()->void_reason)->toBe('Barang dikembalikan');
});

it('requires correction reasons and prevents reversing another receipts payment', function () {
    $actor = User::factory()->create(['role' => 'owner']);
    $receipt = Receipt::factory()->create(['status' => 'final', 'grand_total' => '100.00']);
    $payment = app(ManageReceipt::class)->pay($actor, $receipt, ['amount' => '50.00', 'method' => 'cash', 'paid_at' => now()->format('Y-m-d H:i:s')]);
    $component = Livewire::actingAs($actor)->test(ReceiptEditor::class, ['receipt' => $receipt->id])
        ->call('reversePayment', $payment->id)->assertHasErrors('reason')
        ->call('void')->assertHasErrors('reason');
    expect($payment->refresh()->reversed_at)->toBeNull()->and($receipt->refresh()->status->value)->toBe('final');
    $foreign = Payment::factory()->create();
    $component->set('reason', 'Koreksi')->call('reversePayment', $foreign->id)->assertForbidden();
});

it('keeps corrections owner only', function () {
    $actor = User::factory()->create(['role' => 'admin']);
    $receipt = Receipt::factory()->create(['status' => 'final', 'grand_total' => '100.00']);
    $payment = Payment::factory()->create(['receipt_id' => $receipt->id]);
    Livewire::actingAs($actor)->test(ReceiptEditor::class, ['receipt' => $receipt->id])
        ->assertDontSee('Balik pembayaran')->assertDontSee('Batalkan bon')
        ->set('reason', 'Koreksi')->call('void')->assertForbidden();
    Livewire::actingAs($actor)->test(ReceiptEditor::class, ['receipt' => $receipt->id])
        ->set('reason', 'Koreksi')->call('reversePayment', $payment->id)->assertForbidden();
});

it('rejects forged draft edits on final receipts', function (string $action) {
    $actor = User::factory()->create(['role' => 'admin']);
    $receipt = Receipt::factory()->create(['status' => 'final']);
    Livewire::actingAs($actor)->test(ReceiptEditor::class, ['receipt' => $receipt->id])
        ->call($action, ...($action === 'removeItem' ? [0] : []))->assertHasErrors('receipt');
    expect($receipt->refresh()->status->value)->toBe('final');
})->with(['save', 'addItem', 'removeItem']);
