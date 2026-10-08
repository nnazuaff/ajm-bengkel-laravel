<?php

namespace App\Actions;

use App\Enums\Role;
use App\Enums\ServiceStatus;
use App\Models\AuditLog;
use App\Models\ServiceOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UpdateServiceOrder
{
    /** @param array<string, mixed> $input */
    public function update(User $actor, ServiceOrder $order, array $input): ServiceOrder
    {
        return DB::transaction(function () use ($actor, $order, $input): ServiceOrder {
            $current = ServiceOrder::query()->lockForUpdate()->findOrFail($order->id);
            Gate::forUser($actor)->authorize('update', $current);
            $data = Validator::make($input, [
                'status' => ['required', Rule::enum(ServiceStatus::class)],
                'diagnosis' => ['nullable', 'string', 'max:5000'],
                'notes' => ['nullable', 'string', 'max:5000'],
                'mechanic_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('role', Role::Mechanic->value)->whereNull('deleted_at')],
            ])->validate();
            $next = ServiceStatus::from($data['status']);

            if (in_array($current->status, [ServiceStatus::Delivered, ServiceStatus::Cancelled], true)) {
                throw ValidationException::withMessages(['status' => 'Order yang sudah ditutup tidak dapat diubah.']);
            }
            if ($next !== $current->status && ! $current->status->canTransitionTo($next)) {
                throw ValidationException::withMessages(['status' => 'Perubahan status tidak sesuai urutan kerja. Muat ulang order.']);
            }
            if ($actor->role === Role::Mechanic) {
                if (array_key_exists('mechanic_id', $data)) {
                    throw ValidationException::withMessages(['mechanic_id' => 'Penugasan mekanik hanya dapat diubah admin.']);
                }
                if ($next !== $current->status && ! in_array($next, [ServiceStatus::Inspection, ServiceStatus::InProgress, ServiceStatus::WaitingPart, ServiceStatus::Completed], true)) {
                    throw ValidationException::withMessages(['status' => 'Persetujuan, pembatalan, dan penyerahan dikelola admin.']);
                }
            }
            $diagnosis = array_key_exists('diagnosis', $data) ? $data['diagnosis'] : $current->diagnosis;
            if (in_array($next, [ServiceStatus::Approved, ServiceStatus::InProgress, ServiceStatus::WaitingPart, ServiceStatus::Completed, ServiceStatus::ReadyForPickup, ServiceStatus::Delivered], true) && blank($diagnosis)) {
                throw ValidationException::withMessages(['diagnosis' => 'Catat diagnosis sebelum menyetujui atau menyelesaikan servis.']);
            }
            $notes = array_key_exists('notes', $data) ? $data['notes'] : $current->notes;
            if ($next === ServiceStatus::Cancelled && blank($notes)) {
                throw ValidationException::withMessages(['notes' => 'Catat alasan pembatalan.']);
            }

            if ($next === ServiceStatus::Completed && $current->jobs()->whereIn('status', ['pending', 'in_progress'])->exists()) {
                throw ValidationException::withMessages(['status' => 'Selesaikan atau batalkan seluruh pekerjaan sebelum menutup servis.']);
            }
            if ($next === ServiceStatus::Cancelled) {
                if ($current->receipt()->whereIn('status', ['final', 'paid'])->exists()) {
                    throw ValidationException::withMessages(['status' => 'Void bon terlebih dahulu sebelum membatalkan servis.']);
                }
                app(UseServicePart::class)->returnAll($actor, $current);
                $current->jobs()->whereIn('status', ['pending', 'in_progress'])->update(['status' => 'cancelled', 'updated_at' => now()]);
            }

            $before = $current->only(['status', 'diagnosis', 'notes', 'mechanic_id']);
            $current->fill($data);
            if ($next === ServiceStatus::InProgress && $current->started_at === null) {
                $current->started_at = now();
            }
            if ($next === ServiceStatus::Completed && $current->completed_at === null) {
                $current->completed_at = now();
            }
            if ($next === ServiceStatus::Delivered && $current->delivered_at === null) {
                $current->delivered_at = now();
            }
            $current->save();
            if ($current->wasChanged()) {
                AuditLog::create([
                    'actor_id' => $actor->id,
                    'action' => 'service.updated',
                    'entity_type' => ServiceOrder::class,
                    'entity_id' => $current->id,
                    'context' => [
                        'before' => ['status' => $before['status'], 'mechanic_id' => $before['mechanic_id']],
                        'after' => ['status' => $next->value, 'mechanic_id' => $current->mechanic_id],
                        'changed_fields' => array_keys($current->getChanges()),
                    ],
                ]);
            }

            return $current;
        }, attempts: 5);
    }
}
