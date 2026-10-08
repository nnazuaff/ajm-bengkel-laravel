<?php

use App\Actions\ManageReceipt;
use App\Enums\Role;
use App\Livewire\WorkshopSettings;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\WorkshopSetting;
use Livewire\Livewire;

it('provides one stable workshop identity without guessed contact details', function () {
    $setting = WorkshopSetting::current();
    expect($setting->id)->toBe(1)
        ->and($setting->only(['name', 'phone', 'address', 'receipt_footer']))
        ->toBe(['name' => 'AJM Bengkel', 'phone' => '', 'address' => '', 'receipt_footer' => ''])
        ->and(WorkshopSetting::current()->id)->toBe(1);
    $this->assertDatabaseCount('workshop_settings', 1);
});

it('persists validated workshop settings for owners and audits safely', function () {
    $owner = User::factory()->create(['role' => Role::Owner]);
    Livewire::actingAs($owner)->test(WorkshopSettings::class)
        ->set('name', 'Bengkel Baru')->set('phone', '081234567890')
        ->set('address', 'Alamat bengkel')->set('receipt_footer', 'Terima kasih')
        ->call('save')->assertHasNoErrors()->assertSee('Identitas bengkel disimpan');
    expect(WorkshopSetting::current()->name)->toBe('Bengkel Baru')
        ->and(AuditLog::where('action', 'workshop.settings_updated')->count())->toBe(1);
    Livewire::actingAs($owner)->test(WorkshopSettings::class)->set('name', '')->call('save')->assertHasErrors(['name']);
});

it('denies workshop settings to non owners', function (Role $role) {
    Livewire::actingAs(User::factory()->create(['role' => $role]))
        ->test(WorkshopSettings::class)->assertForbidden();
})->with([Role::Admin, Role::Mechanic, Role::Customer]);

it('rolls settings back when the audit fails', function () {
    $owner = User::factory()->create(['role' => Role::Owner]);
    WorkshopSetting::current();
    AuditLog::creating(fn () => throw new RuntimeException('Audit unavailable'));
    try {
        Livewire::actingAs($owner)->test(WorkshopSettings::class)->set('name', 'Changed')->call('save');
        test()->fail('Expected audit failure');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('Audit unavailable');
    } finally {
        AuditLog::flushEventListeners();
    }
    expect(WorkshopSetting::current()->name)->toBe('AJM Bengkel');
});

it('rechecks owner permission before persisting hydrated settings', function () {
    $owner = User::factory()->create(['role' => Role::Owner]);
    $component = Livewire::actingAs($owner)->test(WorkshopSettings::class)->set('name', 'Untrusted');
    User::whereKey($owner->id)->update(['role' => 'admin']);
    $component->call('save')->assertForbidden();
    expect(WorkshopSetting::current()->name)->toBe('AJM Bengkel');
});

it('keeps finalized receipt identity when workshop settings change', function () {
    $owner = User::factory()->create(['role' => Role::Owner]);
    WorkshopSetting::current()->update(['name' => 'Workshop Before', 'phone' => '081234567890', 'address' => 'Address Before', 'receipt_footer' => 'Footer Before']);
    $receipt = app(ManageReceipt::class)->create($owner, ['items' => [
        ['type' => 'custom', 'description' => 'Jasa', 'quantity' => 1, 'unit_price' => '100.00'],
    ]]);
    $receipt = app(ManageReceipt::class)->finalize($owner, $receipt);
    Livewire::actingAs($owner)->test(WorkshopSettings::class)->set('name', 'Workshop After')->call('save')->assertHasNoErrors();
    expect($receipt->fresh()->workshop_snapshot['name'])->toBe('Workshop Before')
        ->and($receipt->fresh()->workshop_snapshot['phone'])->toBe('081234567890')
        ->and($receipt->fresh()->workshop_snapshot['address'])->toBe('Address Before')
        ->and($receipt->fresh()->workshop_snapshot['receipt_footer'])->toBe('Footer Before');
});
