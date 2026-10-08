<?php

namespace App\Livewire;

use App\Enums\ServiceStatus;
use App\Models\Vehicle;
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
#[Title('Kendaraan')]
class Vehicles extends Component
{
    use AuthorizesRequests, WithPagination;

    public string $search = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Vehicle::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->authorize('create', Vehicle::class);
        $this->dispatch('create-vehicle')->to(VehicleEditor::class);
    }

    public function edit(int $id): void
    {
        $this->authorize('viewAny', Vehicle::class);
        $this->authorize('update', Vehicle::findOrFail($id));
        $this->dispatch('edit-vehicle', id: $id)->to(VehicleEditor::class);
    }

    #[On('vehicle-saved')]
    public function saved(): void
    {
        $this->authorize('viewAny', Vehicle::class);
        $this->resetPage();
        session()->flash('status', 'Data kendaraan berhasil disimpan.');
    }

    public function archive(int $id): void
    {
        $this->authorize('viewAny', Vehicle::class);
        $this->resetValidation('archive');
        DB::transaction(function () use ($id): void {
            $record = Vehicle::query()->whereKey($id)->lockForUpdate()->firstOrFail();
            $this->authorize('delete', $record);
            if ($record->serviceOrders()->whereNotIn('status', [ServiceStatus::Delivered, ServiceStatus::Cancelled])->exists()) {
                throw ValidationException::withMessages(['archive' => $record->license_plate.': Kendaraan masih memiliki servis aktif.']);
            }
            $record->delete();
        }, attempts: 5);

        $this->resetPage();
        session()->flash('status', 'Kendaraan berhasil diarsipkan.');
    }

    public function render(): View
    {
        $this->authorize('viewAny', Vehicle::class);

        return view('livewire.vehicles', [
            'vehicles' => Vehicle::query()->search($this->search)->with('customer')->orderBy('license_plate')->paginate(15),
        ]);
    }
}
