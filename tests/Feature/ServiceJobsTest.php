<?php

use App\Actions\SaveServiceJob;
use App\Enums\ServiceJobStatus;
use App\Livewire\ServiceJobs;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\ServiceJob;
use App\Models\ServiceOrder;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

it('stores a custom performed job and decimal labor snapshot separately from diagnosis', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $mechanic = User::factory()->create(['role' => 'mechanic']);
    $order = ServiceOrder::factory()->create(['diagnosis' => 'Kampas tipis.']);
    $job = app(SaveServiceJob::class)->save($admin, $order, [
        'name' => 'Bersihkan rem', 'description' => 'Bersihkan tromol dan cek kampas.',
        'mechanic_id' => (string) $mechanic->id, 'labor_price' => '123456789012.34',
        'status' => 'pending', 'notes' => 'Pekerjaan khusus.',
    ]);

    expect($job->fresh()->labor_price)->toBe('123456789012.34')
        ->and($job->status)->toBe(ServiceJobStatus::Pending)
        ->and($job->service_order_id)->toBe($order->id)
        ->and($job->mechanic_id)->toBe($mechanic->id)
        ->and($job->description)->toBe('Bersihkan tromol dan cek kampas.')
        ->and($order->fresh()->diagnosis)->toBe('Kampas tipis.')
        ->and(AuditLog::where('action', 'service_job.created')->count())->toBe(1);
    $mechanic->delete();
    expect($job->fresh()->mechanic->name)->toBe($mechanic->name);
});

it('creates mechanic work with self assignment and zero labor without financial inputs', function () {
    $mechanic = User::factory()->create(['role' => 'mechanic']);
    $order = ServiceOrder::factory()->create(['mechanic_id' => $mechanic->id]);
    $job = app(SaveServiceJob::class)->save($mechanic, $order, ['name' => 'Cek rem', 'status' => 'pending']);
    expect($job->mechanic_id)->toBe($mechanic->id)->and($job->fresh()->labor_price)->toBe('0.00');
});

it('rejects mechanic financial tampering and reassignment', function (string $field) {
    $mechanic = User::factory()->create(['role' => 'mechanic']);
    $other = User::factory()->create(['role' => 'mechanic']);
    $order = ServiceOrder::factory()->create(['mechanic_id' => $mechanic->id]);
    $input = ['name' => 'Cek rem', 'status' => 'pending', $field => $field === 'labor_price' ? '100.00' : $other->id];
    expect(fn () => app(SaveServiceJob::class)->save($mechanic, $order, $input))->toThrow(ValidationException::class);
    expect(ServiceJob::count())->toBe(0);
})->with(['labor_price', 'mechanic_id']);

it('denies unrelated mechanics and customer job creation', function (string $role) {
    $actor = User::factory()->create(['role' => $role]);
    $order = ServiceOrder::factory()->create();
    expect(fn () => app(SaveServiceJob::class)->save($actor, $order, ['name' => 'Cek', 'status' => 'pending', 'labor_price' => '0']))
        ->toThrow(AuthorizationException::class);
})->with(['mechanic', 'customer']);

it('rejects invalid decimal labor prices without rounding', function (mixed $price) {
    $admin = User::factory()->create(['role' => 'admin']);
    $order = ServiceOrder::factory()->create();
    expect(fn () => app(SaveServiceJob::class)->save($admin, $order, ['name' => 'Cek', 'status' => 'pending', 'labor_price' => $price]))
        ->toThrow(ValidationException::class);
    expect(ServiceJob::count())->toBe(0);
})->with(['-1.00', '1.001', '1000000000000.00', '1e3', '1,00', '01.00', 1.25, 100]);

it('rejects inactive and nonmechanic job assignments', function (bool $deleted) {
    $admin = User::factory()->create(['role' => 'admin']);
    $assignee = User::factory()->create(['role' => $deleted ? 'mechanic' : 'customer']);
    if ($deleted) {
        $assignee->delete();
    }
    expect(fn () => app(SaveServiceJob::class)->save($admin, ServiceOrder::factory()->create(), [
        'name' => 'Cek', 'status' => 'pending', 'labor_price' => '0', 'mechanic_id' => $assignee->id,
    ]))->toThrow(ValidationException::class);
})->with([true, false]);

it('edits pending work then completes it with immutable terminal snapshots', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $order = ServiceOrder::factory()->create();
    $job = ServiceJob::factory()->for($order)->create();
    $saved = app(SaveServiceJob::class)->save($admin, $order, ['name' => 'Cek CVT', 'labor_price' => '999999999999.99', 'status' => 'in_progress'], $job);
    expect(ServiceJob::count())->toBe(1)->and($saved->name)->toBe('Cek CVT')->and($saved->status)->toBe(ServiceJobStatus::InProgress);
    app(SaveServiceJob::class)->save($admin, $order, ['status' => 'completed'], $job);
    expect($job->fresh()->labor_price)->toBe('999999999999.99')->and($job->fresh()->status)->toBe(ServiceJobStatus::Completed);
    expect(fn () => app(SaveServiceJob::class)->save($admin, $order, ['name' => 'Ganti', 'status' => 'cancelled'], $job))->toThrow(ValidationException::class);
});

it('allows direct completion but rejects reverse transitions', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $order = ServiceOrder::factory()->create();
    $job = ServiceJob::factory()->for($order)->create();
    app(SaveServiceJob::class)->save($admin, $order, ['status' => 'completed'], $job);
    expect($job->fresh()->status)->toBe(ServiceJobStatus::Completed);
    $inProgress = ServiceJob::factory()->for($order)->create(['status' => 'in_progress']);
    expect(fn () => app(SaveServiceJob::class)->save($admin, $order, ['status' => 'pending'], $inProgress))->toThrow(ValidationException::class);
});

it('allows admin cancellation without hard delete and freezes cancelled work', function () {
    $admin = User::factory()->create(['role' => 'owner']);
    $order = ServiceOrder::factory()->create();
    $job = ServiceJob::factory()->for($order)->create();
    app(SaveServiceJob::class)->save($admin, $order, ['status' => 'cancelled'], $job);
    expect(ServiceJob::count())->toBe(1)->and($job->fresh()->status)->toBe(ServiceJobStatus::Cancelled);
    expect(fn () => app(SaveServiceJob::class)->save($admin, $order, ['name' => 'Ubah', 'status' => 'cancelled'], $job))->toThrow(ValidationException::class);
});

it('preserves mechanic labor snapshots and forbids cancelling or accessing other assigned jobs', function () {
    $mechanic = User::factory()->create(['role' => 'mechanic']);
    $other = User::factory()->create(['role' => 'mechanic']);
    $order = ServiceOrder::factory()->create(['mechanic_id' => $mechanic->id]);
    $job = ServiceJob::factory()->for($order)->create(['mechanic_id' => $mechanic->id, 'labor_price' => '55.55']);
    app(SaveServiceJob::class)->save($mechanic, $order, ['status' => 'in_progress', 'description' => 'Sudah dibersihkan.'], $job);
    expect($job->fresh()->labor_price)->toBe('55.55');
    expect(fn () => app(SaveServiceJob::class)->save($mechanic, $order, ['status' => 'cancelled'], $job))->toThrow(ValidationException::class);
    $otherJob = ServiceJob::factory()->for($order)->create(['mechanic_id' => $other->id]);
    expect(fn () => app(SaveServiceJob::class)->save($mechanic, $order, ['status' => 'completed'], $otherJob))->toThrow(AuthorizationException::class);
});

it('rejects jobs belonging to another order even when both orders are authorized', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $order = ServiceOrder::factory()->create();
    $job = ServiceJob::factory()->create();
    expect(fn () => app(SaveServiceJob::class)->save($admin, $order, ['status' => 'completed'], $job))->toThrow(AuthorizationException::class);
    expect($job->fresh()->status)->toBe(ServiceJobStatus::Pending)->and(ServiceJob::count())->toBe(1);
});

it('rereads parent terminal state to lock new and existing work', function (string $status) {
    $admin = User::factory()->create(['role' => 'admin']);
    $order = ServiceOrder::factory()->create();
    $job = ServiceJob::factory()->for($order)->create();
    ServiceOrder::whereKey($order->id)->update(['status' => $status]);
    expect(fn () => app(SaveServiceJob::class)->save($admin, $order, ['name' => 'Cek', 'labor_price' => '0', 'status' => 'pending']))->toThrow(ValidationException::class);
    expect(fn () => app(SaveServiceJob::class)->save($admin, $order, ['status' => 'completed'], $job))->toThrow(ValidationException::class);
    expect(ServiceJob::count())->toBe(1)->and($job->fresh()->status)->toBe(ServiceJobStatus::Pending);
})->with(['completed', 'ready_for_pickup', 'delivered', 'cancelled']);

it('rereads parent assignment before mechanic mutation', function () {
    $mechanic = User::factory()->create(['role' => 'mechanic']);
    $other = User::factory()->create(['role' => 'mechanic']);
    $order = ServiceOrder::factory()->create(['mechanic_id' => $mechanic->id]);
    $job = ServiceJob::factory()->for($order)->create(['mechanic_id' => $mechanic->id]);
    ServiceOrder::whereKey($order->id)->update(['mechanic_id' => $other->id]);
    expect(fn () => app(SaveServiceJob::class)->save($mechanic, $order, ['status' => 'completed'], $job))->toThrow(AuthorizationException::class);
});

it('creates edits and cancels custom work inline with a decimal subtotal excluding cancellation', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $mechanic = User::factory()->create(['role' => 'mechanic']);
    $order = ServiceOrder::factory()->create();
    ServiceJob::factory()->for($order)->create(['name' => 'Dibatalkan sebelumnya', 'status' => 'cancelled', 'labor_price' => '99.99']);
    $page = Livewire::actingAs($admin)->test(ServiceJobs::class, ['serviceOrderId' => $order->id])
        ->assertSee('Pekerjaan yang dilakukan')->call('create')->assertSet('form.labor_price', '0.00')
        ->set('form.name', 'Bersihkan CVT')->set('form.description', 'Roller diperiksa.')
        ->set('form.labor_price', '12500.25')->set('form.mechanic_id', (string) $mechanic->id)
        ->call('save')->assertHasNoErrors()->assertSet('showForm', false)->assertSee('Bersihkan CVT')
        ->assertSee('Rp 12500,25');
    $job = ServiceJob::where('name', 'Bersihkan CVT')->sole();
    $page->call('edit', $job->id)->assertSet('editingId', $job->id)
        ->set('form.name', 'Bersihkan rem')->set('form.status', 'in_progress')
        ->call('save')->assertHasNoErrors()->assertSee('Bersihkan rem')
        ->call('cancel', $job->id)->assertHasNoErrors()->assertSee('Rp 0,00');
    expect($job->fresh()->status)->toBe(ServiceJobStatus::Cancelled)->and(ServiceJob::count())->toBe(2);
});

it('keeps invalid input errors beside the job form and preserves entered work', function () {
    $order = ServiceOrder::factory()->create();
    Livewire::actingAs(User::factory()->create(['role' => 'admin']))->test(ServiceJobs::class, ['serviceOrderId' => $order->id])
        ->call('create')->set('form.name', 'Cek rem')->set('form.labor_price', '-1.00')
        ->call('save')->assertHasErrors(['form.labor_price'])->assertSet('showForm', true)
        ->assertSet('form.name', 'Cek rem');
    expect(ServiceJob::count())->toBe(0);
});

it('shows only mechanic own or unassigned work and keeps financial controls out of mechanic forms', function () {
    $mechanic = User::factory()->create(['role' => 'mechanic']);
    $other = User::factory()->create(['role' => 'mechanic']);
    $order = ServiceOrder::factory()->create(['mechanic_id' => $mechanic->id]);
    $hidden = ServiceJob::factory()->for($order)->create(['mechanic_id' => $other->id, 'name' => 'Rahasia pekerjaan lain']);
    $job = ServiceJob::factory()->for($order)->create(['mechanic_id' => $mechanic->id, 'name' => 'Pekerjaan sendiri']);
    $page = Livewire::actingAs($mechanic)->test(ServiceJobs::class, ['serviceOrderId' => $order->id])
        ->assertSee('Pekerjaan sendiri')->assertDontSee('Rahasia pekerjaan lain')
        ->call('create')->assertDontSee('Harga jasa (Rp)')->assertDontSee('Mekanik pekerjaan')
        ->set('form.name', 'Cek lampu')->call('save')->assertHasNoErrors();
    expect(ServiceJob::where('name', 'Cek lampu')->sole()->labor_price)->toBe('0.00');
    $page->call('edit', $job->id)->set('form.labor_price', '123')
        ->call('save')->assertHasErrors(['form.labor_price']);
    $page->call('edit', $job->id)->set('form.mechanic_id', (string) $other->id)
        ->call('save')->assertHasErrors(['form.mechanic_id']);
    $page->call('edit', $hidden->id)->assertForbidden();
});

it('locks job and parent selections against browser tampering', function (string $property) {
    $order = ServiceOrder::factory()->create();
    $page = Livewire::actingAs(User::factory()->create(['role' => 'admin']))->test(ServiceJobs::class, ['serviceOrderId' => $order->id]);
    expect(fn () => $page->set($property, 999999))->toThrow(CannotUpdateLockedPropertyException::class);
})->with(['serviceOrderId', 'editingId']);

it('denies customer component methods even on their own parent order', function () {
    $actor = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $actor->id]);
    $vehicle = Vehicle::factory()->for($customer)->create();
    $order = ServiceOrder::factory()->for($vehicle)->for($customer)->create();
    $job = ServiceJob::factory()->for($order)->create();
    $this->actingAs($actor);
    Livewire::test(ServiceJobs::class, ['serviceOrderId' => $order->id])->assertForbidden();
    foreach ([['boot'], ['mount', $order->id], ['render'], ['create'], ['edit', $job->id], ['save'], ['cancel', $job->id], ['closeForm']] as $call) {
        $method = array_shift($call);
        $component = new ServiceJobs;
        $component->serviceOrderId = $order->id;
        expect(fn () => $component->{$method}(...$call))->toThrow(AuthorizationException::class);
    }
});

it('denies cross order edit identifiers and unassigned parent mounts', function () {
    $order = ServiceOrder::factory()->create();
    $otherJob = ServiceJob::factory()->create();
    Livewire::actingAs(User::factory()->create(['role' => 'admin']))->test(ServiceJobs::class, ['serviceOrderId' => $order->id])
        ->call('edit', $otherJob->id)->assertForbidden();
    Livewire::actingAs(User::factory()->create(['role' => 'mechanic']))->test(ServiceJobs::class, ['serviceOrderId' => $order->id])->assertForbidden();
});

it('rereads parent assignment during child hydration and render', function () {
    $mechanic = User::factory()->create(['role' => 'mechanic']);
    $other = User::factory()->create(['role' => 'mechanic']);
    $order = ServiceOrder::factory()->create(['mechanic_id' => $mechanic->id]);
    $page = Livewire::actingAs($mechanic)->test(ServiceJobs::class, ['serviceOrderId' => $order->id]);
    $order->update(['mechanic_id' => $other->id]);
    $page->call('$refresh')->assertForbidden();
});

it('allows terminal history but displays stale form errors after parent is closed', function () {
    $order = ServiceOrder::factory()->create();
    $job = ServiceJob::factory()->for($order)->create();
    $page = Livewire::actingAs(User::factory()->create(['role' => 'admin']))->test(ServiceJobs::class, ['serviceOrderId' => $order->id])->call('edit', $job->id);
    $order->update(['status' => 'completed']);
    $page->call('save')->assertHasErrors(['form.status'])->assertSee('Pekerjaan terkunci')
        ->assertSee($job->name)->assertDontSee('Tambah pekerjaan');
});

it('formats decimal money without floating point and rejects nonnumeric data', function () {
    $component = new ServiceJobs;
    expect($component->formatMoney('999999999999.99'))->toBe('Rp 999999999999,99')
        ->and($component->formatMoney('0'))->toBe('Rp 0,00');
    expect(fn () => $component->formatMoney('invalid'))->toThrow(InvalidArgumentException::class);
});

it('rolls back the job when its audit write fails', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $order = ServiceOrder::factory()->create();
    AuditLog::creating(function () {
        throw new RuntimeException('audit unavailable');
    });
    try {
        expect(fn () => app(SaveServiceJob::class)->save($admin, $order, ['name' => 'Cek', 'labor_price' => '0', 'status' => 'pending']))->toThrow(RuntimeException::class, 'audit unavailable');
        expect(ServiceJob::count())->toBe(0);
    } finally {
        AuditLog::flushEventListeners();
    }
});
