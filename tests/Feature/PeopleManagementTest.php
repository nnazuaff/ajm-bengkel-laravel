<?php

use App\Enums\Role;
use App\Enums\ServiceStatus;
use App\Livewire\CustomerEditor;
use App\Livewire\Customers;
use App\Livewire\VehicleEditor;
use App\Livewire\Vehicles;
use App\Models\Customer;
use App\Models\ServiceOrder;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

it('lets an admin register a customer with canonical contact details', function () {
    Livewire::actingAs(User::factory()->create(['role' => 'admin']))
        ->test(CustomerEditor::class)
        ->call('create')
        ->set('form.name', '  Siti   Aminah  ')
        ->set('form.phone', '0812-3456 (7890)')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('showForm', false)
        ->assertDispatched('customer-saved');

    $this->assertDatabaseHas('customers', [
        'name' => 'Siti Aminah',
        'phone' => '6281234567890',
        'email' => null,
        'address' => null,
        'notes' => null,
        'user_id' => null,
    ]);
});

it('edits a customer while rejecting equivalent phone numbers already registered', function () {
    $customer = Customer::create(['name' => 'Siti', 'phone' => '081234567890']);
    Customer::create(['name' => 'Budi', 'phone' => '+62 813-4567-8901']);

    $page = Livewire::actingAs(User::factory()->create(['role' => 'owner']))
        ->test(CustomerEditor::class)
        ->call('edit', $customer->id)
        ->set('form.phone', '0813 4567 8901')
        ->call('save')
        ->assertHasErrors(['form.phone' => 'unique']);

    expect($customer->fresh()->phone)->toBe('6281234567890');

    $page->set('form.name', ' Siti   Aminah ')
        ->set('form.phone', '+62 (812) 3456-7890')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('editingId', null);

    expect($customer->fresh()->name)->toBe('Siti Aminah');
    expect(Customer::count())->toBe(2);
});

it('registers a motorcycle against an active customer after validating its details', function () {
    $customer = Customer::factory()->create();
    $page = Livewire::actingAs(User::factory()->create(['role' => 'admin']))
        ->test(VehicleEditor::class)
        ->call('create')
        ->set('form.customer_id', '999999')
        ->set('form.license_plate', ' ')
        ->set('form.year', 1899)
        ->set('form.latest_mileage', -1)
        ->call('save')
        ->assertHasErrors(['form.customer_id', 'form.license_plate', 'form.brand', 'form.model', 'form.year', 'form.latest_mileage']);

    $page->set('form.customer_id', (string) $customer->id)
        ->set('form.license_plate', ' b 1234 abc ')
        ->set('form.brand', ' Honda ')
        ->set('form.model', ' Vario  125 ')
        ->set('form.year', (string) now()->year)
        ->set('form.latest_mileage', '12500')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('showForm', false)
        ->assertDispatched('vehicle-saved');

    $vehicle = Vehicle::sole();
    expect($vehicle->customer->is($customer))->toBeTrue();
    expect($customer->vehicles->sole()->is($vehicle))->toBeTrue();
    expect($vehicle->latest_mileage)->toBe(12500);
    $this->assertDatabaseHas('vehicles', [
        'customer_id' => $customer->id, 'license_plate' => 'B1234ABC',
        'brand' => 'Honda', 'model' => 'Vario 125', 'color' => null,
        'chassis_number' => null, 'engine_number' => null, 'notes' => null,
    ]);
});

it('edits a motorcycle without decreasing mileage or duplicating a canonical plate', function () {
    $vehicle = Vehicle::factory()->create(['license_plate' => 'B1234ABC', 'latest_mileage' => 12500]);
    Vehicle::factory()->create(['license_plate' => 'D9876XYZ']);

    $page = Livewire::actingAs(User::factory()->create(['role' => 'owner']))
        ->test(VehicleEditor::class)
        ->call('edit', $vehicle->id)
        ->set('form.license_plate', ' d 9876 xyz ')
        ->set('form.latest_mileage', 12499)
        ->call('save')
        ->assertHasErrors(['form.license_plate' => 'unique', 'form.latest_mileage' => 'min']);

    expect($vehicle->fresh()->latest_mileage)->toBe(12500);

    $page->set('form.license_plate', ' b 1234 abc ')
        ->set('form.latest_mileage', '13000')
        ->set('form.color', ' Merah ')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('editingId', null);

    expect($vehicle->fresh()->latest_mileage)->toBe(13000);
    expect($vehicle->fresh()->color)->toBe('Merah');
    expect(Vehicle::count())->toBe(2);
});

it('searches people and motorcycles with canonical identifiers and excludes archived rows', function () {
    $siti = Customer::factory()->create(['name' => 'Siti Aminah', 'phone' => '081234567890']);
    $budi = Customer::factory()->create(['name' => 'Budi Santoso']);
    $vehicle = Vehicle::factory()->for($siti)->create(['license_plate' => 'b 1234 abc', 'brand' => 'Honda', 'model' => 'Vario 125']);
    Vehicle::factory()->for($budi)->create(['license_plate' => 'D5678XYZ', 'brand' => 'Yamaha', 'model' => 'Mio']);
    $archived = Customer::factory()->create(['name' => 'Siti Arsip', 'deleted_at' => now()]);
    Vehicle::factory()->for($archived)->create(['license_plate' => 'B1234ABD', 'deleted_at' => now()]);

    $customers = Livewire::actingAs(User::factory()->create(['role' => 'admin']))->test(Customers::class);
    foreach (['siti   aminah', '0812 3456-7890', 'b 1234 abc'] as $search) {
        $customers->set('search', $search)
            ->assertSee('Siti Aminah')->assertDontSee('Budi Santoso')->assertDontSee('Siti Arsip')
            ->assertViewHas('customers', fn ($rows) => $rows->count() === 1 && $rows->first()->vehicles_count === 1);
    }
    $customers->set('search', '%')->assertViewHas('customers', fn ($rows) => $rows->isEmpty());

    $vehicles = Livewire::test(Vehicles::class);
    foreach (['b 1234 abc', 'honda', 'vario 125', 'siti aminah', '+62 812-3456-7890'] as $search) {
        $vehicles->set('search', $search)->assertSee($vehicle->license_plate)->assertDontSee('D5678XYZ')
            ->assertDontSee('B1234ABD')
            ->assertViewHas('vehicles', fn ($rows) => $rows->count() === 1 && $rows->first()->relationLoaded('customer'));
    }
    $vehicles->set('search', '_')->assertViewHas('vehicles', fn ($rows) => $rows->isEmpty());
});

it('restricts customer and vehicle management to workshop managers', function (Role $role) {
    $user = User::factory()->create(['role' => $role]);
    $customer = Customer::factory()->for($user)->create();
    $vehicle = Vehicle::factory()->for($customer)->create();
    $gate = Gate::forUser($user);

    foreach ([Customer::class, Vehicle::class] as $model) {
        expect($gate->allows('viewAny', $model))->toBe($role->managesWorkshop())
            ->and($gate->allows('create', $model))->toBe($role->managesWorkshop());
    }
    foreach ([$customer, $vehicle] as $record) {
        expect($gate->allows('view', $record))->toBe($role->managesWorkshop() || $role === Role::Customer)
            ->and($gate->allows('update', $record))->toBe($role->managesWorkshop())
            ->and($gate->allows('delete', $record))->toBe($role->managesWorkshop());
    }

    foreach ([Customers::class, Vehicles::class] as $component) {
        Livewire::actingAs($user)->test($component)->assertStatus($role->managesWorkshop() ? 200 : 403);
    }
})->with(Role::cases());

it('allows portal record viewing only for the linked customer account', function () {
    $user = User::factory()->create();
    $own = Customer::factory()->for($user)->create();
    $other = Customer::factory()->for(User::factory())->create();
    $unlinked = Customer::factory()->create();
    $ownVehicle = Vehicle::factory()->for($own)->create();
    $otherVehicle = Vehicle::factory()->for($other)->create();
    $gate = Gate::forUser($user);

    expect($gate->allows('view', $own))->toBeTrue()
        ->and($gate->allows('view', $ownVehicle))->toBeTrue();
    foreach ([$other, $otherVehicle, $unlinked] as $record) {
        expect($gate->allows('view', $record))->toBeFalse();
    }
});

it('refuses ownership transfer after a motorcycle acquires any service history', function (ServiceStatus $status) {
    $vehicle = Vehicle::factory()->create(['latest_mileage' => 1000]);
    $newOwner = Customer::factory()->create();
    $page = Livewire::actingAs(User::factory()->create(['role' => 'admin']))
        ->test(VehicleEditor::class)->call('edit', $vehicle->id);

    ServiceOrder::factory()->for($vehicle)->create(['status' => $status]);
    $page->set('form.customer_id', $newOwner->id)->call('save')->assertHasErrors(['form.customer_id']);

    expect($vehicle->fresh()->customer_id)->toBe($vehicle->customer_id);
    $page->set('form.customer_id', $vehicle->customer_id)->set('form.color', 'Biru')
        ->call('save')->assertHasNoErrors();
    expect($vehicle->fresh()->color)->toBe('Biru');
})->with([ServiceStatus::Waiting, ServiceStatus::Delivered, ServiceStatus::Cancelled]);

it('permits ownership correction only before the first service', function () {
    $vehicle = Vehicle::factory()->create();
    $newOwner = Customer::factory()->create();

    Livewire::actingAs(User::factory()->create(['role' => 'owner']))
        ->test(VehicleEditor::class)->call('edit', $vehicle->id)
        ->set('form.customer_id', $newOwner->id)->call('save')->assertHasNoErrors();

    expect($vehicle->fresh()->customer_id)->toBe($newOwner->id);
});

it('rolls back customer and vehicle writes if persistence fails partway through', function (string $component, string $model) {
    $record = $model::factory()->create();
    $page = Livewire::actingAs(User::factory()->create(['role' => 'admin']))
        ->test($component)->call('edit', $record->id)->set('form.notes', 'Tidak boleh tersimpan');
    $model::saved(fn () => throw new RuntimeException('Simulasi kegagalan setelah simpan'));

    try {
        expect(fn () => $page->call('save'))->toThrow(RuntimeException::class, 'Simulasi kegagalan setelah simpan');
        expect($record->fresh()->notes)->toBeNull();
    } finally {
        $model::flushEventListeners();
    }
})->with([[CustomerEditor::class, Customer::class], [VehicleEditor::class, Vehicle::class]]);

it('turns a duplicate arriving after validation into a field error with rollback', function (string $component, string $model, string $table, string $field) {
    $admin = User::factory()->create(['role' => 'admin']);
    $customer = Customer::factory()->create();
    $page = Livewire::actingAs($admin)->test($component)->call('create');
    if ($model === Customer::class) {
        $page->set('form.name', 'Siti')->set('form.phone', '081234567890');
    } else {
        $page->set('form.customer_id', $customer->id)->set('form.license_plate', 'B1234ABC')
            ->set('form.brand', 'Honda')->set('form.model', 'Vario');
    }
    $before = DB::table($table)->count();
    $model::creating(function ($record) use ($table) {
        DB::table($table)->insert($record->getAttributes());
    });

    try {
        $page->call('save')->assertHasErrors(['form.'.$field])->assertSet('showForm', true);
        expect(DB::table($table)->count())->toBe($before);
    } finally {
        $model::flushEventListeners();
    }
})->with([
    [CustomerEditor::class, Customer::class, 'customers', 'phone'],
    [VehicleEditor::class, Vehicle::class, 'vehicles', 'license_plate'],
]);

it('resets pagination when searching customers or vehicles', function (string $component, string $model, string $field) {
    $records = $model::factory()->count(16)->create();
    $target = $records->first();

    Livewire::actingAs(User::factory()->create(['role' => Role::Admin]))
        ->test($component)->call('setPage', 2)->assertSet('paginators.page', 2)
        ->set('search', $target->$field)->assertSet('paginators.page', 1)
        ->assertSee($target->$field)->assertSeeHtml('data-workshop-search');
})->with([
    [Customers::class, Customer::class, 'phone'],
    [Vehicles::class, Vehicle::class, 'license_plate'],
]);

it('archives a vehicle then its customer without deleting service history', function () {
    $customer = Customer::factory()->create();
    $vehicle = Vehicle::factory()->for($customer)->create();
    $order = ServiceOrder::factory()->for($vehicle)->for($customer)->create(['status' => ServiceStatus::Delivered]);
    $admin = User::factory()->create(['role' => Role::Admin]);

    Livewire::actingAs($admin)->test(Vehicles::class)->assertSeeHtml('wire:confirm=')
        ->call('archive', $vehicle->id)
        ->assertHasNoErrors()
        ->assertDontSee($vehicle->license_plate)->assertSee('Kendaraan berhasil diarsipkan.');
    Livewire::test(Customers::class)->assertSeeHtml('wire:confirm=')
        ->call('archive', $customer->id)->assertHasNoErrors()
        ->assertDontSee($customer->name)->assertSee('Pelanggan berhasil diarsipkan.');

    $this->assertSoftDeleted($vehicle);
    $this->assertSoftDeleted($customer);
    expect($order->fresh()->vehicle->is($vehicle))->toBeTrue()
        ->and($order->fresh()->customer->is($customer))->toBeTrue();
    $this->assertDatabaseCount('service_orders', 1);
});

it('blocks customer archive while a nonarchived vehicle exists', function () {
    $vehicle = Vehicle::factory()->create();
    $customer = $vehicle->customer;

    Livewire::actingAs(User::factory()->create(['role' => Role::Admin]))
        ->test(Customers::class)->call('archive', $customer->id)
        ->assertHasErrors(['archive'])->assertSee('Arsipkan semua kendaraan pelanggan terlebih dahulu.')
        ->assertSeeHtml('role="alert"');

    $this->assertNotSoftDeleted($customer);
    $this->assertNotSoftDeleted($vehicle);
});

it('blocks customer archive for every nonterminal service status even on an archived vehicle', function (ServiceStatus $status) {
    $vehicle = Vehicle::factory()->create();
    $customer = $vehicle->customer;
    ServiceOrder::factory()->for($vehicle)->for($customer)->create(['status' => $status]);
    $vehicle->delete();

    Livewire::actingAs(User::factory()->create(['role' => Role::Owner]))
        ->test(Customers::class)->call('archive', $customer->id)->assertHasErrors(['archive'])
        ->assertSee('Pelanggan masih memiliki servis aktif.');
    $this->assertNotSoftDeleted($customer);
})->with(array_filter(ServiceStatus::cases(), fn ($status) => ! in_array($status, [ServiceStatus::Delivered, ServiceStatus::Cancelled], true)));

it('blocks vehicle archive for every nonterminal service status', function (ServiceStatus $status) {
    $vehicle = Vehicle::factory()->create();
    ServiceOrder::factory()->for($vehicle)->for($vehicle->customer)->create(['status' => $status]);

    Livewire::actingAs(User::factory()->create(['role' => Role::Admin]))
        ->test(Vehicles::class)->call('archive', $vehicle->id)->assertHasErrors(['archive'])
        ->assertSee('Kendaraan masih memiliki servis aktif.')->assertSeeHtml('role="alert"');
    $this->assertNotSoftDeleted($vehicle);
})->with(array_filter(ServiceStatus::cases(), fn ($status) => ! in_array($status, [ServiceStatus::Delivered, ServiceStatus::Cancelled], true)));

it('permits archive without history or with cancelled history', function (bool $withHistory) {
    $vehicle = Vehicle::factory()->create();
    $customer = $vehicle->customer;
    if ($withHistory) {
        ServiceOrder::factory()->for($vehicle)->for($customer)->create(['status' => ServiceStatus::Cancelled]);
    }
    Livewire::actingAs(User::factory()->create(['role' => Role::Owner]))
        ->test(Vehicles::class)->call('archive', $vehicle->id)->assertHasNoErrors();
    Livewire::test(Customers::class)->call('archive', $customer->id)->assertHasNoErrors();
    $this->assertSoftDeleted($vehicle);
    $this->assertSoftDeleted($customer);
})->with([false, true]);

it('denies direct customer and vehicle mutations after a manager role changes', function (Role $role, string $action) {
    $vehicle = Vehicle::factory()->create();
    $admin = User::factory()->create(['role' => Role::Admin]);
    $targets = $action === 'archive'
        ? [[Customers::class, $vehicle->customer], [Vehicles::class, $vehicle]]
        : [[CustomerEditor::class, $vehicle->customer], [VehicleEditor::class, $vehicle]];
    foreach ($targets as [$component, $record]) {
        $admin->forceFill(['role' => Role::Admin])->save();
        $page = Livewire::actingAs($admin)->test($component);
        if ($action !== 'archive') {
            $page->call('edit', $record->id);
        }
        $admin->forceFill(['role' => $role])->save();
        Livewire::actingAs($admin->fresh());
        $page->call($action, $record->id)->assertForbidden();
        $this->assertNotSoftDeleted($record);
    }
})->with([Role::Customer, Role::Mechanic])->with(['archive', 'save', 'edit', 'create']);

it('preserves customer and vehicle history relations after masters are archived', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->for($user)->create();
    $vehicle = Vehicle::factory()->for($customer)->create();
    $order = ServiceOrder::factory()->for($vehicle)->for($customer)->create(['status' => ServiceStatus::Delivered]);

    $user->delete();
    $vehicle->delete();
    $customer->delete();

    expect($customer->fresh()->user->is($user))->toBeTrue()
        ->and($customer->fresh()->serviceOrders->sole()->is($order))->toBeTrue()
        ->and($vehicle->fresh()->customer->is($customer))->toBeTrue()
        ->and($vehicle->fresh()->serviceOrders->sole()->is($order))->toBeTrue()
        ->and($order->fresh()->vehicle->is($vehicle))->toBeTrue()
        ->and($order->fresh()->customer->is($customer))->toBeTrue();
});
