<?php

namespace App\Actions;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\User;
use App\Support\WorkshopInput;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CustomerResolver
{
    /** @param array<string, mixed> $input */
    public function resolve(array $input): Customer
    {
        $input['phone'] = is_string($input['phone'] ?? null) ? WorkshopInput::phone($input['phone']) : null;
        $data = Validator::make($input, [
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'regex:/^62[1-9][0-9]{7,12}$/D'],
            'email' => ['nullable', 'email:rfc', 'max:254'],
            'address' => ['nullable', 'string', 'max:2000'], 'notes' => ['nullable', 'string', 'max:5000'],
        ])->validate();
        $find = function () use ($data): ?Customer {
            $customer = Customer::withTrashed()->where('phone', $data['phone'])->lockForUpdate()->first();
            if ($customer?->trashed()) {
                throw ValidationException::withMessages(['phone' => 'Kontak ini diarsipkan. Hubungi petugas untuk verifikasi dan pemulihan.']);
            }

            return $customer;
        };
        try {
            return DB::transaction(fn () => $find() ?? Customer::create($data), attempts: 5);
        } catch (UniqueConstraintViolationException) {
            return $find() ?? throw ValidationException::withMessages(['phone' => 'Data sedang diproses. Silakan coba lagi.']);
        }
    }

    /** @param array<string, mixed> $input */
    public function forUser(User $user, array $input): Customer
    {
        return DB::transaction(function () use ($user, $input): Customer {
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_unless($user->role === Role::Customer, 403);
            $linked = Customer::withTrashed()->where('user_id', $user->id)->lockForUpdate()->first();
            if ($linked) {
                if ($linked->trashed()) {
                    throw ValidationException::withMessages(['phone' => 'Data pelanggan diarsipkan. Hubungi petugas.']);
                }

                return $linked;
            }
            $phone = WorkshopInput::phone($user->phone ?: (is_string($input['phone'] ?? null) ? $input['phone'] : ''));
            $customer = $this->resolve([...$input, 'name' => $user->name, 'email' => $user->email, 'phone' => $phone]);
            // Only our newly inserted master is safe to claim without identity verification.
            if ($customer->wasRecentlyCreated) {
                $customer->user_id = $user->id;
                $customer->save();
            }
            if (! $user->phone) {
                $user->forceFill(['phone' => $phone])->save();
            }

            return $customer;
        }, attempts: 5);
    }
}
