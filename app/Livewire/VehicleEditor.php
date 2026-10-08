<?php

namespace App\Livewire;

use App\Livewire\Forms\VehicleForm;
use App\Models\Customer;
use App\Models\User;
use App\Models\Vehicle;
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

class VehicleEditor extends Component
{
    use AuthorizesRequests;

    public VehicleForm $form;

    #[Locked]
    public ?int $editingId = null;

    public bool $showForm = false;

    public function boot(): void
    {
        $actor = User::find(Auth::id()) ?? throw new AuthorizationException;
        Gate::forUser($actor)->authorize('viewAny', Vehicle::class);
    }

    public function mount(): void
    {
        $this->authorize('viewAny', Vehicle::class);
    }

    public function closeForm(): void
    {
        $this->authorize('viewAny', Vehicle::class);
        $this->reset('editingId', 'showForm');
        $this->form->reset();
        $this->resetValidation();
        $this->dispatch('vehicle-editor-closed');
    }

    public function updatedShowForm(bool $open): void
    {
        if (! $open) {
            $this->closeForm();
        }
    }

    #[On('create-vehicle')]
    public function create(): void
    {
        $this->authorize('create', Vehicle::class);
        $this->editingId = null;
        $this->form->reset();
        $this->resetValidation();
        $this->showForm = true;
    }

    #[On('edit-vehicle')]
    public function edit(int $id): void
    {
        $this->authorize('viewAny', Vehicle::class);
        $vehicle = Vehicle::findOrFail($id);
        $this->authorize('update', $vehicle);
        $this->editingId = $vehicle->id;
        $this->form->fill([
            'customer_id' => $vehicle->customer_id, 'license_plate' => $vehicle->license_plate,
            'brand' => $vehicle->brand, 'model' => $vehicle->model, 'year' => $vehicle->year ?? '',
            'color' => $vehicle->color ?? '', 'chassis_number' => $vehicle->chassis_number ?? '',
            'engine_number' => $vehicle->engine_number ?? '', 'latest_mileage' => $vehicle->latest_mileage,
            'notes' => $vehicle->notes ?? '',
        ]);
        $this->resetValidation();
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->authorize('viewAny', Vehicle::class);
        try {
            DB::transaction(function (): void {
                // Same lock order as walk-in intake: vehicle, then customer.
                $vehicle = $this->editingId === null ? null
                    : Vehicle::query()->whereKey($this->editingId)->lockForUpdate()->firstOrFail();
                $this->authorize($vehicle ? 'update' : 'create', $vehicle ?? Vehicle::class);
                Customer::query()->whereKey($this->form->customer_id)->lockForUpdate()->first();
                $data = $this->form->validatedData($vehicle);

                $vehicle ? $vehicle->update($data) : Vehicle::create($data);
            }, attempts: 5);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['form.license_plate' => 'Pelat nomor sudah tercatat. Cari kendaraan yang ada, termasuk arsip.']);
        }

        $this->closeForm();
        $this->dispatch('vehicle-saved')->to(Vehicles::class);
    }

    public function render(): View
    {
        $this->authorize('viewAny', Vehicle::class);

        return view('livewire.vehicle-editor', [
            'customers' => $this->showForm ? Customer::query()->orderBy('name')->get(['id', 'name', 'phone']) : collect(),
        ]);
    }
}
