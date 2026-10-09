<?php

use App\Actions\ManageReceipt;
use App\Livewire\ServiceHistory;
use App\Livewire\ServiceHistoryDetail;
use App\Models\Customer;
use App\Models\ServiceOrder;
use App\Models\User;
use App\Models\Vehicle;
use Livewire\Livewire;

it('finds archived vehicle service lineage by canonical plate without losing complaint and diagnosis', function () {
    $customer = Customer::factory()->create(['name' => 'Sari Wulandari']);
    $vehicle = Vehicle::factory()->create(['customer_id' => $customer->id, 'license_plate' => 'B1234ABC']);
    $order = ServiceOrder::factory()->create(['vehicle_id' => $vehicle->id, 'customer_id' => $customer->id, 'complaint' => 'Rem berisik.', 'diagnosis' => 'Kampas tipis.', 'status' => 'delivered']);
    $vehicle->delete();
    $customer->delete();
    Livewire::actingAs(User::factory()->create(['role' => 'admin']))->test(ServiceHistory::class)
        ->set('search', 'b 1234 abc')->assertSee('B1234ABC')->call('selectVehicle', $vehicle->id)
        ->assertSee('Sari Wulandari')->assertDispatchedTo(ServiceHistoryDetail::class, 'open-service-history', id: $vehicle->id);
    Livewire::test(ServiceHistoryDetail::class)->call('openHistory', $vehicle->id)
        ->assertSee('Sari Wulandari')->assertSee('Rem berisik.')->assertSee('Kampas tipis.')
        ->assertSee($order->service_number);
});

it('links draft history receipts to the editor rather than the unavailable print route', function () {
    $actor = User::factory()->create(['role' => 'admin']);
    $order = ServiceOrder::factory()->create(['status' => 'completed']);
    $receipt = app(ManageReceipt::class)->create($actor, ['service_order_id' => $order->id]);
    Livewire::actingAs($actor)->test(ServiceHistoryDetail::class)->call('openHistory', $order->vehicle_id)
        ->assertSeeHtml(route('receipts.edit', $receipt->id))
        ->assertDontSeeHtml(route('receipts.show', $receipt));
});

it('denies general history to customers and mechanics', function (string $role) {
    Livewire::actingAs(User::factory()->create(['role' => $role]))->test(ServiceHistory::class)->assertForbidden();
})->with(['customer', 'mechanic']);
