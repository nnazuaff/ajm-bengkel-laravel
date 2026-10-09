<?php

namespace App\Livewire;

use App\Models\ServiceOrder;
use App\Models\Vehicle;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class ServiceHistoryDetail extends Component
{
    use WithPagination;

    public bool $showForm = false;

    #[Locked]
    public ?int $selectedVehicleId = null;

    public function boot(): void
    {
        $this->authorize('manage-workshop');
    }

    public function mount(): void
    {
        $this->authorize('manage-workshop');
    }

    #[On('open-service-history')]
    public function openHistory(int $id): void
    {
        $this->authorize('manage-workshop');
        Vehicle::withTrashed()->findOrFail($id);
        $this->selectedVehicleId = $id;
        $this->showForm = true;
        $this->resetPage('historyPage');
        $this->resetValidation();
    }

    public function closeHistory(): void
    {
        $this->authorize('manage-workshop');
        $this->reset('selectedVehicleId', 'showForm');
        $this->resetPage('historyPage');
        $this->resetValidation();
        $this->dispatch('service-history-closed');
    }

    public function updatedShowForm(bool $open): void
    {
        if (! $open) {
            $this->closeHistory();
        }
    }

    public function render(): View
    {
        $this->authorize('manage-workshop');
        $vehicle = ! $this->showForm || $this->selectedVehicleId === null ? null
            : Vehicle::withTrashed()->with('customer')->findOrFail($this->selectedVehicleId);
        $history = $vehicle === null ? null : ServiceOrder::query()->where('vehicle_id', $vehicle->id)
            ->with(['customer', 'mechanic', 'jobs', 'parts', 'documentation', 'receipt.payments'])
            ->orderByDesc('received_at')->orderByDesc('id')->paginate(10, pageName: 'historyPage');

        return view('livewire.service-history-detail', [
            'selectedVehicle' => $vehicle,
            'history' => $history,
        ]);
    }
}
