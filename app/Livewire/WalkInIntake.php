<?php

namespace App\Livewire;

use App\Actions\ReceiveWalkIn;
use App\Enums\Role;
use App\Models\ServiceOrder;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\WorkshopInput;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class WalkInIntake extends Component
{
    use AuthorizesRequests;

    public bool $showIntake = false;

    public bool $newVehicle = false;

    public string $intakeSearch = '';

    #[Locked]
    public ?int $selectedVehicleId = null;

    /** @var array<string, mixed> */
    public array $intake = [
        'current_mileage' => '',
        'complaint' => '',
        'notes' => '',
        'mechanic_id' => '',
        'customer' => ['name' => '', 'phone' => '', 'email' => '', 'address' => '', 'notes' => ''],
        'vehicle' => ['license_plate' => '', 'brand' => '', 'model' => '', 'year' => '', 'color' => ''],
    ];

    public function boot(): void
    {
        $this->authorize('create', ServiceOrder::class);
    }

    public function mount(): void
    {
        $this->authorize('create', ServiceOrder::class);
    }

    public function updatedShowIntake(bool $visible): void
    {
        $this->authorize('create', ServiceOrder::class);
        if (! $visible) {
            $this->closeIntake();
        }
    }

    #[On('open-walk-in-intake')]
    public function openIntake(): void
    {
        $this->authorize('create', ServiceOrder::class);
        $this->reset('intake', 'intakeSearch', 'selectedVehicleId', 'newVehicle');
        $this->resetValidation();
        $this->showIntake = true;
    }

    public function closeIntake(): void
    {
        $this->authorize('create', ServiceOrder::class);
        $this->reset('intake', 'intakeSearch', 'selectedVehicleId', 'showIntake', 'newVehicle');
        $this->resetValidation();
    }

    public function selectVehicle(int $id): void
    {
        $this->authorize('create', ServiceOrder::class);
        $vehicle = Vehicle::query()->whereHas('customer', fn (Builder $query) => $query->whereNull('deleted_at'))->findOrFail($id);
        $this->selectedVehicleId = $vehicle->id;
        $this->newVehicle = false;
        $this->intake['current_mileage'] = $vehicle->latest_mileage;
        $this->resetValidation();
    }

    public function useNewVehicle(): void
    {
        $this->authorize('create', ServiceOrder::class);
        $this->selectedVehicleId = null;
        $this->newVehicle = true;
        $this->intake['current_mileage'] = '';
        $this->resetValidation();
    }

    public function useExistingVehicle(): void
    {
        $this->authorize('create', ServiceOrder::class);
        $this->selectedVehicleId = null;
        $this->newVehicle = false;
        $this->resetValidation();
    }

    public function receiveWalkIn(): void
    {
        $this->authorize('create', ServiceOrder::class);
        /** @var User $actor */
        $actor = Auth::user();
        $this->resetValidation();
        if (! $this->newVehicle && $this->selectedVehicleId === null) {
            $this->addError('intake.vehicle_id', 'Pilih motor terdaftar atau gunakan motor baru.');

            return;
        }

        $masters = [];
        if ($this->newVehicle) {
            $this->validate([
                'intake.customer' => ['required', 'array'],
                'intake.vehicle' => ['required', 'array'],
            ]);
            $masters = [
                'customer' => array_map(fn ($value) => $value === '' ? null : $value, $this->intake['customer'] ?? []),
                'vehicle' => array_map(fn ($value) => $value === '' ? null : $value, $this->intake['vehicle'] ?? []),
            ];
        }

        try {
            $order = app(ReceiveWalkIn::class)->receive($actor, [
                ...$masters,
                'vehicle_id' => $this->selectedVehicleId,
                'current_mileage' => $this->intake['current_mileage'] ?? '',
                'complaint' => $this->intake['complaint'] ?? '',
                'notes' => $this->intake['notes'] ?? null,
                'mechanic_id' => ($this->intake['mechanic_id'] ?? '') === '' ? null : $this->intake['mechanic_id'],
            ]);
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $key => $messages) {
                foreach ($messages as $message) {
                    $this->addError('intake.'.$key, $message);
                }
            }

            return;
        }

        $this->closeIntake();
        session()->flash('status', 'Servis '.$order->service_number.' berhasil diterima.');
        $this->dispatch('walk-in-received');
    }

    public function render(): View
    {
        $this->authorize('create', ServiceOrder::class);
        $matches = collect();
        $term = Str::squish(Str::substr($this->intakeSearch, 0, 120));

        if ($this->showIntake && ! $this->newVehicle && Str::length($term) >= 2 && $this->selectedVehicleId === null) {
            $matches = Vehicle::query()->with('customer')
                ->whereHas('customer', fn (Builder $query) => $query->whereNull('deleted_at'))
                ->where(fn (Builder $query) => $query
                    ->whereRaw("license_plate LIKE ? ESCAPE '!'", [WorkshopInput::like(WorkshopInput::plate($term))])
                    ->orWhereHas('customer', fn (Builder $query) => $query->whereRaw("phone LIKE ? ESCAPE '!'", [WorkshopInput::like(WorkshopInput::phone($term))])))
                ->orderBy('license_plate')->limit(11)->get();
        }

        return view('livewire.walk-in-intake', [
            'mechanics' => $this->showIntake ? User::query()->where('role', Role::Mechanic)->orderBy('name')->get(['id', 'name']) : collect(),
            'vehicleMatches' => $matches->take(10),
            'refineIntakeSearch' => $matches->count() > 10,
            'selectedVehicle' => $this->showIntake && $this->selectedVehicleId !== null
                ? Vehicle::query()->with('customer')->whereHas('customer', fn (Builder $query) => $query->whereNull('deleted_at'))->findOrFail($this->selectedVehicleId) : null,
        ]);
    }
}
