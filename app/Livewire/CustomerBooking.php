<?php

namespace App\Livewire;

use App\Actions\UpdateBooking;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::app')]
#[Title('Booking saya')]
class CustomerBooking extends Component
{
    use AuthorizesRequests, WithPagination;

    public function boot(): void
    {
        $this->authorize('request', Booking::class);
    }

    public function createBooking(): void
    {
        $this->authorize('request', Booking::class);
        $this->dispatch('open-booking-request')->to(BookingRequest::class);
    }

    #[On('booking-created')]
    public function bookingCreated(): void
    {
        $this->authorize('request', Booking::class);
        $this->resetPage();
        session()->flash('status', 'Booking berhasil diajukan. Tunggu konfirmasi bengkel.');
    }

    public function cancel(int $id): void
    {
        $this->authorize('request', Booking::class);
        $booking = Booking::findOrFail($id);
        $this->authorize('cancel', $booking);
        $actor = User::findOrFail(Auth::id());
        app(UpdateBooking::class)->cancel($actor, $booking);
        session()->flash('status', 'Booking berhasil dibatalkan.');
    }

    public function render(): View
    {
        $this->authorize('request', Booking::class);

        return view('livewire.customer-booking', [
            'bookings' => Booking::query()->where('submitted_by', Auth::id())->orderByDesc('id')->paginate(10),
        ]);
    }
}
