<?php

use App\Livewire\ServiceHistory;
use App\Livewire\ServiceHistoryDetail;
use App\Models\Receipt;
use App\Models\ServiceDocumentation;
use App\Models\ServiceItem;
use App\Models\ServiceJob;
use App\Models\ServiceOrder;
use App\Models\User;
use App\Models\Vehicle;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

it('keeps service history details out of the list and targets the always mounted popup', function () {
    $order = ServiceOrder::factory()->create(['complaint' => 'Keluhan khusus riwayat']);
    $page = Livewire::actingAs(User::factory()->create(['role' => 'admin']))->test(ServiceHistory::class)
        ->assertSeeLivewire(ServiceHistoryDetail::class)
        ->call('selectVehicle', $order->vehicle_id)
        ->assertDispatchedTo(ServiceHistoryDetail::class, 'open-service-history', id: $order->vehicle_id)
        ->assertDontSee('Keluhan khusus riwayat');

    Livewire::test(ServiceHistoryDetail::class)->dispatch('open-service-history', id: $order->vehicle_id)
        ->assertSet('showForm', true)->assertSet('selectedVehicleId', $order->vehicle_id)
        ->assertSee('Keluhan khusus riwayat')->assertSee($order->service_number);
});

it('renders a single accessible close control and responsive labelled list cells with focus return', function () {
    $order = ServiceOrder::factory()->create();
    $page = Livewire::actingAs(User::factory()->create(['role' => 'admin']))->test(ServiceHistory::class)
        ->assertSeeHtml('workshop-responsive-table')->assertSeeHtml('data-label="Motor"')
        ->assertSeeHtml('data-label="Pemilik"')->assertSeeHtml('data-label="Tindakan"')
        ->assertSeeHtml('x-on:service-history-closed.window=');
    $modal = Livewire::test(ServiceHistoryDetail::class)->call('openHistory', $order->vehicle_id)
        ->assertSeeHtml('aria-labelledby="service-history-heading"')->assertDontSeeHtml('data-flux-modal-close');
    $document = new DOMDocument;
    @$document->loadHTML($modal->html());
    $xpath = new DOMXPath($document);
    $controls = [];
    foreach ($xpath->query('//dialog//button') as $button) {
        if ($button->getAttribute('wire:click') === 'closeHistory') {
            $controls[] = $button;
        }
    }
    expect($controls)->toHaveCount(1);
});

it('resets history pagination and selection on explicit close escape and switching vehicles', function () {
    $first = ServiceOrder::factory()->create(['complaint' => 'Keluhan motor pertama']);
    $second = ServiceOrder::factory()->create(['complaint' => 'Keluhan motor kedua']);
    Livewire::actingAs(User::factory()->create(['role' => 'owner']))->test(ServiceHistoryDetail::class)
        ->call('openHistory', $first->vehicle_id)->set('paginators.historyPage', 3)
        ->call('openHistory', $second->vehicle_id)->assertSet('paginators.historyPage', 1)
        ->assertSee('Keluhan motor kedua')->assertDontSee('Keluhan motor pertama')
        ->call('closeHistory')->assertSet('showForm', false)->assertSet('selectedVehicleId', null)
        ->assertSet('paginators.historyPage', 1)->assertDispatched('service-history-closed')
        ->call('openHistory', $first->vehicle_id)->set('paginators.historyPage', 2)
        ->set('showForm', false)->assertSet('selectedVehicleId', null)->assertSet('paginators.historyPage', 1)
        ->assertDispatched('service-history-closed')->assertDontSee('Keluhan motor pertama')
        ->call('openHistory', $second->vehicle_id)->assertSee('Keluhan motor kedua')->assertHasNoErrors();
});

it('locks the selected service history vehicle against client tampering', function () {
    Livewire::actingAs(User::factory()->create(['role' => 'admin']))->test(ServiceHistoryDetail::class)
        ->set('selectedVehicleId', 123);
})->throws(CannotUpdateLockedPropertyException::class);

it('denies service history popup mounts to non administrators', function (string $role) {
    Livewire::actingAs(User::factory()->create(['role' => $role]))->test(ServiceHistoryDetail::class)->assertForbidden();
})->with(['customer', 'mechanic']);

it('reauthorizes every history request after the actor role is revoked', function (string $request) {
    $actor = User::factory()->create(['role' => 'admin']);
    $order = ServiceOrder::factory()->create();
    $modal = Livewire::actingAs($actor)->test(ServiceHistoryDetail::class)->call('openHistory', $order->vehicle_id);
    User::findOrFail($actor->id)->forceFill(['role' => 'mechanic'])->save();
    (match ($request) {
        'open' => $modal->dispatch('open-service-history', id: $order->vehicle_id),
        'close' => $modal->call('closeHistory'),
        'escape' => $modal->set('showForm', false),
        'pagination' => $modal->call('setPage', 2, 'historyPage'),
        'refresh' => $modal->call('$refresh'),
    })->assertForbidden();
})->with(['open', 'close', 'escape', 'pagination', 'refresh']);

it('rejects unknown history vehicles in both the list and popup', function (string $component, string $method) {
    Livewire::actingAs(User::factory()->create(['role' => 'admin']))->test($component)->call($method, 99999)->assertNotFound();
})->with([[ServiceHistory::class, 'selectVehicle'], [ServiceHistoryDetail::class, 'openHistory']]);

it('preserves jobs parts documentation and finalized receipt details in the popup', function () {
    $mechanic = User::factory()->create(['role' => 'mechanic', 'name' => 'Mekanik riwayat']);
    $order = ServiceOrder::factory()->create(['mechanic_id' => $mechanic->id, 'current_mileage' => 12345]);
    ServiceJob::factory()->create(['service_order_id' => $order->id, 'name' => 'Pekerjaan riwayat', 'description' => 'Catatan pekerjaan']);
    ServiceItem::factory()->create(['service_order_id' => $order->id, 'description' => 'Part riwayat', 'returned_at' => now()]);
    $photo = ServiceDocumentation::factory()->create(['service_order_id' => $order->id, 'caption' => 'Foto riwayat']);
    $receipt = Receipt::factory()->create(['service_order_id' => $order->id, 'status' => 'paid', 'payment_status' => 'paid', 'grand_total' => '40000.00']);
    Livewire::actingAs(User::factory()->create(['role' => 'admin']))->test(ServiceHistoryDetail::class)
        ->call('openHistory', $order->vehicle_id)->assertSee('Mekanik riwayat')->assertSee('12.345 km')
        ->assertSee('Pekerjaan riwayat')->assertSee('Catatan pekerjaan')->assertSee('Part riwayat')
        ->assertSee('(dikembalikan)')->assertSee('Foto riwayat')->assertSeeHtml(route('documentation.show', $photo))
        ->assertSee($receipt->receipt_number)->assertSee('40000,00')->assertSee('paid')
        ->assertSeeHtml(route('receipts.show', $receipt))->assertDontSeeHtml(route('receipts.edit', $receipt->id));
});

it('paginates vehicle histories independently of the list and shows an empty vehicle history', function () {
    $vehicle = Vehicle::factory()->create();
    ServiceOrder::factory()->count(11)->create(['vehicle_id' => $vehicle->id, 'received_at' => now()->subDay(), 'complaint' => 'Servis lama']);
    $newest = ServiceOrder::factory()->create(['vehicle_id' => $vehicle->id, 'received_at' => now(), 'complaint' => 'Servis terbaru']);
    $empty = Vehicle::factory()->create();
    Livewire::actingAs(User::factory()->create(['role' => 'admin']))->test(ServiceHistoryDetail::class)
        ->call('openHistory', $vehicle->id)->assertSee($newest->service_number)
        ->assertViewHas('history', fn ($history) => $history->count() === 10 && $history->total() === 12)
        ->call('setPage', 2, 'historyPage')->assertDontSee('Servis terbaru')->assertSee('Servis lama')
        ->assertViewHas('history', fn ($history) => $history->count() === 2)
        ->call('openHistory', $empty->id)->assertSet('paginators.historyPage', 1)
        ->assertSee('Belum ada servis untuk motor ini.');
});
