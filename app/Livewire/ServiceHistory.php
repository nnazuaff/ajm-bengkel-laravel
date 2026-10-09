<?php

namespace App\Livewire;

use App\Models\Vehicle;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::app')]
#[Title('Riwayat servis')]
class ServiceHistory extends Component
{
    use WithPagination;

    public string $search = '';

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
        $this->dispatch('open-service-history', id: $id)->to(ServiceHistoryDetail::class);
    }

    public function render(): View
    {
        $this->authorize('manage-workshop');

        return view('livewire.service-history', [
            'vehicles' => Vehicle::withTrashed()->search(mb_substr($this->search, 0, 120))
                ->with('customer')->orderBy('license_plate')->paginate(15),
        ]);
    }
}
