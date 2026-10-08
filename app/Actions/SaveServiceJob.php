<?php

namespace App\Actions;

use App\Enums\Role;
use App\Enums\ServiceJobStatus;
use App\Enums\ServiceStatus;
use App\Models\AuditLog;
use App\Models\ServiceJob;
use App\Models\ServiceOrder;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SaveServiceJob
{
    /** @param array<string, mixed> $input */
    public function save(User $actor, ServiceOrder $order, array $input, ?ServiceJob $job = null): ServiceJob
    {
        return DB::transaction(function () use ($actor, $order, $input, $job): ServiceJob {
            // Shared lock order with order completion/cancellation: parent, then job.
            $currentOrder = ServiceOrder::query()->lockForUpdate()->findOrFail($order->id);
            Gate::forUser($actor)->authorize('viewAny', ServiceOrder::class);
            Gate::forUser($actor)->authorize('update', $currentOrder);
            $current = $job === null ? new ServiceJob : ServiceJob::query()->lockForUpdate()->findOrFail($job->id);
            if ($job !== null && $current->service_order_id !== $currentOrder->id) {
                throw new AuthorizationException('Pekerjaan tidak berasal dari order ini.');
            }
            $current->setRelation('serviceOrder', $currentOrder);
            if ($job === null) {
                Gate::forUser($actor)->authorize('create', [ServiceJob::class, $currentOrder]);
            } else {
                Gate::forUser($actor)->authorize('update', $current);
            }
            if (in_array($currentOrder->status, [ServiceStatus::Completed, ServiceStatus::ReadyForPickup, ServiceStatus::Delivered, ServiceStatus::Cancelled], true)) {
                throw ValidationException::withMessages(['status' => 'Pekerjaan terkunci karena order sudah selesai atau ditutup.']);
            }
            if ($job !== null && in_array($current->status, [ServiceJobStatus::Completed, ServiceJobStatus::Cancelled], true)) {
                throw ValidationException::withMessages(['status' => 'Pekerjaan selesai atau dibatalkan tidak dapat diubah.']);
            }
            if ($actor->role === Role::Mechanic) {
                if (array_key_exists('labor_price', $input)) {
                    throw ValidationException::withMessages(['labor_price' => 'Harga jasa hanya dapat diubah admin.']);
                }
                if (array_key_exists('mechanic_id', $input) && (string) $input['mechanic_id'] !== (string) $actor->id) {
                    throw ValidationException::withMessages(['mechanic_id' => 'Mekanik hanya dapat menugaskan dirinya sendiri.']);
                }
                if ($job === null) {
                    $input['labor_price'] = '0.00';
                    $input['mechanic_id'] = $actor->id;
                }
            }
            if ($currentOrder->receipt()->whereIn('status', ['final', 'paid'])->exists()) {
                throw ValidationException::withMessages(['status' => 'Pekerjaan terkunci karena bon sudah difinalkan.']);
            }
            $data = Validator::make($input, [
                'name' => [$job === null ? 'required' : 'sometimes', 'required', 'string', 'max:120'],
                'description' => ['nullable', 'string', 'max:5000'],
                'mechanic_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('role', Role::Mechanic->value)->whereNull('deleted_at')],
                'labor_price' => [$job === null ? 'required' : 'sometimes', 'required', 'string', 'regex:/\A(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?\z/'],
                'status' => ['required', Rule::enum(ServiceJobStatus::class)],
                'notes' => ['nullable', 'string', 'max:5000'],
            ])->validate();
            $next = ServiceJobStatus::from($data['status']);
            if ($actor->role === Role::Mechanic && $next === ServiceJobStatus::Cancelled) {
                throw ValidationException::withMessages(['status' => 'Pembatalan pekerjaan hanya dapat dilakukan admin.']);
            }
            $previous = $job === null ? ServiceJobStatus::Pending : $current->status;
            if ($next !== $previous && ! in_array($next, $previous->transitions(), true)) {
                throw ValidationException::withMessages(['status' => 'Perubahan status pekerjaan tidak sesuai urutan. Muat ulang pekerjaan.']);
            }
            $before = $job === null ? null : $current->only(['status', 'labor_price', 'mechanic_id']);
            $current->fill($data);
            $current->service_order_id = $currentOrder->id;
            $current->save();
            if ($job === null || $current->wasChanged()) {
                AuditLog::create([
                    'actor_id' => $actor->id, 'action' => $job === null ? 'service_job.created' : 'service_job.updated',
                    'entity_type' => ServiceJob::class, 'entity_id' => $current->id,
                    'context' => ['before' => $before, 'after' => $current->only(['status', 'labor_price', 'mechanic_id']), 'changed_fields' => array_keys($current->getChanges())],
                ]);
            }

            return $current;
        }, attempts: 5);
    }
}
