<?php

namespace App\Livewire;

use App\Actions\CreateBooking;
use App\Support\BookingWhatsapp;
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

    /** @return array{url: ?string, number: string}|null */
    public function submit(): ?array
    {
        if ($this->submitted) {
            return null;
        }
        $this->resetValidation();
        try {
            $booking = app(CreateBooking::class)->guest($this->form);
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $key => $messages) {
                foreach ($messages as $message) {
                    $this->addError('form.'.$key, $message);
                }
            }

            return null;
        }
        $this->reset('form');
        $this->submitted = true;

        return ['url' => BookingWhatsapp::url($booking), 'number' => $booking->booking_number];
    }

    public function render(): View
    {
        return view('livewire.guest-booking');
    }
}
