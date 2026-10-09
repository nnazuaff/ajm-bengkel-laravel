<?php

namespace App\Livewire;

use App\Actions\CreateBooking;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.public')]
#[Title('Booking tanpa akun')]
class GuestBooking extends Component
{
    /** @var array<string, mixed> */
    public array $form = [
        'name' => '', 'phone' => '', 'email' => '', 'license_plate' => '', 'brand' => '', 'model' => '',
        'year' => '', 'current_mileage' => '', 'booking_date' => '', 'arrival_time' => '09:00',
        'service_type' => '', 'complaint' => '', 'notes' => '',
    ];

    #[Locked]
    public bool $submitted = false;

    public function mount(): void
    {
        $this->form['booking_date'] = now()->toDateString();
    }

    public function submit(): void
    {
        if ($this->submitted) {
            return;
        }
        $this->resetValidation();
        try {
            app(CreateBooking::class)->guest($this->form);
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $key => $messages) {
                foreach ($messages as $message) {
                    $this->addError('form.'.$key, $message);
                }
            }

            return;
        }
        $this->reset('form');
        $this->submitted = true;
    }

    public function render(): View
    {
        return view('livewire.guest-booking');
    }
}
