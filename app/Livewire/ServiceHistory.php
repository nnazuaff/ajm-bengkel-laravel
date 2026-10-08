<?php

namespace App\Livewire;

use App\Models\ServiceOrder;
use App\Models\Vehicle;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::app')]
#[Title('Riwayat servis')]
class ServiceHistory extends Component
{
    use WithPagination;

    public string $search = '';

    #[Locked]
    public ?int $selectedVehicleId = null;

    public function boot(): void
    {
        $this->authorize('manage-workshop');
    }

    public function updatedSearch(): void
    {
        $this->authorize('manage-workshop');
        $this->resetPage();
    }

    public function selectVehicle(int $id): void
    {
        $this->authorize('manage-workshop');
        Vehicle::withTrashed()->findOrFail($id);
        $this->selectedVehicleId = $id;
        $this->resetPage('historyPage');
    }

    public function render(): View
    {
        $this->authorize('manage-workshop');
        $vehicle = $this->selectedVehicleId === null ? null
            : Vehicle::withTrashed()->with('customer')->findOrFail($this->selectedVehicleId);
        $history = $vehicle === null ? null : ServiceOrder::query()->where('vehicle_id', $vehicle->id)
            ->with(['customer', 'mechanic', 'jobs', 'parts', 'documentation', 'receipt.payments'])
            ->orderByDesc('received_at')->orderByDesc('id')->paginate(10, pageName: 'historyPage');

        return view('livewire.service-history', [
            'vehicles' => Vehicle::withTrashed()->search(mb_substr($this->search, 0, 120))
                ->with('customer')->orderBy('license_plate')->paginate(15),
            'selectedVehicle' => $vehicle,
            'history' => $history,
        ]);
    }
}
