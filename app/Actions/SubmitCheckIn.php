<?php

namespace App\Actions;

use App\Concerns\PasswordValidationRules;
use App\Enums\CheckInStatus;
use App\Enums\Role;
use App\Models\CheckIn;
use App\Models\CheckInCode;
use App\Models\Customer;
use App\Models\User;
use App\Support\WorkshopInput;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SubmitCheckIn
{
    use PasswordValidationRules;

    /** @param array<string,mixed> $input */
    public function submit(array $input, ?string $browserToken = null, ?User $actor = null): CheckIn
    {
        if ($actor) {
            $actor = User::findOrFail($actor->id);
            abort_unless($actor->role === Role::Customer && $actor->canUseCustomerAccess(), 403);
            $trusted = Customer::where('user_id', $actor->id)->first();
            $input = [...$input, 'name' => $trusted !== null ? $trusted->name : $actor->name, 'phone' => $trusted !== null ? $trusted->phone : $actor->phone, 'email' => $trusted !== null ? $trusted->email : $actor->email];
        }
        $key = 'check-in:'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['code' => 'Terlalu banyak percobaan. Coba lagi dalam satu menit.']);
        }
        RateLimiter::hit($key, 60);
        if (is_string($input['phone'] ?? null)) {
            $input['phone'] = WorkshopInput::phone($input['phone']);
        }
        $data = Validator::make($input, ['code' => ['required', 'string', 'regex:/^[0-9]{6}$/D'], 'name' => ['required', 'string', 'max:120'], 'phone' => ['required', 'string', 'regex:/^62[1-9][0-9]{7,12}$/D'], 'email' => ['nullable', 'email:rfc', 'max:254']])->validate();

        if ($browserToken !== null && $actor === null) {
            $credentials = Validator::make($input, ['email' => ['required', 'email:rfc', 'max:254', Rule::unique('users', 'email')], 'password' => $this->passwordRules()])->validate();
        } else {
            $credentials = [];
        }

        return DB::transaction(function () use ($data, $browserToken, $actor, $credentials) {
            if ($actor) {
                $actor = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
                abort_unless($actor->role === Role::Customer && $actor->canUseCustomerAccess(), 403);
            }
            $code = CheckInCode::whereKey(1)->lockForUpdate()->first();
            if (! $code || ! $code->active || ! $code->code || ($code->expires_at && $code->expires_at->isPast()) || ! hash_equals($code->code, $data['code'])) {
                throw ValidationException::withMessages(['code' => 'Kode salah atau tidak aktif. Minta kode terbaru kepada petugas.']);
            }
            $customer = $actor ? app(CustomerResolver::class)->forUser($actor, $data) : app(CustomerResolver::class)->resolve($data);
            $existing = CheckIn::where('customer_id', $customer->id)->whereIn('status', [CheckInStatus::Waiting, CheckInStatus::Processing])->lockForUpdate()->first();

            if ($existing) {
                if ($actor) {
                    throw ValidationException::withMessages(['code' => 'Check-in masih menunggu atau diproses. Tunggu diterima servis atau dibatalkan.']);
                }

                return $existing;
            }
            $account = $actor;
            $needsIdentity = $actor ? $customer->user_id !== $actor->id : ! $customer->wasRecentlyCreated;
            if ($browserToken !== null && ! $actor && ($customer->user_id !== null || User::withTrashed()->where('phone', $customer->phone)->exists())) {
                throw ValidationException::withMessages(['phone' => 'Kontak sudah memiliki akun. Masuk terlebih dahulu atau hubungi petugas untuk pemulihan akun.']);
            }
            // Never claim an existing account from public phone/email input.
            if (! $actor && $customer->user_id === null && ! User::withTrashed()->where('phone', $customer->phone)->exists()) {
                $account = User::create(['name' => $data['name'], 'phone' => $customer->phone,
                    'email' => $credentials['email'] ?? null, 'password' => $credentials['password'] ?? Str::random(64)]);
                if ($customer->wasRecentlyCreated) {
                    $customer->forceFill(['user_id' => $account->id])->save();
                }
            }

            return CheckIn::create(['customer_id' => $customer->id, 'status' => CheckInStatus::Waiting, 'checked_in_at' => now(),
                'account_id' => $account?->id, 'requires_identity_verification' => $browserToken === null ? true : $needsIdentity, 'browser_token_hash' => $browserToken === null ? null : hash('sha256', $browserToken),
                'browser_access_expires_at' => $browserToken === null ? null : now()->addDay()]);
        }, attempts: 5);
    }
}
