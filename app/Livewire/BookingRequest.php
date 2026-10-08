<?php

namespace App\Livewire;

use App\Actions\CreateBooking;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Component;

class BookingRequest extends Component
{
    use AuthorizesRequests;

    public bool $showForm = false;

    /** @var array<string, mixed> */
    public array $form = [
        'name' => '', 'phone' => '', 'email' => '', 'license_plate' => '', 'brand' => '', 'model' => '',
        'year' => '', 'current_mileage' => '', 'booking_date' => '', 'arrival_time' => '09:00',
        'service_type' => '', 'complaint' => '', 'notes' => '',
    ];

    public function boot(): void
    {
        $this->authorize('request', Booking::class);
    }

    #[On('open-booking-request')]
    public function openForm(): void
    {
        $this->authorize('request', Booking::class);
        /** @var User $actor */
        $actor = Auth::user();
        $this->reset('form');
        $this->resetValidation();
        $this->showForm = true;
        $this->form['name'] = $actor->name;
        $this->form['email'] = $actor->email;
        $this->form['booking_date'] = now()->toDateString();
    }

    public function useVehicle(int $id): void
    {
        $this->authorize('request', Booking::class);
        $customer = Customer::query()->where('user_id', Auth::id())->firstOrFail();
        $vehicle = Vehicle::query()->where('customer_id', $customer->id)->findOrFail($id);
        $this->form = [...$this->form, ...$vehicle->only(['license_plate', 'brand', 'model', 'year']),
            'current_mileage' => $vehicle->latest_mileage, 'name' => $customer->name, 'phone' => $customer->phone,
            'email' => $customer->email ?? User::findOrFail(Auth::id())->email];
        $this->resetValidation();
    }

    public function submit(): void
    {
        $this->authorize('request', Booking::class);
        /** @var User $actor */
        $actor = Auth::user();
        $this->resetValidation();
        try {
            app(CreateBooking::class)->create($actor, $this->form);
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $key => $messages) {
                foreach ($messages as $message) {
                    $this->addError('form.'.$key, $message);
                }
            }

            return;
        }
        $this->closeForm();
        $this->dispatch('booking-created');
    }

    public function closeForm(): void
    {
        $this->authorize('request', Booking::class);
        $this->reset('form', 'showForm');
        $this->resetValidation();
        $this->dispatch('booking-request-closed');
    }

    public function updatedShowForm(bool $open): void
    {
        if (! $open) {
            $this->closeForm();
        }
    }

    public function render(): View
    {
        $this->authorize('request', Booking::class);

        return view('livewire.booking-request', [
            'ownVehicles' => $this->showForm
                ? Vehicle::query()->whereHas('customer', fn ($query) => $query->where('user_id', Auth::id()))->orderBy('license_plate')->get()
                : collect(),
        ]);
    }
}
