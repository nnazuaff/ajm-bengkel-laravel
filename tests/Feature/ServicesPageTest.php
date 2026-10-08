<?php

use App\Enums\ServiceSource;
use App\Enums\ServiceStatus;
use App\Livewire\Services;
use App\Livewire\WalkInIntake;
use App\Models\Customer;
use App\Models\ServiceOrder;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

it('receives an existing motorcycle through the walk-in modal', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $mechanic = User::factory()->create(['role' => 'mechanic']);
    $customer = Customer::factory()->create(['name' => 'Sari Wulandari', 'phone' => '081234567890']);
    $vehicle = Vehicle::factory()->for($customer)->create(['license_plate' => 'B1234ABC', 'latest_mileage' => 12000]);

    Livewire::actingAs($admin)->test(WalkInIntake::class)
        ->call('openIntake')
        ->set('intakeSearch', 'b 1234 abc')
        ->assertSee('Sari Wulandari')
        ->call('selectVehicle', $vehicle->id)
        ->assertSet('selectedVehicleId', $vehicle->id)
        ->assertSet('intake.current_mileage', 12000)
        ->set('intake.current_mileage', '12500')
        ->set('intake.complaint', 'Rem belakang berbunyi.')
        ->set('intake.mechanic_id', (string) $mechanic->id)
        ->call('receiveWalkIn')
        ->assertHasNoErrors()
        ->assertSet('showIntake', false)
        ->assertSet('selectedVehicleId', null)
        ->assertDispatched('walk-in-received');

    $order = ServiceOrder::sole();
    expect($order->customer_id)->toBe($customer->id)
        ->and($order->vehicle_id)->toBe($vehicle->id)
        ->and($order->mechanic_id)->toBe($mechanic->id)
        ->and($order->received_by)->toBe($admin->id)
        ->and($order->source)->toBe(ServiceSource::WalkIn)
        ->and($order->status)->toBe(ServiceStatus::Waiting)
        ->and($order->current_mileage)->toBe(12500)
        ->and($order->diagnosis)->toBeNull()
        ->and($vehicle->fresh()->latest_mileage)->toBe(12500)
        ->and(Customer::count())->toBe(1)
        ->and(Vehicle::count())->toBe(1);
});

it('registers a new customer and motorcycle in the walk-in modal with empty optional fields', function () {
    Livewire::actingAs(User::factory()->create(['role' => 'admin']))->test(WalkInIntake::class)
        ->call('openIntake')
        ->call('useNewVehicle')
        ->set('intake.customer.name', 'Dimas Saputra')
        ->set('intake.customer.phone', '0812 9876 5432')
        ->set('intake.vehicle.license_plate', 'd 9981 jk')
        ->set('intake.vehicle.brand', 'Yamaha')
        ->set('intake.vehicle.model', 'Mio')
        ->set('intake.current_mileage', '1500')
        ->set('intake.complaint', 'Servis CVT.')
        ->call('receiveWalkIn')
        ->assertHasNoErrors()
        ->assertSet('showIntake', false)
        ->assertSet('intake.customer.name', '')
        ->assertSet('intake.vehicle.license_plate', '')
        ->assertDispatched('walk-in-received');

    $order = ServiceOrder::sole();
    expect($order->customer->phone)->toBe('6281298765432')
        ->and($order->vehicle->year)->toBeNull()
        ->and($order->vehicle->color)->toBeNull()
        ->and($order->customer->email)->toBeNull()
        ->and($order->mechanic_id)->toBeNull()
        ->and($order->notes)->toBeNull();
});

it('searches service numbers plates customers and phones with literal wildcards', function () {
    $customer = Customer::factory()->create(['name' => 'Sari Wulandari', 'phone' => '081234567890']);
    $vehicle = Vehicle::factory()->for($customer)->create(['license_plate' => 'B1234ABC']);
    $order = ServiceOrder::factory()->for($customer)->for($vehicle)->create(['service_number' => 'SRV-20261007-001']);
    $other = ServiceOrder::factory()->create(['service_number' => 'SRV-20261007-002']);

    $page = Livewire::actingAs(User::factory()->create(['role' => 'admin']))->test(Services::class);
    foreach (['SRV-20261007-001', 'b 1234 abc', 'sari   wulandari', '0812 3456-7890'] as $search) {
        $page->set('search', $search)->assertSee($order->service_number)->assertDontSee($other->service_number)
            ->assertViewHas('orders', fn ($rows) => $rows->count() === 1
                && $rows->first()->relationLoaded('customer') && $rows->first()->relationLoaded('vehicle')
                && $rows->first()->relationLoaded('mechanic'));
    }
    foreach (['%', '_'] as $search) {
        $page->set('search', $search)->assertViewHas('orders', fn ($rows) => $rows->isEmpty());
    }
});

it('filters services and resets fifteen-row pagination when a filter changes', function () {
    $mechanic = User::factory()->create(['role' => 'mechanic']);
    $order = ServiceOrder::factory()->create([
        'service_number' => 'SRV-MATCH-001', 'status' => ServiceStatus::Inspection,
        'mechanic_id' => $mechanic->id, 'source' => ServiceSource::Booking,
    ]);
    ServiceOrder::factory()->count(16)->create();

    $page = Livewire::actingAs(User::factory()->create(['role' => 'owner']))->test(Services::class)
        ->assertViewHas('orders', fn ($rows) => $rows->count() === 15 && $rows->total() === 17)
        ->call('gotoPage', 2)
        ->assertSet('paginators.page', 2);

    foreach (['statusFilter' => 'inspection', 'mechanicFilter' => (string) $mechanic->id, 'sourceFilter' => 'booking', 'search' => 'MATCH'] as $filter => $value) {
        $page->set($filter, $value)->assertSet('paginators.page', 1)->assertSee($order->service_number)
            ->assertViewHas('orders', fn ($rows) => $rows->total() === 1);
        $page->set($filter, '')->call('gotoPage', 2);
    }
});

it('keeps other mechanics orders out of every search branch', function () {
    $mechanic = User::factory()->create(['role' => 'mechanic']);
    $other = User::factory()->create(['role' => 'mechanic']);
    $assigned = ServiceOrder::factory()->create(['mechanic_id' => $mechanic->id, 'service_number' => 'SRV-ASSIGNED']);
    $customer = Customer::factory()->create(['name' => 'Pelanggan Rahasia', 'phone' => '081277776666']);
    $vehicle = Vehicle::factory()->for($customer)->create(['license_plate' => 'D9977XYZ']);
    $hidden = ServiceOrder::factory()->for($vehicle)->for($customer)->create(['mechanic_id' => $other->id, 'service_number' => 'SRV-HIDDEN']);

    $page = Livewire::actingAs($mechanic)->test(Services::class)
        ->assertSee($assigned->service_number)->assertDontSee($hidden->service_number)
        ->assertDontSee('Terima walk-in')
        ->set('mechanicFilter', (string) $other->id)
        ->assertViewHas('orders', fn ($rows) => $rows->total() === 1 && $rows->first()->id === $assigned->id);

    foreach (['SRV-HIDDEN', 'd 9977 xyz', 'Pelanggan Rahasia', '0812 7777 6666'] as $search) {
        $page->set('search', $search)->assertViewHas('orders', fn ($rows) => $rows->isEmpty())
            ->assertDontSee($customer->name)->assertDontSee($customer->phone)->assertDontSee($vehicle->license_plate);
    }

    $page->call('openOrder', $hidden->id)->assertForbidden();
});

it('locks order and vehicle selections against browser property tampering', function (string $property) {
    $page = Livewire::actingAs(User::factory()->create(['role' => 'admin']))->test($property === 'selectedVehicleId' ? WalkInIntake::class : Services::class);

    expect(fn () => $page->set($property, 999999))
        ->toThrow(CannotUpdateLockedPropertyException::class);
})->with(['selectedOrderId', 'selectedVehicleId']);

it('maps lower mileage errors to the intake field without writing an order', function () {
    $vehicle = Vehicle::factory()->create(['latest_mileage' => 12000]);
    Livewire::actingAs(User::factory()->create(['role' => 'admin']))->test(WalkInIntake::class)
        ->call('openIntake')->call('selectVehicle', $vehicle->id)
        ->set('intake.current_mileage', '11999')->set('intake.complaint', 'Periksa rem.')
        ->call('receiveWalkIn')->assertHasErrors(['intake.current_mileage'])
        ->assertSee('Kilometer tidak boleh lebih kecil')->assertSet('showIntake', true)
        ->assertSet('intake.complaint', 'Periksa rem.');
    expect(ServiceOrder::count())->toBe(0)->and($vehicle->fresh()->latest_mileage)->toBe(12000);
});

it('preserves profiles when a new motor uses an existing canonical phone', function () {
    $customer = Customer::factory()->create(['name' => 'Sari Wulandari', 'phone' => '081234567890']);
    Livewire::actingAs(User::factory()->create(['role' => 'admin']))->test(WalkInIntake::class)
        ->call('openIntake')->call('useNewVehicle')
        ->set('intake.customer.name', 'Nama berbeda')->set('intake.customer.phone', '+62 812-3456-7890')
        ->set('intake.vehicle.license_plate', 'D4567XYZ')->set('intake.vehicle.brand', 'Honda')->set('intake.vehicle.model', 'Beat')
        ->set('intake.current_mileage', '100')->set('intake.complaint', 'Periksa lampu.')
        ->call('receiveWalkIn')->assertHasNoErrors();
    expect(ServiceOrder::sole()->customer_id)->toBe($customer->id)
        ->and($customer->fresh()->name)->toBe('Sari Wulandari')->and(Customer::count())->toBe(1);
});

it('validates tampered intake sections before array processing', function (string $section) {
    Livewire::actingAs(User::factory()->create(['role' => 'admin']))->test(WalkInIntake::class)
        ->call('openIntake')->call('useNewVehicle')
        ->set('intake.'.$section, 'invalid scalar')
        ->call('receiveWalkIn')->assertHasErrors(['intake.'.$section]);
    expect(ServiceOrder::count())->toBe(0);
})->with(['customer', 'vehicle']);

it('denies customer component mounts and public service actions even for their own order', function () {
    $account = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $account->id]);
    $vehicle = Vehicle::factory()->for($customer)->create();
    $order = ServiceOrder::factory()->for($customer)->for($vehicle)->create();
    $this->actingAs($account);

    Livewire::test(Services::class)->assertForbidden();
    Livewire::test(WalkInIntake::class)->assertForbidden();
    foreach ([['mount'], ['render'], ['openIntake'], ['closeIntake'], ['selectVehicle', $vehicle->id], ['receiveWalkIn'], ['useNewVehicle'], ['useExistingVehicle']] as $call) {
        $method = array_shift($call);
        expect(fn () => (new WalkInIntake)->{$method}(...$call))->toThrow(AuthorizationException::class);
    }
    foreach ([['mount'], ['render'], ['openOrder', $order->id], ['saveOrder']] as $call) {
        $method = array_shift($call);
        $component = new Services;
        $component->selectedOrderId = $order->id;
        expect(fn () => $component->{$method}(...$call))
            ->toThrow(AuthorizationException::class);
    }
});

it('keeps the initial list clean and links orders to the dedicated page', function () {
    $order = ServiceOrder::factory()->create(['complaint' => 'Keluhan hanya di detail.']);
    Livewire::actingAs(User::factory()->create(['role' => 'admin']))->test(Services::class)
        ->assertSeeLivewire(WalkInIntake::class)
        ->assertSeeHtml('href="'.route('services.detail', $order).'"')
        ->assertDontSee('Keluhan hanya di detail.')
        ->assertDontSee('Terima servis walk-in')
        ->call('openOrder', $order->id)
        ->assertDontSee('Keluhan hanya di detail.');
});

it('opens intake by event and clears stale fields on native modal dismissal', function () {
    $vehicle = Vehicle::factory()->create();
    Livewire::actingAs(User::factory()->create(['role' => 'admin']))->test(WalkInIntake::class)
        ->assertSet('showIntake', false)
        ->dispatch('open-walk-in-intake')
        ->assertSet('showIntake', true)
        ->call('selectVehicle', $vehicle->id)
        ->set('intake.complaint', 'Draft penerimaan.')
        ->set('showIntake', false)
        ->assertSet('selectedVehicleId', null)
        ->assertSet('intake.complaint', '')
        ->dispatch('open-walk-in-intake')
        ->assertSet('newVehicle', false)
        ->assertSet('intakeSearch', '')
        ->assertHasNoErrors();
});

it('refreshes list pagination after successful intake without changing filters', function () {
    ServiceOrder::factory()->count(16)->create();
    Livewire::actingAs(User::factory()->create(['role' => 'admin']))->test(Services::class)
        ->set('statusFilter', 'waiting')
        ->call('gotoPage', 2)
        ->dispatch('walk-in-received')
        ->assertSet('paginators.page', 1)
        ->assertSet('statusFilter', 'waiting');
});

it('excludes archived ownership records from walk-in search and selection', function (string $archived) {
    $customer = Customer::factory()->create(['phone' => '081234567890']);
    $vehicle = Vehicle::factory()->for($customer)->create(['license_plate' => 'B1234ARC']);
    ($archived === 'customer' ? $customer : $vehicle)->delete();
    Livewire::actingAs(User::factory()->create(['role' => 'admin']))->test(WalkInIntake::class)
        ->call('openIntake')->set('intakeSearch', 'B1234ARC')
        ->assertViewHas('vehicleMatches', fn ($matches) => $matches->isEmpty())
        ->call('selectVehicle', $vehicle->id)->assertNotFound();
    expect(ServiceOrder::count())->toBe(0);
})->with(['customer', 'vehicle']);
