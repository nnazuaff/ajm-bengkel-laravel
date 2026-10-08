<?php

use App\Enums\Role;
use App\Enums\ServiceStatus;
use App\Http\Controllers\ReportExportController;
use App\Livewire\Reports;
use App\Models\InventoryItem;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\ServiceOrder;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('streams an authorized CSV with real headers', function () {
    $this->actingAs(User::factory()->create(['role' => Role::Admin]))
        ->get('/reports/export?from=2026-10-01&to=2026-10-07')
        ->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
        ->assertHeader('Content-Disposition', 'attachment; filename=payments-2026-10-01-2026-10-07.csv')
        ->assertStreamedContent("tanggal,penerimaan,pembalikan,bersih,aktif,servis_selesai,stok_masuk,stok_keluar\n");
});

it('reports exact cash flow with paid dates and reversal dates independently', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $receipt = Receipt::factory()->create(['cashier_id' => $admin->id]);
    Payment::create(['receipt_id' => $receipt->id, 'created_by' => $admin->id, 'amount' => '100.10', 'method' => 'cash', 'paid_at' => '2026-10-02 09:00:00']);
    Payment::create(['receipt_id' => $receipt->id, 'created_by' => $admin->id, 'amount' => '50.20', 'method' => 'transfer', 'paid_at' => '2026-10-02 23:59:59', 'reversed_at' => '2026-10-03 10:00:00']);
    Payment::create(['receipt_id' => $receipt->id, 'created_by' => $admin->id, 'amount' => '20.05', 'method' => 'qris', 'paid_at' => '2026-09-30 09:00:00', 'reversed_at' => '2026-10-03 11:00:00']);
    Payment::create(['receipt_id' => $receipt->id, 'created_by' => $admin->id, 'amount' => '999.99', 'method' => 'other', 'paid_at' => '2026-10-04 00:00:00']);
    $rows = Reports::daily('2026-10-02', '2026-10-03');
    expect($rows->toArray())->toBe([
        ['date' => '2026-10-02', 'gross' => '150.30', 'reversed' => '0.00', 'net' => '150.30', 'active' => '100.10', 'services' => 0, 'stock_in' => 0, 'stock_out' => 0],
        ['date' => '2026-10-03', 'gross' => '0.00', 'reversed' => '70.25', 'net' => '-70.25', 'active' => '0.00', 'services' => 0, 'stock_in' => 0, 'stock_out' => 0],
    ]);
    Livewire\Livewire::actingAs($admin)->test(Reports::class)->set('from', '2026-10-02')->set('to', '2026-10-03')
        ->call('apply')->assertHasNoErrors()->assertSee('80.05')->assertSee('150.30')->assertSee('70.25')
        ->assertSee('Bukan laporan laba');
    $this->actingAs($admin)->get('/reports/export?from=2026-10-02&to=2026-10-03')->assertOk()
        ->assertStreamedContent("tanggal,penerimaan,pembalikan,bersih,aktif,servis_selesai,stok_masuk,stok_keluar\n2026-10-02,150.30,0.00,150.30,100.10,0,0,0\n2026-10-03,0.00,70.25,-70.25,0.00,0,0,0\n");
});

it('protects report page and exports from non managers', function (Role $role) {
    $actor = User::factory()->create(['role' => $role]);
    Livewire\Livewire::actingAs($actor)->test(Reports::class)->assertForbidden();
    $this->actingAs($actor)->get('/reports/export?from=2026-10-01&to=2026-10-07')->assertForbidden();
})->with([Role::Mechanic, Role::Customer]);

it('redirects guests and rejects invalid report date ranges', function () {
    $this->get('/reports/export?from=2026-10-01&to=2026-10-07')->assertRedirect('/login');
    $admin = User::factory()->create(['role' => Role::Admin]);
    $this->actingAs($admin)->getJson('/reports/export?from=2026-10-10&to=2026-10-01')->assertUnprocessable()->assertJsonValidationErrors('to');
    $this->actingAs($admin)->getJson('/reports/export?from=oops&to=2026-10-01')->assertUnprocessable()->assertJsonValidationErrors('from');
    Livewire\Livewire::actingAs($admin)->test(Reports::class)->set('from', '2026-10-10')->set('to', '2026-10-01')->call('apply')->assertHasErrors('to');
});

it('escapes spreadsheet formula cells and preserves numeric totals', function () {
    foreach (['=SUM(1,1)', '+cmd', '-cmd', '@formula', " \t=cmd"] as $value) {
        expect(ReportExportController::csvCell($value))->toBe("'".$value);
    }
    expect(ReportExportController::csvCell('-70.25'))->toBe('-70.25')
        ->and(ReportExportController::csvCell('2026-10-03'))->toBe('2026-10-03');
});

it('includes completed services and signed stock movements on their actual dates', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    ServiceOrder::factory()->create(['status' => ServiceStatus::Completed, 'completed_at' => '2026-10-03 12:00:00']);
    ServiceOrder::factory()->create(['status' => ServiceStatus::Delivered, 'completed_at' => '2026-10-03 12:00:00', 'delivered_at' => '2026-10-05 12:00:00']);
    $item = InventoryItem::factory()->create();
    foreach ([10, -3, 2] as $quantity) {
        StockMovement::create(['inventory_item_id' => $item->id, 'type' => $quantity > 0 ? 'in' : 'out', 'quantity' => $quantity, 'stock_before' => 10, 'stock_after' => 10 + $quantity, 'reason' => 'Test', 'created_by' => $admin->id, 'created_at' => '2026-10-03 14:00:00']);
    }
    // created_at is protected on the immutable model; use a test-only query to timestamp fixture rows.
    DB::table('stock_movements')->update(['created_at' => '2026-10-03 14:00:00']);
    expect(Reports::daily('2026-10-03', '2026-10-03')->toArray())->toBe([
        ['date' => '2026-10-03', 'gross' => '0.00', 'reversed' => '0.00', 'net' => '0.00', 'active' => '0.00', 'services' => 2, 'stock_in' => 12, 'stock_out' => 3],
    ]);
});
