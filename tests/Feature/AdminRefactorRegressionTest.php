<?php

use App\Livewire\BookingReview;
use App\Livewire\Customers;
use App\Livewire\InventoryCategories;
use App\Livewire\InventoryEditor;
use App\Livewire\InventoryHistory;
use App\Livewire\InventoryStock;
use App\Livewire\ReceiptEditor;
use App\Livewire\Services;
use App\Livewire\Vehicles;
use App\Livewire\WalkInIntake;
use App\Models\Booking;
use App\Models\InventoryItem;
use App\Models\Receipt;
use App\Models\ServiceOrder;
use App\Models\User;
use Livewire\Livewire;

it('redirects newly saved receipt drafts to a stable reloadable editor URL', function () {
    $actor = User::factory()->create(['role' => 'admin']);
    Livewire::actingAs($actor)->test(ReceiptEditor::class)
        ->set('items', [['type' => 'custom', 'description' => 'Cuci', 'quantity' => 1, 'unit_price' => '10000.00']])
        ->call('save')->assertRedirect(route('receipts.edit', Receipt::sole()->id));
    $this->get(route('receipts.edit', Receipt::sole()->id))->assertOk()->assertSee('Cuci');
    expect(Receipt::count())->toBe(1);
});

it('links a converted booking directly to its dedicated service detail', function () {
    $actor = User::factory()->create(['role' => 'owner']);
    $booking = Booking::factory()->create(['status' => 'converted_to_service']);
    $order = ServiceOrder::factory()->create(['booking_id' => $booking->id, 'source' => 'booking']);
    Livewire::actingAs($actor)->test(BookingReview::class)->call('openBooking', $booking->id)
        ->assertSeeHtml(route('services.detail', $order->id));
});

it('denies retained actor authority after database role changes', function (string $component) {
    $actor = User::factory()->create(['role' => 'admin']);
    $page = Livewire::actingAs($actor)->test($component);
    User::whereKey($actor->id)->update(['role' => 'customer']);
    $page->call('$refresh')->assertForbidden();
})->with([Customers::class, Vehicles::class, Services::class, ReceiptEditor::class, WalkInIntake::class,
    InventoryEditor::class, InventoryStock::class, InventoryCategories::class, InventoryHistory::class]);

it('rejects stock writes through a retained actor after demotion', function () {
    $actor = User::factory()->create(['role' => 'admin']);
    $item = InventoryItem::factory()->create(['current_stock' => 5]);
    $page = Livewire::actingAs($actor)->test(InventoryStock::class)->call('openStock', $item->id, 'in')
        ->set('stockForm.quantity', '2')->set('stockForm.reason', 'Restok');
    User::whereKey($actor->id)->update(['role' => 'customer']);
    $page->call('saveStock')->assertForbidden();
    expect($item->fresh()->current_stock)->toBe(5);
});
