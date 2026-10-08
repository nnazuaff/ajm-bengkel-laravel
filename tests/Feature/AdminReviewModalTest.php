<?php

use App\Enums\Role;
use App\Livewire\BookingReview;
use App\Livewire\Bookings;
use App\Livewire\Mechanics;
use App\Livewire\StaffEditor;
use App\Models\Booking;
use App\Models\ServiceJob;
use App\Models\ServiceOrder;
use App\Models\User;
use Livewire\Drawer\Utils;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

it('dispatches review to the child without storing booking form state in the queue', function () {
    $booking = Booking::factory()->create();
    $page = Livewire::actingAs(User::factory()->create(['role' => Role::Admin]))->test(Bookings::class);
    $page->call('openBooking', $booking->id)->assertDispatchedTo(BookingReview::class, 'open-booking-review', id: $booking->id);
    expect(Utils::getPublicPropertiesDefinedOnSubclass($page->instance()))->not->toHaveKeys(['detail', 'selectedBookingId']);
});

it('opens a lazy review and clears it on dismissal', function () {
    $booking = Booking::factory()->create();
    Livewire::actingAs(User::factory()->create(['role' => Role::Admin]))->test(BookingReview::class)
        ->assertDontSee('Keluhan pelanggan')->dispatch('open-booking-review', id: $booking->id)
        ->assertSet('showForm', true)->assertSee('Keluhan pelanggan')
        ->assertSeeHtml('wire:click="closeBooking"')->assertDontSeeHtml('data-flux-modal-close')
        ->set('detail.admin_notes', 'Unsaved')->set('showForm', false)
        ->assertSet('selectedBookingId', null)->assertSet('detail.admin_notes', '')
        ->assertDispatched('booking-review-closed', id: $booking->id);
});

it('locks review and staff target identifiers', function () {
    $owner = User::factory()->create(['role' => Role::Owner]);
    foreach ([[BookingReview::class, 'selectedBookingId'], [StaffEditor::class, 'editingId']] as [$component, $property]) {
        expect(fn () => Livewire::actingAs($owner)->test($component)->set($property, 999))
            ->toThrow(CannotUpdateLockedPropertyException::class);
    }
});

it('keeps staff secrets out of the list and clears modal secrets on dismissal and failed saves', function () {
    $owner = User::factory()->create(['role' => Role::Owner]);
    $page = Livewire::actingAs($owner)->test(Mechanics::class)->call('create')
        ->assertDispatchedTo(StaffEditor::class, 'open-staff-create');
    expect(Utils::getPublicPropertiesDefinedOnSubclass($page->instance()))->not->toHaveKeys(['password', 'password_confirmation', 'editingId', 'name', 'email', 'role']);
    Livewire::actingAs($owner)->test(StaffEditor::class)->call('create')
        ->set('password', 'Test-only!Password123')->set('password_confirmation', 'Test-only!Password123')
        ->call('save')->assertHasErrors(['name', 'email'])->assertSet('showForm', true)
        ->assertSet('password', '')->assertSet('password_confirmation', '')
        ->set('password', 'UnsavedSecret')->set('showForm', false)
        ->assertSet('password', '')->assertSet('password_confirmation', '')->assertDispatched('staff-editor-closed');
});

it('refreshes lists after child completion without losing booking filters', function () {
    $owner = User::factory()->create(['role' => Role::Owner]);
    Livewire::actingAs($owner)->test(Bookings::class)->set('statusFilter', 'arrived')
        ->dispatch('booking-updated', message: 'Servis berhasil dibuat: SRV-test.')
        ->assertSet('statusFilter', 'arrived')->assertSee('Servis berhasil dibuat: SRV-test.');
    $page = Livewire::actingAs($owner)->test(Mechanics::class)->set('search', 'New Staff');
    User::factory()->create(['role' => Role::Mechanic, 'name' => 'New Staff']);
    $page->dispatch('staff-updated')->assertSee('New Staff')->assertSet('search', 'New Staff');
});

it('shows stale conversion errors outside the reception branch', function () {
    $booking = Booking::factory()->create(['status' => 'arrived']);
    $page = Livewire::actingAs(User::factory()->create(['role' => Role::Admin]))->test(BookingReview::class)
        ->call('openBooking', $booking->id);
    $booking->update(['status' => 'cancelled', 'admin_notes' => 'Batal']);
    $page->call('convert')->assertHasErrors('detail.status')->assertSet('showForm', true)
        ->assertSee('Booking harus sudah datang dan belum dikonversi.')
        ->assertDontSee('Saya sudah memverifikasi identitas');
});

it('preserves role loss protections in the staff editor', function () {
    $owner = User::factory()->create(['role' => Role::Owner]);
    Livewire::actingAs($owner)->test(StaffEditor::class)->call('edit', $owner->id)
        ->set('role', 'admin')->call('save')->assertHasErrors('role')->assertSet('showForm', true)
        ->assertSee('Minimal satu pemilik aktif harus dipertahankan.');
    $mechanic = User::factory()->create(['role' => Role::Mechanic]);
    $order = ServiceOrder::factory()->create(['status' => 'in_progress']);
    ServiceJob::factory()->create(['service_order_id' => $order->id, 'mechanic_id' => $mechanic->id, 'status' => 'pending']);
    Livewire::actingAs($owner)->test(StaffEditor::class)->call('edit', $mechanic->id)
        ->set('role', 'admin')->call('save')->assertHasErrors('role')->assertSet('showForm', true)
        ->assertSee('Mekanik masih memiliki servis aktif.');
    expect($owner->fresh()->role)->toBe(Role::Owner)->and($mechanic->fresh()->role)->toBe(Role::Mechanic);
});

it('closes safely after an eligible owner changes their own role', function () {
    $owner = User::factory()->create(['role' => Role::Owner]);
    User::factory()->create(['role' => Role::Owner]);
    Livewire::actingAs($owner)->test(StaffEditor::class)->call('edit', $owner->id)
        ->set('role', 'admin')->call('save')->assertHasNoErrors()->assertSet('showForm', false)
        ->assertSet('editingId', null)->assertDispatched('staff-updated');
    expect($owner->fresh()->role)->toBe(Role::Admin);
});

it('denies direct modal requests and rechecks changed or archived actors', function () {
    foreach ([Role::Customer, Role::Mechanic] as $role) {
        $actor = User::factory()->create(['role' => $role]);
        Livewire::actingAs($actor)->test(BookingReview::class)->assertForbidden();
        Livewire::actingAs($actor)->test(StaffEditor::class)->assertForbidden();
    }
    Livewire::actingAs(User::factory()->create(['role' => Role::Admin]))->test(StaffEditor::class)->assertForbidden();
    foreach ([BookingReview::class, StaffEditor::class] as $component) {
        $actor = User::factory()->create(['role' => Role::Owner]);
        $page = Livewire::actingAs($actor)->test($component);
        User::whereKey($actor->id)->update(['role' => Role::Mechanic]);
        $page->set('showForm', true)->assertForbidden();
        $actor = User::factory()->create(['role' => Role::Owner]);
        $page = Livewire::actingAs($actor)->test($component);
        $actor->delete();
        $page->set('showForm', true)->assertForbidden();
    }
});
