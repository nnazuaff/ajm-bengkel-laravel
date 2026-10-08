<?php

use App\Actions\ManageReceipt;
use App\Actions\StockLedger;
use App\Actions\UseServicePart;
use App\Enums\PaymentStatus;
use App\Enums\ReceiptStatus;
use App\Models\AuditLog;
use App\Models\InventoryItem;
use App\Models\Receipt;
use App\Models\ServiceJob;
use App\Models\ServiceOrder;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

function financeActor(string $role = 'admin'): User
{
    return User::factory()->state(['role' => $role])->create();
}

function customReceipt(User $actor, string $price = '100.01'): Receipt
{
    return app(ManageReceipt::class)->create($actor, ['items' => [
        ['type' => 'custom', 'description' => 'Jasa tambahan', 'quantity' => 3, 'unit_price' => $price],
    ], 'discount' => '0.02']);
}

it('creates a direct general receipt with exact server totals ignoring submitted totals', function () {
    $receipt = app(ManageReceipt::class)->create(financeActor(), [
        'items' => [['type' => 'custom', 'description' => 'Jasa tambahan', 'quantity' => 3, 'unit_price' => '100.01', 'total' => '1']],
        'discount' => '0.02', 'grand_total' => '0.01', 'status' => 'paid',
    ]);
    expect($receipt->status)->toBe(ReceiptStatus::Draft)
        ->and($receipt->subtotal)->toBe('300.03')->and($receipt->grand_total)->toBe('300.01')
        ->and($receipt->service_order_id)->toBeNull()->and($receipt->customer_id)->toBeNull()
        ->and($receipt->items()->first()->total)->toBe('300.03');
});

it('finalizes custom charges and locks a historical document', function () {
    $actor = financeActor();
    $receipt = customReceipt($actor);
    $final = app(ManageReceipt::class)->finalize($actor, $receipt);
    expect($final->status)->toBe(ReceiptStatus::Final)
        ->and($final->workshop_snapshot['name'])->toBe('AJM Bengkel')
        ->and($final->customer_snapshot['name'])->toBe('Umum');
    expect(fn () => app(ManageReceipt::class)->saveDraft($actor, $final, ['discount' => '0']))
        ->toThrow(ValidationException::class);
});

it('records partial and full payments without overpayment', function () {
    $actor = financeActor();
    $action = app(ManageReceipt::class);
    $receipt = $action->finalize($actor, customReceipt($actor));
    $action->pay($actor, $receipt, ['amount' => '100.01', 'method' => 'cash', 'paid_at' => now()->toDateTimeString()]);
    expect($receipt->refresh()->payment_status)->toBe(PaymentStatus::Partial);
    expect(fn () => $action->pay($actor, $receipt, ['amount' => '200.01', 'method' => 'cash', 'paid_at' => now()->toDateTimeString()]))->toThrow(ValidationException::class);
    $action->pay($actor, $receipt, ['amount' => '200.00', 'method' => 'qris', 'paid_at' => now()->toDateTimeString(), 'reference' => 'QR-123']);
    expect($receipt->refresh()->status)->toBe(ReceiptStatus::Paid)->and($receipt->payment_status)->toBe(PaymentStatus::Paid)->and($receipt->payments()->count())->toBe(2);
});

it('reverses payment once with owner audit and recomputes remaining status', function () {
    $actor = financeActor();
    $owner = financeActor('owner');
    $action = app(ManageReceipt::class);
    $receipt = $action->finalize($actor, customReceipt($actor));
    $payment = $action->pay($actor, $receipt, ['amount' => '300.01', 'method' => 'transfer', 'paid_at' => now()->toDateTimeString()]);
    expect(fn () => $action->reversePayment($actor, $payment, 'Salah input'))->toThrow(AuthorizationException::class);
    $action->reversePayment($owner, $payment, 'Salah input');
    $action->reversePayment($owner, $payment, 'Ulang');
    expect($receipt->refresh()->status)->toBe(ReceiptStatus::Final)->and($receipt->payment_status)->toBe(PaymentStatus::Unpaid)
        ->and($payment->refresh()->reversal_reason)->toBe('Salah input')
        ->and(AuditLog::where('action', 'payment.reversed')->count())->toBe(1);
});

it('voids paid receipts once and reverses bookkeeping payments', function () {
    $owner = financeActor('owner');
    $action = app(ManageReceipt::class);
    $receipt = $action->finalize($owner, customReceipt($owner));
    $payment = $action->pay($owner, $receipt, ['amount' => '300.01', 'method' => 'cash', 'paid_at' => now()->toDateTimeString()]);
    $action->void($owner, $receipt, 'Koreksi transaksi');
    $action->void($owner, $receipt, 'Ulang');
    expect($receipt->refresh()->status)->toBe(ReceiptStatus::Voided)->and($payment->refresh()->reversed_at)->not->toBeNull()
        ->and(AuditLog::where('action', 'receipt.voided')->count())->toBe(1);
    expect(fn () => $action->pay($owner, $receipt, ['amount' => '1', 'method' => 'cash', 'paid_at' => now()->toDateTimeString()]))->toThrow(ValidationException::class);
});

it('uses current product price at finalization and returns archived stock once', function () {
    $actor = financeActor('owner');
    $action = app(ManageReceipt::class);
    $item = InventoryItem::factory()->create(['selling_price' => '100.10']);
    app(StockLedger::class)->move($actor, $item, 5, 'in', 'purchase');
    $receipt = $action->create($actor, ['items' => [['type' => 'product', 'inventory_item_id' => $item->id, 'quantity' => 2, 'unit_price' => '0.01']]]);
    expect($receipt->grand_total)->toBe('200.20');
    $item->update(['selling_price' => '200.25']);
    $action->finalize($actor, $receipt);
    expect($receipt->refresh()->grand_total)->toBe('400.50')->and($item->refresh()->current_stock)->toBe(3);
    $item->update(['selling_price' => '900']);
    $item->delete();
    expect($receipt->refresh()->items()->first()->unit_price)->toBe('200.25');
    $action->void($actor, $receipt, 'Salah barang');
    $action->void($actor, $receipt, 'Ulang');
    expect($item->refresh()->current_stock)->toBe(5)->and($item->movements()->where('reason', 'sale_voided')->count())->toBe(1);
});

it('rolls back all direct stock deductions when a later product is insufficient', function () {
    $actor = financeActor();
    $action = app(ManageReceipt::class);
    $first = InventoryItem::factory()->create();
    $last = InventoryItem::factory()->create();
    app(StockLedger::class)->move($actor, $first, 4, 'in', 'purchase');
    $receipt = $action->create($actor, ['items' => [
        ['type' => 'product', 'inventory_item_id' => $first->id, 'quantity' => 2],
        ['type' => 'product', 'inventory_item_id' => $last->id, 'quantity' => 1],
    ]]);
    expect(fn () => $action->finalize($actor, $receipt))->toThrow(ValidationException::class);
    expect($first->refresh()->current_stock)->toBe(4)->and($receipt->refresh()->status)->toBe(ReceiptStatus::Draft)
        ->and($first->movements()->where('reason', 'direct_sale')->count())->toBe(0);
});

it('rebuilds only completed service jobs and used parts without double deduction', function () {
    $actor = financeActor('owner');
    $action = app(ManageReceipt::class);
    $order = ServiceOrder::factory()->create(['status' => 'in_progress']);
    $item = InventoryItem::factory()->create(['selling_price' => '50.25']);
    app(StockLedger::class)->move($actor, $item, 5, 'in', 'purchase');
    app(UseServicePart::class)->use($actor, $order, $item->id, 2);
    ServiceJob::factory()->create(['service_order_id' => $order->id, 'status' => 'completed', 'labor_price' => '30.00']);
    ServiceJob::factory()->create(['service_order_id' => $order->id, 'status' => 'cancelled', 'labor_price' => '999.00']);
    expect(fn () => $action->create($actor, ['service_order_id' => $order->id]))->toThrow(ValidationException::class);
    $order->update(['status' => 'completed']);
    $receipt = $action->create($actor, ['service_order_id' => $order->id]);
    $item->update(['selling_price' => '999']);
    $action->finalize($actor, $receipt);
    expect($receipt->refresh()->grand_total)->toBe('130.50')->and($receipt->items()->count())->toBe(2)->and($item->refresh()->current_stock)->toBe(3);
    $action->void($actor, $receipt, 'Koreksi');
    $action->void($actor, $receipt, 'Ulang');
    expect($item->refresh()->current_stock)->toBe(5)->and($order->parts()->whereNull('returned_at')->count())->toBe(0);
    expect(fn () => $action->create($actor, ['service_order_id' => $order->id]))->toThrow(ValidationException::class);
});

it('rolls back finalization payment reversal and void on audit failure', function () {
    $actor = financeActor('owner');
    $action = app(ManageReceipt::class);
    $receipt = customReceipt($actor);
    AuditLog::creating(function () {
        throw new RuntimeException('audit unavailable');
    });
    try {
        expect(fn () => $action->finalize($actor, $receipt))->toThrow(RuntimeException::class);
    } finally {
        AuditLog::flushEventListeners();
    }
    expect($receipt->refresh()->status)->toBe(ReceiptStatus::Draft);
    $action->finalize($actor, $receipt);
    AuditLog::creating(function () {
        throw new RuntimeException('audit unavailable');
    });
    try {
        expect(fn () => $action->pay($actor, $receipt, ['amount' => '100', 'method' => 'cash', 'paid_at' => now()->toDateTimeString()]))->toThrow(RuntimeException::class);
    } finally {
        AuditLog::flushEventListeners();
    }
    expect($receipt->payments()->count())->toBe(0)->and($receipt->refresh()->payment_status)->toBe(PaymentStatus::Unpaid);
    $payment = $action->pay($actor, $receipt, ['amount' => '100', 'method' => 'cash', 'paid_at' => now()->toDateTimeString()]);
    AuditLog::creating(function () {
        throw new RuntimeException('audit unavailable');
    });
    try {
        expect(fn () => $action->reversePayment($actor, $payment, 'Koreksi'))->toThrow(RuntimeException::class);
        expect(fn () => $action->void($actor, $receipt, 'Koreksi'))->toThrow(RuntimeException::class);
    } finally {
        AuditLog::flushEventListeners();
    }
    expect($payment->refresh()->reversed_at)->toBeNull()->and($receipt->refresh()->status)->toBe(ReceiptStatus::Final)->and($receipt->payment_status)->toBe(PaymentStatus::Partial);
});

it('denies financial access to mechanics and customers', function (string $role) {
    $actor = financeActor($role);
    expect(fn () => app(ManageReceipt::class)->create($actor, []))->toThrow(AuthorizationException::class);
})->with(['mechanic', 'customer']);

it('rejects negative prices invalid amounts overdiscount and excessive totals', function (array $input) {
    expect(fn () => app(ManageReceipt::class)->create(financeActor(), $input))->toThrow(ValidationException::class);
    expect(Receipt::count())->toBe(0);
})->with([
    [['items' => [['type' => 'custom', 'description' => 'X', 'quantity' => 1, 'unit_price' => '-1']]]],
    [['items' => [['type' => 'custom', 'description' => 'X', 'quantity' => 0, 'unit_price' => '1']]]],
    [['items' => [['type' => 'custom', 'description' => 'X', 'quantity' => 1, 'unit_price' => '1']], 'discount' => '2']],
    [['items' => [['type' => 'custom', 'description' => 'X', 'quantity' => 2, 'unit_price' => '999999999999.99']]]],
]);

it('settles a fully discounted receipt without a fictitious payment', function () {
    $actor = financeActor();
    $receipt = app(ManageReceipt::class)->create($actor, [
        'items' => [['type' => 'custom', 'description' => 'Garansi servis', 'quantity' => 1, 'unit_price' => '100.00']],
        'discount' => '100.00',
    ]);
    $final = app(ManageReceipt::class)->finalize($actor, $receipt);
    expect($final->status)->toBe(ReceiptStatus::Paid)->and($final->payment_status)->toBe(PaymentStatus::Paid)
        ->and($final->payments()->count())->toBe(0);
});

it('keeps oversized image documents editable instead of locking an unrenderable receipt', function () {
    $actor = User::factory()->create(['role' => 'owner']);
    $items = array_fill(0, 100, ['type' => 'custom', 'description' => str_repeat('Long description ', 14), 'quantity' => 1, 'unit_price' => '1.00']);
    $receipt = app(ManageReceipt::class)->create($actor, ['items' => $items]);
    expect(fn () => app(ManageReceipt::class)->finalize($actor, $receipt))->toThrow(ValidationException::class);
    expect($receipt->fresh()->status->value)->toBe('draft');
    expect($receipt->items()->count())->toBe(100);
});

it('preserves draft rows when updating only notes', function () {
    $actor = financeActor();
    $action = app(ManageReceipt::class);
    $receipt = customReceipt($actor);
    $action->saveDraft($actor, $receipt, ['notes' => 'Tanpa perubahan baris']);
    expect($receipt->refresh()->grand_total)->toBe('300.01')->and($receipt->items()->count())->toBe(1);
});

it('returns all stock atomically when receipt void audit fails', function () {
    $actor = financeActor('owner');
    $action = app(ManageReceipt::class);
    $item = InventoryItem::factory()->create();
    app(StockLedger::class)->move($actor, $item, 5, 'in', 'purchase');
    $receipt = $action->create($actor, ['items' => [['type' => 'product', 'inventory_item_id' => $item->id, 'quantity' => 2]]]);
    $action->finalize($actor, $receipt);
    AuditLog::creating(function ($log) {
        if ($log->action === 'receipt.voided') {
            throw new RuntimeException('audit unavailable');
        }
    });
    try {
        expect(fn () => $action->void($actor, $receipt, 'Koreksi'))->toThrow(RuntimeException::class);
    } finally {
        AuditLog::flushEventListeners();
    }
    expect($item->refresh()->current_stock)->toBe(3)->and($receipt->refresh()->status)->toBe(ReceiptStatus::Final)->and($item->movements()->where('reason', 'sale_voided')->count())->toBe(0);
});

it('validates payment date method reference and exact monetary strings', function (array $input) {
    $actor = financeActor();
    $action = app(ManageReceipt::class);
    $receipt = $action->finalize($actor, customReceipt($actor));
    expect(fn () => $action->pay($actor, $receipt, $input))->toThrow(ValidationException::class);
    expect($receipt->payments()->count())->toBe(0);
})->with([
    [['amount' => '0', 'method' => 'cash', 'paid_at' => '2026-01-01']],
    [['amount' => '1e2', 'method' => 'cash', 'paid_at' => '2026-01-01']],
    [['amount' => '1', 'method' => 'invalid', 'paid_at' => '2026-01-01']],
    [['amount' => '1', 'method' => 'cash', 'paid_at' => '2099-01-01']],
    [['amount' => '1', 'method' => 'cash', 'paid_at' => '2026-01-01', 'reference' => str_repeat('x', 256)]],
]);

it('voids drafts without returning unused stock and requires owner reason', function () {
    $admin = financeActor();
    $owner = financeActor('owner');
    $action = app(ManageReceipt::class);
    $item = InventoryItem::factory()->create();
    app(StockLedger::class)->move($admin, $item, 5, 'in', 'purchase');
    $receipt = $action->create($admin, ['items' => [['type' => 'product', 'inventory_item_id' => $item->id, 'quantity' => 2]]]);
    expect(fn () => $action->void($admin, $receipt, 'Koreksi'))->toThrow(AuthorizationException::class);
    expect(fn () => $action->void($owner, $receipt, '  '))->toThrow(ValidationException::class);
    expect(fn () => $action->pay($admin, $receipt, ['amount' => '1', 'method' => 'cash', 'paid_at' => now()->toDateTimeString()]))->toThrow(ValidationException::class);
    $action->void($owner, $receipt, 'Batal draf');
    expect($item->refresh()->current_stock)->toBe(5)->and($item->movements()->where('reason', 'sale_voided')->count())->toBe(0);
});

it('rejects repeated finalization without duplicate deductions', function () {
    $actor = financeActor();
    $action = app(ManageReceipt::class);
    $item = InventoryItem::factory()->create();
    app(StockLedger::class)->move($actor, $item, 5, 'in', 'purchase');
    $receipt = $action->create($actor, ['items' => [['type' => 'product', 'inventory_item_id' => $item->id, 'quantity' => 2]]]);
    $action->finalize($actor, $receipt);
    expect(fn () => $action->finalize($actor, $receipt))->toThrow(ValidationException::class);
    expect($item->refresh()->current_stock)->toBe(3)->and($item->movements()->where('reason', 'direct_sale')->count())->toBe(1);
});
