<?php

namespace App\Actions;

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class LinkCustomerAccount
{
    public function link(User $actor, Customer $customer, ?int $userId, bool $ownershipVerified): Customer
    {
        $actor = User::query()->find($actor->id) ?? throw new AuthorizationException;
        Gate::forUser($actor)->authorize('manage-workshop');

        if (! $ownershipVerified) {
            throw ValidationException::withMessages(['ownershipVerified' => 'Konfirmasi verifikasi identitas dan kepemilikan kendaraan terlebih dahulu.']);
        }

        return DB::transaction(function () use ($actor, $customer, $userId): Customer {
            $customer = Customer::query()->whereKey($customer->id)->lockForUpdate()->firstOrFail();
            if ($userId !== null) {
                $account = User::query()->whereKey($userId)->lockForUpdate()->first();
                if (! $account || $account->role !== Role::Customer || ! $account->canUseCustomerAccess()) {
                    throw ValidationException::withMessages(['userId' => config('fortify.require_email_verification')
                        ? 'Pilih akun pelanggan aktif dengan email terverifikasi.'
                        : 'Pilih akun pelanggan aktif.']);
                }
                if (Customer::withTrashed()->where('user_id', $userId)->whereKeyNot($customer->id)->exists()) {
                    throw ValidationException::withMessages(['userId' => 'Akun sudah terhubung ke pelanggan lain, termasuk arsip.']);
                }
            }
            $previousUserId = $customer->user_id;
            $customer->user_id = $userId;
            try {
                $customer->save();
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages(['userId' => 'Akun sudah terhubung ke pelanggan lain, termasuk arsip.']);
            }
            AuditLog::create([
                'actor_id' => $actor->id, 'action' => 'customer.account_linked',
                'entity_type' => Customer::class, 'entity_id' => $customer->id,
                'context' => ['previous_user_id' => $previousUserId, 'new_user_id' => $userId],
            ]);

            return $customer->load('user');
        }, attempts: 5);
    }
}
