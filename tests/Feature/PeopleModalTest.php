<?php

use App\Enums\Role;
use App\Livewire\CustomerAccount;
use App\Livewire\CustomerEditor;
use App\Livewire\Customers;
use App\Livewire\VehicleEditor;
use App\Livewire\Vehicles;
use App\Models\Customer;
use App\Models\User;
use App\Models\Vehicle;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

it('retains invalid editor drafts then clears dismissal state before reopening', function (string $editor, string $model, string $field, string $closed) {
    $record = $model::factory()->create();
    Livewire::actingAs(User::factory()->create(['role' => Role::Admin]))->test($editor)
        ->assertSet('showForm', false)->call('edit', $record->id)->assertSet('editingId', $record->id)
        ->set('form.'.$field, '')->call('save')->assertHasErrors('form.'.$field)
        ->assertSet('showForm', true)->assertSet('editingId', $record->id)
        ->set('showForm', false)->assertSet('editingId', null)->assertHasNoErrors()
        ->assertDispatched($closed)->call('create')->assertSet('form.'.$field, '')
        ->assertSet('showForm', true)->assertSet('editingId', null)->assertHasNoErrors()
        ->call('closeForm')->assertSet('showForm', false)->assertDispatched($closed);
})->with([
    [CustomerEditor::class, Customer::class, 'name', 'customer-editor-closed'],
    [VehicleEditor::class, Vehicle::class, 'brand', 'vehicle-editor-closed'],
]);

it('locks editor identities against client changes', function (string $editor, string $model) {
    $record = $model::factory()->create();
    $other = $model::factory()->create();
    expect(fn () => Livewire::actingAs(User::factory()->create(['role' => Role::Admin]))->test($editor)
        ->call('edit', $record->id)->set('editingId', $other->id))
        ->toThrow(CannotUpdateLockedPropertyException::class);
})->with([[CustomerEditor::class, Customer::class], [VehicleEditor::class, Vehicle::class]]);

it('refreshes lists and resets pagination after child saves', function (string $list, string $model, string $field, string $event) {
    $record = $model::factory()->create();
    Livewire::actingAs(User::factory()->create(['role' => Role::Admin]))->test($list)
        ->call('setPage', 2)->dispatch($event)->assertSet('paginators.page', 1)
        ->assertSee($record->$field)->assertSee('berhasil');
})->with([
    [Customers::class, Customer::class, 'name', 'customer-saved'],
    [Customers::class, Customer::class, 'name', 'customer-account-saved'],
    [Vehicles::class, Vehicle::class, 'license_plate', 'vehicle-saved'],
]);

it('denies direct editor mounts to nonmanagers and guests', function (?Role $role) {
    if ($role) {
        $this->actingAs(User::factory()->create(['role' => $role]));
    }
    foreach ([CustomerEditor::class, VehicleEditor::class, CustomerAccount::class] as $editor) {
        Livewire::test($editor)->assertForbidden();
    }
})->with([Role::Customer, Role::Mechanic, null]);

it('rejects hydrated editors when the database actor role changes', function (string $editor) {
    $actor = User::factory()->create(['role' => Role::Admin]);
    $modal = Livewire::actingAs($actor)->test($editor);
    User::whereKey($actor->id)->update(['role' => Role::Customer]);
    $modal->call('$refresh')->assertForbidden();
})->with([CustomerEditor::class, VehicleEditor::class, CustomerAccount::class]);

it('resets account target choices acknowledgement and errors on dismissal', function () {
    $customer = Customer::factory()->create();
    $other = Customer::factory()->create();
    $account = User::factory()->create();
    Livewire::actingAs(User::factory()->create(['role' => Role::Admin]))->test(CustomerAccount::class)
        ->assertSet('showForm', false)->assertSet('customerId', null)
        ->dispatch('open-customer-account', id: $customer->id)->assertSet('showForm', true)
        ->set('userId', (string) $account->id)->call('save')->assertHasErrors('ownershipVerified')
        ->assertSet('customerId', $customer->id)->assertSet('showForm', true)
        ->set('search', 'draft')->set('ownershipVerified', true)->set('showForm', false)
        ->assertSet('customerId', null)->assertSet('userId', '')->assertSet('search', '')
        ->assertSet('ownershipVerified', false)->assertHasNoErrors()->assertDispatched('customer-account-closed')
        ->dispatch('open-customer-account', id: $other->id)->assertSet('customerId', $other->id)
        ->assertSet('ownershipVerified', false)->assertSet('userId', '')->assertHasNoErrors();
    expect($customer->fresh()->user_id)->toBeNull();
});

it('closes the account modal only after a valid audited link', function () {
    $customer = Customer::factory()->create();
    $account = User::factory()->create();
    Livewire::actingAs(User::factory()->create(['role' => Role::Owner]))->test(CustomerAccount::class)
        ->call('openForm', $customer->id)->set('userId', (string) $account->id)
        ->set('ownershipVerified', true)->call('save')->assertHasNoErrors()
        ->assertSet('showForm', false)->assertSet('customerId', null)->assertSet('userId', '')
        ->assertDispatched('customer-account-saved')->assertDispatched('customer-account-closed');
    expect($customer->fresh()->user_id)->toBe($account->id);
    $this->assertDatabaseCount('audit_logs', 1);
});

it('refuses to load or save archived editor targets', function (string $editor, string $model) {
    $record = $model::factory()->create();
    $modal = Livewire::actingAs(User::factory()->create(['role' => Role::Admin]))->test($editor)
        ->call('edit', $record->id);
    $record->delete();
    $modal->call('save')->assertNotFound();
    Livewire::test($editor)->call('edit', $record->id)->assertNotFound();
})->with([[CustomerEditor::class, Customer::class], [VehicleEditor::class, Vehicle::class]]);

it('opens customer work in separate targeted modal components', function () {
    $customer = Customer::factory()->create();
    Livewire::actingAs(User::factory()->create(['role' => Role::Admin]))->test(Customers::class)
        ->assertDontSeeHtml('wire:model="form.name"')
        ->assertDontSee('Akun portal pelanggan')
        ->call('create')->assertDispatchedTo(CustomerEditor::class, 'create-customer')
        ->call('edit', $customer->id)->assertDispatchedTo(CustomerEditor::class, 'edit-customer', id: $customer->id)
        ->call('openAccount', $customer->id)->assertDispatchedTo(CustomerAccount::class, 'open-customer-account', id: $customer->id);
});

it('opens vehicle work in a separate targeted modal component', function () {
    $vehicle = Vehicle::factory()->create();
    Livewire::actingAs(User::factory()->create(['role' => Role::Admin]))->test(Vehicles::class)
        ->assertDontSeeHtml('wire:model="form.license_plate"')
        ->call('create')->assertDispatchedTo(VehicleEditor::class, 'create-vehicle')
        ->call('edit', $vehicle->id)->assertDispatchedTo(VehicleEditor::class, 'edit-vehicle', id: $vehicle->id);
});
