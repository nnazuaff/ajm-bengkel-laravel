<?php

namespace App\Actions;

use App\Enums\CheckInStatus;
use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\CheckIn;
use App\Models\Customer;
use App\Models\ServiceOrder;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ProcessCheckIn
{
    /** @param array<string,mixed> $input */
    public function process(User $actor, CheckIn $checkIn, array $input): ServiceOrder
    {
        Gate::forUser($actor)->authorize('work-services');

        return DB::transaction(function () use ($actor, $checkIn, $input) {
            $actor = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('work-services');
            $current = CheckIn::whereKey($checkIn->id)->lockForUpdate()->firstOrFail();
            if ($current->status === CheckInStatus::ConvertedToService) {
                $order = ServiceOrder::findOrFail($current->service_order_id);
                Gate::forUser($actor)->authorize('view', $order);
                if ($order->customer_id !== $current->customer_id) {
                    throw ValidationException::withMessages(['status' => 'Relasi check-in dan servis tidak konsisten. Hubungi admin.']);
                }

                return $order;
            }
            if (! in_array($current->status, [CheckInStatus::Waiting, CheckInStatus::Processing], true)) {
                throw ValidationException::withMessages(['status' => 'Check-in sudah dibatalkan.']);
            }
            if (! empty($input['vehicle_id']) && ! Vehicle::whereKey($input['vehicle_id'])->where('customer_id', $current->customer_id)->exists()) {
                throw ValidationException::withMessages(['vehicle_id' => 'Motor bukan milik pelanggan check-in.']);
            }
            if ($current->account_id !== null) {
                if ($current->requires_identity_verification) {
                    Validator::make($input, ['identity_verified' => ['accepted']])->validate();
                }
                $customer = Customer::whereKey($current->customer_id)->lockForUpdate()->firstOrFail();
                $account = User::whereKey($current->account_id)->lockForUpdate()->firstOrFail();
                if ($account->role !== Role::Customer || ($customer->user_id !== null && $customer->user_id !== $account->id)
                    || Customer::withTrashed()->where('user_id', $account->id)->whereKeyNot($customer->id)->exists()) {
                    throw ValidationException::withMessages(['identity_verified' => 'Akses akun telah berubah. Minta admin memverifikasi akun.']);
                }
                $customer->forceFill(['user_id' => $account->id])->save();
                $account->forceFill(['identity_verified_at' => now()])->save();
            }
            unset($input['customer']);
            $input['customer_id'] = $current->customer_id;
            $order = app(ReceiveWalkIn::class)->receiveFromCheckIn($actor, $input);
            $current->update(['status' => CheckInStatus::ConvertedToService, 'service_order_id' => $order->id, 'processed_by' => $actor->id, 'processed_at' => now()]);
            $audit = AuditLog::create(['actor_id' => $actor->id, 'action' => 'check_in.converted', 'entity_type' => CheckIn::class, 'entity_id' => $current->id, 'context' => ['service_order_id' => $order->id]]);
            if (! $audit->exists) {
                throw new \RuntimeException('Audit gagal disimpan.');
            }

            return $order;
        }, attempts: 5);
    }

    public function cancel(User $actor, CheckIn $checkIn): void
    {
        Gate::forUser($actor)->authorize('work-services');
        DB::transaction(function () use ($actor, $checkIn) {
            $actor = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('work-services');
            $current = CheckIn::whereKey($checkIn->id)->lockForUpdate()->firstOrFail();
            if (! in_array($current->status, [CheckInStatus::Waiting, CheckInStatus::Processing], true)) {
                throw ValidationException::withMessages(['status' => 'Check-in sudah ditutup.']);
            }
            $current->update(['status' => CheckInStatus::Cancelled, 'processed_by' => $actor->id, 'processed_at' => now()]);
            $audit = AuditLog::create(['actor_id' => $actor->id, 'action' => 'check_in.cancelled', 'entity_type' => CheckIn::class, 'entity_id' => $current->id]);
            if (! $audit->exists) {
                throw new \RuntimeException('Audit gagal disimpan.');
            }
        });
    }
}
