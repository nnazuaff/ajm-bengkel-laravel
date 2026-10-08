<?php

use App\Enums\ServiceStatus;
use App\Livewire\ServiceJobs;
use App\Livewire\ServiceParts;
use App\Livewire\ServicePhotos;
use App\Livewire\Services;
use App\Livewire\WalkInIntake;
use App\Models\ServiceOrder;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

it('opens a dedicated service page with protected embedded work modules', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $order = ServiceOrder::factory()->create(['complaint' => 'Keluhan khusus halaman detail.']);

    Livewire::actingAs($admin)->test(Services::class, ['serviceOrder' => $order->id])
        ->assertSet('selectedOrderId', $order->id)
        ->assertSet('detailPage', true)
        ->assertViewIs('livewire.service-detail')
        ->assertSee($order->complaint)
        ->assertSeeLivewire(ServiceJobs::class)
        ->assertSeeLivewire(ServiceParts::class)
        ->assertSeeLivewire(ServicePhotos::class)
        ->assertDontSee('Cari servis')
        ->assertDontSee('Terima walk-in');

    $this->actingAs($admin)->get(route('services.detail', $order))
        ->assertOk()->assertSee($order->complaint);
});

it('records diagnosis status and mechanic on the dedicated service page', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $mechanic = User::factory()->create(['role' => 'mechanic']);
    $order = ServiceOrder::factory()->create(['status' => ServiceStatus::Inspection, 'complaint' => 'Rem belakang berbunyi.']);

    Livewire::actingAs($admin)->test(Services::class, ['serviceOrder' => $order->id])

        ->assertSet('selectedOrderId', $order->id)
        ->assertSee('Keluhan pelanggan')
        ->assertSee('Rem belakang berbunyi.')
        ->set('detail.diagnosis', 'Kampas rem tipis.')
        ->set('detail.status', 'approved')
        ->set('detail.mechanic_id', (string) $mechanic->id)
        ->call('saveOrder')
        ->assertHasNoErrors()
        ->assertSee('berhasil diperbarui')
        ->assertSet('detail.status', 'approved');

    expect($order->fresh()->status)->toBe(ServiceStatus::Approved)
        ->and($order->fresh()->diagnosis)->toBe('Kampas rem tipis.')
        ->and($order->fresh()->complaint)->toBe('Rem belakang berbunyi.')
        ->and($order->fresh()->mechanic_id)->toBe($mechanic->id);
});

it('lets the assigned mechanic finish work without changing assignments', function () {
    $mechanic = User::factory()->create(['role' => 'mechanic']);
    $order = ServiceOrder::factory()->create(['mechanic_id' => $mechanic->id, 'status' => ServiceStatus::InProgress]);

    Livewire::actingAs($mechanic)->test(Services::class, ['serviceOrder' => $order->id])

        ->assertViewHas('allowedStatuses', fn ($statuses) => array_map(fn ($status) => $status->value, $statuses) === ['in_progress', 'waiting_part', 'completed'])
        ->set('detail.diagnosis', 'Kampas rem tipis; pemeriksaan selesai.')
        ->set('detail.status', 'completed')
        ->call('saveOrder')
        ->assertHasNoErrors();

    expect($order->fresh()->status)->toBe(ServiceStatus::Completed)
        ->and($order->fresh()->completed_at)->not->toBeNull()
        ->and($order->fresh()->mechanic_id)->toBe($mechanic->id);
});

it('rejects stale assignments and direct save attempts against another mechanic order', function () {
    $mechanic = User::factory()->create(['role' => 'mechanic']);
    $other = User::factory()->create(['role' => 'mechanic']);
    $order = ServiceOrder::factory()->create(['mechanic_id' => $mechanic->id]);
    $page = Livewire::actingAs($mechanic)->test(Services::class, ['serviceOrder' => $order->id]);
    $order->update(['mechanic_id' => $other->id]);
    $page->call('saveOrder')->assertForbidden();

    $component = new Services;
    $component->selectedOrderId = $order->id;
    expect(fn () => $component->saveOrder())->toThrow(AuthorizationException::class);
    expect($order->fresh()->diagnosis)->toBeNull();
});

it('keeps diagnosis and status validation errors visible in the detail panel', function () {
    $order = ServiceOrder::factory()->create(['status' => ServiceStatus::Inspection]);
    $page = Livewire::actingAs(User::factory()->create(['role' => 'admin']))->test(Services::class, ['serviceOrder' => $order->id])
        ->set('detail.status', 'approved')
        ->call('saveOrder')->assertHasErrors(['detail.diagnosis'])->assertSee('Catat diagnosis');
    $page->set('detail.diagnosis', 'Kampas tipis.')->set('detail.status', 'delivered')
        ->call('saveOrder')->assertHasErrors(['detail.status']);
    expect($order->fresh()->status)->toBe(ServiceStatus::Inspection)->and($order->fresh()->diagnosis)->toBeNull();
});

it('shows the save error when an order was closed by another admin', function () {
    $order = ServiceOrder::factory()->create();
    $page = Livewire::actingAs(User::factory()->create(['role' => 'admin']))->test(Services::class, ['serviceOrder' => $order->id]);
    $order->update(['status' => ServiceStatus::Cancelled, 'notes' => 'Pelanggan membatalkan.']);
    $page->call('saveOrder')->assertHasErrors(['detail.status'])
        ->assertSee('Order yang sudah ditutup tidak dapat diubah.');
});

it('denies mechanic intake cancellation approval and reassignment requests', function () {
    $mechanic = User::factory()->create(['role' => 'mechanic']);
    $other = User::factory()->create(['role' => 'mechanic']);
    $order = ServiceOrder::factory()->create(['mechanic_id' => $mechanic->id, 'status' => ServiceStatus::Inspection]);
    $page = Livewire::actingAs($mechanic)->test(Services::class, ['serviceOrder' => $order->id])
        ->set('detail.diagnosis', 'Kampas tipis.')->set('detail.status', 'approved')
        ->call('saveOrder')->assertHasErrors(['detail.status']);
    $page->set('detail.status', 'cancelled')->set('detail.notes', 'Tidak dilanjutkan.')
        ->call('saveOrder')->assertHasErrors(['detail.status']);
    $page->set('detail.status', 'inspection')->set('detail.mechanic_id', (string) $other->id)
        ->call('saveOrder')->assertHasErrors(['detail.mechanic_id']);
    expect($order->fresh()->mechanic_id)->toBe($mechanic->id)
        ->and($order->fresh()->status)->toBe(ServiceStatus::Inspection);
    Livewire::actingAs($mechanic)->test(WalkInIntake::class)->assertForbidden();
});

it('restricts detail routes to managers and the assigned mechanic', function () {
    $mechanic = User::factory()->create(['role' => 'mechanic']);
    $other = User::factory()->create(['role' => 'mechanic']);
    $customer = User::factory()->create(['role' => 'customer']);
    $order = ServiceOrder::factory()->create(['mechanic_id' => $mechanic->id]);

    $this->get(route('services.detail', $order))->assertRedirect(route('login'));
    $this->actingAs($customer)->get(route('services.detail', $order))->assertForbidden();
    $this->actingAs($other)->get(route('services.detail', $order))->assertForbidden();
    $this->actingAs($mechanic)->get(route('services.detail', $order))->assertOk();
    Livewire::actingAs($other)->test(Services::class, ['serviceOrder' => $order->id])->assertForbidden();
    Livewire::actingAs($mechanic)->test(Services::class, ['serviceOrder' => 999999])->assertNotFound();
});

it('locks the service detail page flag against browser tampering', function () {
    $order = ServiceOrder::factory()->create();
    $page = Livewire::actingAs(User::factory()->create(['role' => 'admin']))
        ->test(Services::class, ['serviceOrder' => $order->id]);
    expect(fn () => $page->set('detailPage', false))
        ->toThrow(CannotUpdateLockedPropertyException::class);
});
