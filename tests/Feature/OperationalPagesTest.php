<?php

use App\Enums\Role;
use App\Livewire\AuditLogs;
use App\Livewire\Mechanics;
use App\Livewire\StaffEditor;
use App\Models\AuditLog;
use App\Models\User;
use Livewire\Livewire;

it('lets owners manage staff without exposing customer accounts', function () {
    $owner = User::factory()->create(['role' => Role::Owner]);
    $mechanic = User::factory()->create(['role' => Role::Mechanic, 'name' => 'Mekanik Satu']);
    $customer = User::factory()->create(['name' => 'Customer Secret']);
    Livewire::actingAs($owner)->test(Mechanics::class)->assertSee('Mekanik Satu')->assertDontSee($customer->name);
    Livewire::actingAs($owner)->test(StaffEditor::class)->call('edit', $mechanic->id)->set('name', 'Mekanik Baru')->set('role', 'admin')
        ->call('save')->assertHasNoErrors()->assertSet('showForm', false)->assertDispatched('staff-updated');
    expect($mechanic->fresh()->role)->toBe(Role::Admin);
});

it('lets admins read only active mechanic names and denies staff mutation', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $mechanic = User::factory()->create(['role' => Role::Mechanic, 'name' => 'Visible Mechanic']);
    $other = User::factory()->create(['role' => Role::Admin, 'name' => 'Other Admin']);
    $archived = User::factory()->create(['role' => Role::Mechanic, 'name' => 'Archived Mechanic']);
    $archived->delete();
    Livewire::actingAs($admin)->test(Mechanics::class)->assertSee('Visible Mechanic')->assertDontSee($mechanic->email)
        ->assertDontSee('Other Admin')->assertDontSee('Archived Mechanic')->call('edit', $mechanic->id)->assertForbidden();
    Livewire::actingAs($admin)->test(Mechanics::class)->call('create')->assertForbidden();
    Livewire::actingAs($admin)->test(Mechanics::class)->call('deactivate', $mechanic->id)->assertForbidden();
});

it('provisions staff through owner UI and clears password state', function () {
    $owner = User::factory()->create(['role' => Role::Owner]);
    Livewire::actingAs($owner)->test(StaffEditor::class)->call('create')
        ->set('name', 'Staf UI')->set('email', 'ui-staff@example.test')->set('role', 'mechanic')
        ->set('password', 'Test-only!Password123')->set('password_confirmation', 'Test-only!Password123')
        ->call('save')->assertHasNoErrors()->assertSet('password', '')->assertSet('password_confirmation', '')
        ->assertSet('showForm', false)->assertDispatched('staff-updated');
    expect(User::where('email', 'ui-staff@example.test')->firstOrFail()->role)->toBe(Role::Mechanic);
});

it('supports staff search and deactivation with visible errors', function () {
    $owner = User::factory()->create(['role' => Role::Owner]);
    $mechanic = User::factory()->create(['role' => Role::Mechanic, 'name' => 'Found Staff']);
    Livewire::actingAs($owner)->test(Mechanics::class)->set('search', 'Found')->assertSee('Found Staff')
        ->call('deactivate', $mechanic->id)->assertHasNoErrors()->assertDontSee('Found Staff');
    Livewire::actingAs($owner)->test(Mechanics::class)->call('deactivate', $owner->id)->assertHasErrors(['deactivate']);
});

it('denies operational pages to mechanics and customers', function (Role $role) {
    $actor = User::factory()->create(['role' => $role]);
    Livewire::actingAs($actor)->test(Mechanics::class)->assertForbidden();
    Livewire::actingAs($actor)->test(AuditLogs::class)->assertForbidden();
})->with([Role::Mechanic, Role::Customer]);

it('filters audit by safe action date actor including archived actors without raw contexts', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $actor = User::factory()->create(['role' => Role::Mechanic, 'name' => 'Archived Actor']);
    $audit = AuditLog::create(['actor_id' => $actor->id, 'action' => 'staff.updated', 'entity_type' => User::class,
        'entity_id' => $actor->id, 'context' => ['password' => 'SECRET-PASSWORD', 'token' => 'SECRET-TOKEN']]);
    $audit->forceFill(['created_at' => '2026-10-04 12:00:00'])->save();
    AuditLog::create(['actor_id' => $admin->id, 'action' => 'unknown.secret', 'entity_type' => User::class,
        'entity_id' => $admin->id, 'context' => ['private' => 'SECRET-CONTEXT']]);
    $actor->delete();
    Livewire::actingAs($admin)->test(AuditLogs::class)->set('action', 'staff.updated')->set('actorId', (string) $actor->id)
        ->set('from', '2026-10-04')->set('to', '2026-10-04')->assertSee('Archived Actor')
        ->assertSee('Staf diperbarui')->assertDontSee('SECRET-PASSWORD')->assertDontSee('SECRET-TOKEN')
        ->assertDontSee('SECRET-CONTEXT')->assertDontSee('unknown.secret')
        ->set('from', '2026-10-05')->set('to', '2026-10-06')->assertSee('Tidak ada aktivitas');
});

it('serves integrated operational routes with role boundaries', function () {
    $owner = User::factory()->create(['role' => Role::Owner]);
    foreach (['/mechanics', '/audit-log', '/reports', '/workshop-settings'] as $uri) {
        $this->actingAs($owner)->get($uri)->assertOk();
    }
    $admin = User::factory()->create(['role' => Role::Admin]);
    foreach (['/mechanics', '/audit-log', '/reports'] as $uri) {
        $this->actingAs($admin)->get($uri)->assertOk();
    }
    $this->actingAs($admin)->get('/workshop-settings')->assertForbidden();
});

it('shows actual finance audit events without their secret contexts', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    foreach (['receipt.draft_updated', 'payment.created'] as $action) {
        AuditLog::create(['actor_id' => $admin->id, 'action' => $action, 'entity_type' => 'Receipt', 'entity_id' => 1, 'context' => ['reference' => 'PRIVATE-REFERENCE']]);
    }
    Livewire::actingAs($admin)->test(AuditLogs::class)->assertSee('Draf bon diperbarui')->assertSee('Pembayaran dicatat')->assertDontSee('PRIVATE-REFERENCE');
});

it('includes inventory archival and category events in operational audit', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    foreach (['inventory.archived', 'inventory_category.created'] as $action) {
        AuditLog::create(['actor_id' => $admin->id, 'action' => $action, 'entity_type' => 'InventoryItem', 'entity_id' => 1]);
    }
    Livewire::actingAs($admin)->test(AuditLogs::class)->assertSee('Barang diarsipkan')->assertSee('Kategori barang dibuat');
});
