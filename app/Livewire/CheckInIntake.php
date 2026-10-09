<?php

namespace App\Livewire;

use App\Actions\ProcessCheckIn;
use App\Enums\Role;
use App\Models\CheckIn;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class CheckInIntake extends Component
{
    #[Locked]
    public ?int $checkInId = null;

    public bool $showForm = false;

    /** @var array<string,mixed> */
    public array $form = ['vehicle_id' => '', 'vehicle' => ['license_plate' => '', 'brand' => '', 'model' => '', 'year' => '', 'color' => ''], 'current_mileage' => '', 'complaint' => '', 'mechanic_id' => '', 'identity_verified' => false];

    public function boot(): void
    {
        $this->authorize('work-services');
    }

    #[On('open-check-in-intake')]
    public function openForm(int $id): void
    {
        $this->authorize('work-services');
        CheckIn::findOrFail($id);
        $this->reset('form');
        $this->resetValidation();
        $this->checkInId = $id;
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->reset('form', 'checkInId', 'showForm');
        $this->resetValidation();
        $this->dispatch('check-in-intake-closed');
    }

    public function updatedShowForm(bool $open): void
    {
        if (! $open) {
            $this->closeForm();
        }
    }

    public function submit(): void
    {
        $this->authorize('work-services');
        $this->resetValidation();
        $input = $this->form;
        if (blank($input['vehicle_id'] ?? null)) {
            unset($input['vehicle_id']);
        } else {
            unset($input['vehicle']);
        }
        foreach (['mechanic_id'] as $key) {
            if (blank($input[$key] ?? null)) {
                unset($input[$key]);
            }
        }
        if (isset($input['vehicle'])) {
            foreach (['year', 'color'] as $key) {
                if (blank($input['vehicle'][$key] ?? null)) {
                    $input['vehicle'][$key] = null;
                }
            }
        }
        try {
            app(ProcessCheckIn::class)->process(User::findOrFail(auth()->id()), CheckIn::findOrFail($this->checkInId), $input);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $key => $messages) {
                foreach ($messages as $message) {
                    $this->addError('form.'.$key, $message);
                }
            }

            return;
        }
        $this->closeForm();
        $this->dispatch('check-in-converted');
    }

    public function render(): View
    {
        $checkIn = $this->showForm && $this->checkInId ? CheckIn::with('customer')->findOrFail($this->checkInId) : null;

        return view('livewire.check-in-intake', ['checkIn' => $checkIn, 'vehicles' => $checkIn?->customer->vehicles()->orderBy('license_plate')->get() ?? collect(), 'manager' => User::findOrFail(auth()->id())->role->managesWorkshop(), 'mechanics' => User::where('role', Role::Mechanic)->get(['id', 'name'])]);
    }
}
