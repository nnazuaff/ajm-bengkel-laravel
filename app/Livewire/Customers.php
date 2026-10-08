<?php

namespace App\Livewire;

use App\Enums\ServiceStatus;
use App\Models\Customer;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::app')]
#[Title('Pelanggan')]
class Customers extends Component
{
    use AuthorizesRequests, WithPagination;

    public string $search = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Customer::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->authorize('create', Customer::class);
        $this->dispatch('create-customer')->to(CustomerEditor::class);
    }

    public function edit(int $id): void
    {
        $this->authorize('viewAny', Customer::class);
        $this->authorize('update', Customer::findOrFail($id));
        $this->dispatch('edit-customer', id: $id)->to(CustomerEditor::class);
    }

    #[On('customer-saved')]
    public function saved(): void
    {
        $this->authorize('viewAny', Customer::class);
        $this->resetPage();
        session()->flash('status', 'Data pelanggan berhasil disimpan.');
    }

    public function openAccount(int $id): void
    {
        $this->authorize('viewAny', Customer::class);
        $this->authorize('update', Customer::findOrFail($id));
        $this->dispatch('open-customer-account', id: $id)->to(CustomerAccount::class);
    }

    #[On('customer-account-saved')]
    public function accountSaved(): void
    {
        $this->authorize('viewAny', Customer::class);
        $this->resetPage();
        session()->flash('status', 'Hubungan akun berhasil diperbarui.');
    }

    public function archive(int $id): void
    {
        $this->authorize('viewAny', Customer::class);
        $this->resetValidation('archive');
        DB::transaction(function () use ($id): void {
            $record = Customer::query()->whereKey($id)->lockForUpdate()->firstOrFail();
            $this->authorize('delete', $record);
            if ($record->vehicles()->exists()) {
                throw ValidationException::withMessages(['archive' => $record->name.': Arsipkan semua kendaraan pelanggan terlebih dahulu.']);
            }
            if ($record->serviceOrders()->whereNotIn('status', [ServiceStatus::Delivered, ServiceStatus::Cancelled])->exists()) {
                throw ValidationException::withMessages(['archive' => $record->name.': Pelanggan masih memiliki servis aktif.']);
            }
            $record->delete();
        }, attempts: 5);

        $this->resetPage();
        session()->flash('status', 'Pelanggan berhasil diarsipkan.');
    }

    public function render(): View
    {
        $this->authorize('viewAny', Customer::class);

        return view('livewire.customers', [
            'customers' => Customer::query()->search($this->search)->withCount('vehicles')->orderBy('name')->orderBy('id')->paginate(15),
        ]);
    }
}
