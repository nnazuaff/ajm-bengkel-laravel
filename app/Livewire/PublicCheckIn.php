<?php

namespace App\Livewire;

use App\Actions\SubmitCheckIn;
use App\Enums\CheckInStatus;
use App\Enums\Role;
use App\Models\CheckIn;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.public')]
#[Title('Check-in di bengkel')]
class PublicCheckIn extends Component
{
    public string $name = '';

    public string $phone = '';

    public string $email = '';

    public string $code = '';

    public string $password = '';

    public string $password_confirmation = '';

    #[Locked]
    public bool $submitted = false;

    #[Locked]
    public string $status = 'waiting';

    #[Locked]
    public bool $needsLogin = false;

    public function mount(): void
    {
        if (Auth::check()) {
            $actor = User::findOrFail(Auth::id());
            abort_unless($actor->role === Role::Customer && $actor->canUseCustomerAccess(), 403);
            $customer = Customer::where('user_id', $actor->id)->first();
            $this->name = $customer !== null ? $customer->name : $actor->name;
            $this->phone = $customer !== null ? $customer->phone : ($actor->phone ?? '');
            $this->email = $actor->email ?? '';
            $waiting = $customer ? CheckIn::where('customer_id', $customer->id)->whereIn('status', [CheckInStatus::Waiting, CheckInStatus::Processing])->first() : null;
            if ($waiting) {
                session(['check_in.id' => $waiting->id]);
            } elseif ($this->entry()?->status === CheckInStatus::Cancelled) {
                session()->forget('check_in');
            }
        }
        $this->submitted = $this->entry() !== null;
        if ($this->submitted) {
            $this->refreshStatus();
        }
    }

    private function entry(): ?CheckIn
    {
        $id = session('check_in.id');
        $token = session('check_in.token');
        if (is_int($id) && Auth::check()) {
            $entry = CheckIn::find($id);
            if ($entry && Customer::whereKey($entry->customer_id)->where('user_id', Auth::id())->exists()) {
                return $entry;
            }
        }
        if (! is_int($id) || ! is_string($token)) {
            return null;
        }
        $entry = CheckIn::find($id);

        return $entry && $entry->browser_token_hash && $entry->browser_access_expires_at?->isFuture()
            && hash_equals($entry->browser_token_hash, hash('sha256', $token)) ? $entry : null;
    }

    public function refreshStatus(): void
    {
        $entry = $this->entry();
        if (! $entry) {
            $this->status = 'expired';

            return;
        }
        $this->status = $entry->status->value;
        if ($entry->status !== CheckInStatus::ConvertedToService) {
            return;
        }
        if (Auth::check() && Customer::whereKey($entry->customer_id)->where('user_id', Auth::id())->exists()) {
            $actor = User::findOrFail(Auth::id());
            abort_unless($actor->role === Role::Customer && $actor->canUseCustomerAccess(), 403);
            session()->forget('check_in');
            $this->redirectRoute('portal');

            return;
        }
        $alreadyAuthenticated = Auth::check();
        $account = DB::transaction(function () use ($entry): ?User {
            $current = CheckIn::whereKey($entry->id)->lockForUpdate()->firstOrFail();
            $token = session('check_in.token');
            if (! is_string($token) || ! $current->browser_token_hash || ! hash_equals($current->browser_token_hash, hash('sha256', $token))
                || ! $current->browser_access_expires_at?->isFuture()) {
                return null;
            }
            $user = $current->account_id ? User::whereKey($current->account_id)->lockForUpdate()->first() : (Auth::check() ? User::whereKey(Auth::id())->lockForUpdate()->first() : null);
            $customer = Customer::whereKey($current->customer_id)->lockForUpdate()->first();
            if (! $user || $user->role !== Role::Customer || ! $user->canUseCustomerAccess() || $customer?->user_id !== $user->id
                || (Auth::check() && Auth::id() !== $user->id)) {
                return null;
            }
            $current->update(['browser_token_hash' => null]);

            return $user;
        });
        if (! $account) {
            $this->needsLogin = true;

            return;
        }
        if (! $alreadyAuthenticated) {
            Auth::login($account);
        }
        session()->regenerate();
        session()->forget('check_in');
        $this->redirectRoute('portal');
    }

    public function submit(): void
    {
        if ($this->submitted) {
            return;
        }
        $this->resetValidation();
        $token = Str::random(64);
        $input = $this->only(['name', 'phone', 'email', 'code', 'password', 'password_confirmation']);
        $this->reset('password', 'password_confirmation');
        try {
            $entry = app(SubmitCheckIn::class)->submit($input, $token, Auth::check() ? User::findOrFail(Auth::id()) : null);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $key => $messages) {
                foreach ($messages as $message) {
                    $this->addError($key, $message);
                }
            }

            return;
        }
        if (! $entry->browser_token_hash || ! hash_equals($entry->browser_token_hash, hash('sha256', $token))) {
            $this->addError('phone', 'Check-in sudah menunggu. Gunakan browser sebelumnya atau hubungi petugas.');

            return;
        }
        // Rotate and destroy the pre-handoff session; keep CSRF valid for Livewire polling.
        session()->migrate(true);
        session(['check_in.id' => $entry->id, 'check_in.token' => $token]);
        $this->reset('name', 'phone', 'email', 'code', 'password', 'password_confirmation');
        $this->submitted = true;
    }

    public function dehydrate(): void
    {
        $this->reset('password', 'password_confirmation');
    }

    public function render(): View
    {
        return view('livewire.public-check-in');
    }
}
