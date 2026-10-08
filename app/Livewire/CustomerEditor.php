<?php

namespace App\Livewire;

use App\Livewire\Forms\CustomerForm;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class CustomerEditor extends Component
{
    use AuthorizesRequests;

    public CustomerForm $form;

    #[Locked]
    public ?int $editingId = null;

    public bool $showForm = false;

    public function boot(): void
    {
        $actor = User::find(Auth::id()) ?? throw new AuthorizationException;
        Gate::forUser($actor)->authorize('viewAny', Customer::class);
    }

    public function mount(): void
    {
        $this->authorize('viewAny', Customer::class);
    }

    public function closeForm(): void
    {
        $this->authorize('viewAny', Customer::class);
        $this->reset('editingId', 'showForm');
        $this->form->reset();
        $this->resetValidation();
        $this->dispatch('customer-editor-closed');
    }

    public function updatedShowForm(bool $open): void
    {
        if (! $open) {
            $this->closeForm();
        }
    }

    #[On('create-customer')]
    public function create(): void
    {
        $this->authorize('create', Customer::class);
        $this->editingId = null;
        $this->form->reset();
        $this->resetValidation();
        $this->showForm = true;
    }

    #[On('edit-customer')]
    public function edit(int $id): void
    {
        $this->authorize('viewAny', Customer::class);
        $customer = Customer::findOrFail($id);
        $this->authorize('update', $customer);
        $this->editingId = $customer->id;
        $this->form->fill([
            'name' => $customer->name, 'phone' => $customer->phone,
            'email' => $customer->email ?? '', 'address' => $customer->address ?? '',
            'notes' => $customer->notes ?? '',
        ]);
        $this->resetValidation();
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->authorize('viewAny', Customer::class);
        try {
            DB::transaction(function (): void {
                $customer = $this->editingId === null ? null
                    : Customer::query()->whereKey($this->editingId)->lockForUpdate()->firstOrFail();
                $this->authorize($customer ? 'update' : 'create', $customer ?? Customer::class);
                $data = $this->form->validatedData($customer);

                $customer ? $customer->update($data) : Customer::create($data);
            }, attempts: 5);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['form.phone' => 'Nomor telepon sudah tercatat. Cari pelanggan yang ada, termasuk arsip.']);
        }

        $this->closeForm();
        $this->dispatch('customer-saved')->to(Customers::class);
    }

    public function render(): View
    {
        $this->authorize('viewAny', Customer::class);

        return view('livewire.customer-editor');
    }
}
