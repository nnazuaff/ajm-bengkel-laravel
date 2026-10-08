<?php

namespace App\Actions;

use App\Enums\Role;
use App\Enums\ServiceStatus;
use App\Models\AuditLog;
use App\Models\ServiceJob;
use App\Models\ServiceOrder;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ManageStaff
{
    /** @param array<string, mixed> $input */
    public function create(User $actor, array $input): User
    {
        try {
            return DB::transaction(function () use ($actor, $input): User {
                $actor = User::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();
                Gate::allowIf($actor->role === Role::Owner);
                $data = Validator::make($input, [
                    'name' => ['required', 'string', 'max:255'],
                    'email' => ['required', 'email', 'max:254', Rule::unique('users', 'email')],
                    'role' => ['required', Rule::in(['owner', 'admin', 'mechanic'])],
                    'password' => ['required', 'string', 'min:12', 'max:255', 'confirmed'],
                ])->validate();
                $staff = new User;
                $staff->name = $data['name'];
                $staff->email = $data['email'];
                $staff->role = Role::from($data['role']);
                $staff->password = $data['password'];
                $staff->email_verified_at = now();
                $staff->save();
                AuditLog::create([
                    'actor_id' => $actor->id, 'action' => 'staff.created', 'entity_type' => User::class,
                    'entity_id' => $staff->id, 'context' => ['role' => $staff->role->value],
                ]);

                return $staff;
            }, attempts: 5);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['email' => 'Email sudah digunakan, termasuk akun yang diarsipkan.']);
        }
    }

    /** @param array<string, mixed> $input */
    public function save(User $actor, User $target, array $input): User
    {
        return DB::transaction(function () use ($actor, $target, $input): User {
            $actor = User::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();
            Gate::allowIf($actor->role === Role::Owner);
            $target = User::query()->whereKey($target->id)->lockForUpdate()->firstOrFail();
            Gate::allowIf($target->role->isStaff());
            $data = Validator::make($input, [
                'name' => ['required', 'string', 'max:255'],
                'role' => ['required', Rule::in(['owner', 'admin', 'mechanic'])],
            ])->validate();
            if ($target->role->value !== $data['role']) {
                $this->guardRoleLoss($target);
            }
            $before = ['name' => $target->name, 'role' => $target->role->value];
            $target->name = $data['name'];
            $target->role = Role::from($data['role']);
            $target->save();
            AuditLog::create([
                'actor_id' => $actor->id, 'action' => 'staff.updated', 'entity_type' => User::class,
                'entity_id' => $target->id, 'context' => ['before' => $before, 'after' => $data],
            ]);

            return $target;
        }, attempts: 5);
    }

    public function deactivate(User $actor, User $target): User
    {
        return DB::transaction(function () use ($actor, $target): User {
            $actor = User::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();
            Gate::allowIf($actor->role === Role::Owner);
            $target = User::query()->whereKey($target->id)->lockForUpdate()->firstOrFail();
            Gate::allowIf($target->role->isStaff());
            if ($target->id === $actor->id) {
                throw ValidationException::withMessages(['deactivate' => 'Akun sendiri tidak dapat dinonaktifkan.']);
            }
            $this->guardRoleLoss($target);
            $target->delete();
            AuditLog::create([
                'actor_id' => $actor->id, 'action' => 'staff.deactivated', 'entity_type' => User::class,
                'entity_id' => $target->id, 'context' => ['role' => $target->role->value],
            ]);

            return $target;
        }, attempts: 5);
    }

    private function guardRoleLoss(User $target): void
    {
        if ($target->role === Role::Owner && count(User::query()->where('role', 'owner')->lockForUpdate()->get()->all()) <= 1) {
            throw ValidationException::withMessages(['role' => 'Minimal satu pemilik aktif harus dipertahankan.']);
        }
        if ($target->role === Role::Mechanic && (ServiceOrder::query()->where('mechanic_id', $target->id)
            ->whereNotIn('status', [ServiceStatus::Delivered, ServiceStatus::Cancelled])->lockForUpdate()->first()
            || ServiceJob::query()->where('mechanic_id', $target->id)->whereIn('status', ['pending', 'in_progress'])
                ->whereHas('serviceOrder', fn ($query) => $query->whereNotIn('status', [ServiceStatus::Delivered, ServiceStatus::Cancelled]))
                ->lockForUpdate()->first())) {
            throw ValidationException::withMessages(['role' => 'Mekanik masih memiliki servis aktif. Alihkan atau selesaikan servis terlebih dahulu.']);
        }
    }
}
