<?php

use App\Actions\UpdateServiceOrder;
use App\Models\ServiceJob;
use App\Models\ServiceOrder;
use App\Models\User;
use Illuminate\Validation\ValidationException;

it('does not complete an order while a job remains open', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $order = ServiceOrder::factory()->create(['status' => 'in_progress', 'diagnosis' => 'Rem diperiksa.']);
    $job = ServiceJob::factory()->create(['service_order_id' => $order->id, 'status' => 'pending']);
    expect(fn () => app(UpdateServiceOrder::class)->update($admin, $order, ['status' => 'completed']))
        ->toThrow(ValidationException::class);
    $job->update(['status' => 'completed']);
    app(UpdateServiceOrder::class)->update($admin, $order, ['status' => 'completed']);
    expect($order->fresh()->completed_at)->not->toBeNull();
});

it('cancels unfinished jobs atomically with their service order', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $order = ServiceOrder::factory()->create(['status' => 'inspection']);
    $open = ServiceJob::factory()->create(['service_order_id' => $order->id, 'status' => 'in_progress']);
    $done = ServiceJob::factory()->create(['service_order_id' => $order->id, 'status' => 'completed']);
    app(UpdateServiceOrder::class)->update($admin, $order, ['status' => 'cancelled', 'notes' => 'Pelanggan membatalkan.']);
    expect($open->fresh()->getRawOriginal('status'))->toBe('cancelled')
        ->and($done->fresh()->getRawOriginal('status'))->toBe('completed');
});
