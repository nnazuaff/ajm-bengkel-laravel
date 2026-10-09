<?php

use App\Livewire\BookingRequest;
use App\Livewire\BookingReview;
use App\Livewire\CheckInIntake;
use App\Livewire\CustomerAccount;
use App\Livewire\CustomerEditor;
use App\Livewire\InventoryCategories;
use App\Livewire\InventoryEditor;
use App\Livewire\InventoryHistory;
use App\Livewire\InventoryStock;
use App\Livewire\ServiceHistoryDetail;
use App\Livewire\StaffEditor;
use App\Livewire\VehicleEditor;
use App\Livewire\WalkInIntake;
use App\Models\Booking;
use App\Models\CheckIn;
use App\Models\Customer;
use App\Models\InventoryItem;
use App\Models\User;
use App\Models\Vehicle;
use Livewire\Livewire;

it('renders one explicit close control without the Flux duplicate', function (string $component, string $open, string $close) {
    $role = $component === BookingRequest::class ? 'customer' : 'owner';
    $arguments = match ($component) {
        ServiceHistoryDetail::class => [Vehicle::factory()->create()->id],
        CheckInIntake::class => [CheckIn::create(['customer_id' => Customer::factory()->create()->id, 'status' => 'waiting', 'checked_in_at' => now()])->id],
        InventoryHistory::class => [InventoryItem::factory()->create()->id],
        InventoryStock::class => [InventoryItem::factory()->create()->id, 'in'],
        CustomerAccount::class => [Customer::factory()->create()->id],
        BookingReview::class => [Booking::factory()->create()->id],
        default => [],
    };
    $modal = Livewire::actingAs(User::factory()->create(['role' => $role]))->test($component)
        ->call($open, ...$arguments)->assertDontSeeHtml('data-flux-modal-close');
    $document = new DOMDocument;
    @$document->loadHTML($modal->html());
    $xpath = new DOMXPath($document);
    $controls = [];
    foreach ($xpath->query('//dialog//button') as $button) {
        if ($button->getAttribute('wire:click') === $close) {
            $controls[] = $button;
        }
    }
    expect($controls)->toHaveCount(1);
    $modal->call($close)->assertSet($component === WalkInIntake::class ? 'showIntake' : 'showForm', false);
})->with([
    [ServiceHistoryDetail::class, 'openHistory', 'closeHistory'],
    [CheckInIntake::class, 'openForm', 'closeForm'],
    [InventoryHistory::class, 'history', 'closeHistory'],
    [InventoryEditor::class, 'create', 'closeForm'],
    [InventoryStock::class, 'openStock', 'closeStock'],
    [InventoryCategories::class, 'openForm', 'closeForm'],
    [CustomerEditor::class, 'create', 'closeForm'],
    [VehicleEditor::class, 'create', 'closeForm'],
    [CustomerAccount::class, 'openForm', 'closeForm'],
    [StaffEditor::class, 'create', 'cancel'],
    [WalkInIntake::class, 'openIntake', 'closeIntake'],
    [BookingRequest::class, 'openForm', 'closeForm'],
    [BookingReview::class, 'openBooking', 'closeBooking'],
]);

it('disables built in close whenever a modal supplies its own dismiss control', function () {
    foreach (glob(resource_path('views/livewire/*.blade.php')) as $path) {
        $source = file_get_contents($path);
        if (preg_match('/<flux:modal\s[^>]*>/', $source, $tag)) {
            expect($tag[0])->toContain(':closable="false"');
        }
    }
    $source = file_get_contents(resource_path('views/pages/settings/⚡delete-user-modal.blade.php'));
    expect($source)->toContain(':closable="false"');
});
