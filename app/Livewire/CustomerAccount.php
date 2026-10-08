<?php

namespace App\Livewire;

use App\Actions\LinkCustomerAccount;
use App\Enums\Role;
use App\Models\Customer;
use App\Models\User;
use App\Support\WorkshopInput;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class CustomerAccount extends Component
{
    #[Locked]
    public ?int $customerId = null;

    public bool $showForm = false;

    public string $userId = '';

    public string $search = '';

    public bool $ownershipVerified = false;

    #[Locked]
    public string $status = '';

    public function boot(): void
    {
        $this->actor();
    }

    public function mount(?int $customerId = null): void
    {
        $this->actor();
        if ($customerId !== null) {
            $this->openForm($customerId);
        }
    }

    #[On('open-customer-account')]
    public function openForm(int $id): void
    {
        $actor = $this->actor();
        $customer = Customer::findOrFail($id);
        Gate::forUser($actor)->authorize('update', $customer);
        $this->reset('userId', 'search', 'ownershipVerified', 'status');
        $this->resetValidation();
        $this->customerId = $customer->id;
        $this->userId = (string) $customer->user_id;
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->actor();
        $this->reset('customerId', 'userId', 'search', 'ownershipVerified', 'status', 'showForm');
        $this->resetValidation();
        $this->dispatch('customer-account-closed');
    }

    public function updatedShowForm(bool $open): void
    {
        if (! $open) {
            $this->closeForm();
        }
    }

    public function updatedUserId(): void
    {
        $this->ownershipVerified = false;
        $this->status = '';
        $this->resetValidation();
    }

    public function save(): void
    {
        $actor = $this->actor();
        $this->status = '';
        $this->validate(['userId' => ['nullable', 'integer', 'min:1']]);
        $customer = app(LinkCustomerAccount::class)->link(
            $actor, Customer::findOrFail($this->customerId),
            $this->userId === '' ? null : (int) $this->userId, $this->ownershipVerified,
        );
        $this->closeForm();
        $this->dispatch('customer-account-saved')->to(Customers::class);
    }

    private function actor(): User
    {
        $actor = User::query()->find(Auth::id()) ?? throw new AuthorizationException;
        Gate::forUser($actor)->authorize('manage-workshop');

        return $actor;
    }

    public function render(): View
    {
        $actor = $this->actor();
        if (! $this->showForm || $this->customerId === null) {
            return view('livewire.customer-account');
        }
        $customer = Customer::with('user')->findOrFail($this->customerId);
        Gate::forUser($actor)->authorize('update', $customer);

        $term = WorkshopInput::like(Str::lower(Str::squish(Str::substr($this->search, 0, 120))));
        $eligible = User::query()->where('role', Role::Customer)
            ->when(config('fortify.require_email_verification'), fn (Builder $query) => $query->whereNotNull('email_verified_at'))
            ->whereNotIn('id', Customer::withTrashed()->whereKeyNot($customer->id)->whereNotNull('user_id')->select('user_id'));
        $selectedAccount = $this->userId === '' ? null : (clone $eligible)->whereKey($this->userId)->first(['id', 'name', 'email']);
        $accounts = $eligible->where(fn (Builder $query) => $query->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$term])
            ->orWhereRaw("LOWER(email) LIKE ? ESCAPE '!'", [$term]))
            ->orderBy('name')->orderBy('id')->limit(51)->get(['id', 'name', 'email']);

        return view('livewire.customer-account', [
            'customer' => $customer,
            'accounts' => $accounts->take(50),
            'hasMore' => $accounts->count() > 50,
            'selectedAccount' => $selectedAccount,
        ]);
    }
}
