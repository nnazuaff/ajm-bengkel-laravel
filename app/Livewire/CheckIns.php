<?php

namespace App\Livewire;

use App\Actions\ManageCheckInCode;
use App\Actions\ProcessCheckIn;
use App\Models\CheckIn;
use App\Models\CheckInCode;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::app')]
#[Title('Customer check-in')]
class CheckIns extends Component
{
    use WithPagination;

    public string $status = 'waiting';

    public function boot(): void
    {
        $this->authorize('work-services');
    }

    public function generateCode(): void
    {
        app(ManageCheckInCode::class)->generate(User::findOrFail(auth()->id()));
    }

    public function disableCode(): void
    {
        app(ManageCheckInCode::class)->disable(User::findOrFail(auth()->id()));
    }

    public function openIntake(int $id): void
    {
        $this->authorize('work-services');
        CheckIn::findOrFail($id);
        $this->dispatch('open-check-in-intake', id: $id)->to(CheckInIntake::class);
    }

    public function cancel(int $id): void
    {
        app(ProcessCheckIn::class)->cancel(User::findOrFail(auth()->id()), CheckIn::findOrFail($id));
    }

    #[On('check-in-converted')]
    public function refreshQueue(): void
    {
        $this->authorize('work-services');
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        return view('livewire.check-ins', ['activeCode' => CheckInCode::find(1), 'checkIns' => CheckIn::with('customer')->when(in_array($this->status, ['waiting', 'processing', 'converted_to_service', 'cancelled'], true), fn ($q) => $q->where('status', $this->status))->orderBy('checked_in_at')->paginate(15)]);
    }
}
