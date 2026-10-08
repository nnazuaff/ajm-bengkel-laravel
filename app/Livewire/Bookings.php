<?php

namespace App\Livewire;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Support\WorkshopInput;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::app')]
#[Title('Booking')]
class Bookings extends Component
{
    use AuthorizesRequests, WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    public string $dateFilter = '';

    public function boot(): void
    {
        $this->authorize('viewAny', Booking::class);
    }

    public function updated(string $property): void
    {
        $this->authorize('viewAny', Booking::class);
        if (in_array($property, ['search', 'statusFilter', 'dateFilter'], true)) {
            $this->resetPage();
        }
    }

    public function openBooking(int $id): void
    {
        $booking = Booking::findOrFail($id);
        $this->authorize('view', $booking);
        $this->dispatch('open-booking-review', id: $booking->id)->to(BookingReview::class);
    }

    #[On('booking-updated')]
    public function refreshBookings(string $message): void
    {
        $this->authorize('viewAny', Booking::class);
        session()->flash('status', $message);
    }

    public function render(): View
    {
        $this->authorize('viewAny', Booking::class);
        $search = Str::squish(Str::substr($this->search, 0, 120));
        $bookings = Booking::query()
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->whereRaw("LOWER(booking_number) LIKE ? ESCAPE '!'", [WorkshopInput::like(Str::lower($search))])
                ->orWhereRaw("LOWER(name) LIKE ? ESCAPE '!'", [WorkshopInput::like(Str::lower($search))])
                ->orWhereRaw("phone LIKE ? ESCAPE '!'", [WorkshopInput::like(WorkshopInput::phone($search))])
                ->orWhereRaw("license_plate LIKE ? ESCAPE '!'", [WorkshopInput::like(WorkshopInput::plate($search))])))
            ->when($this->statusFilter !== '', fn (Builder $query) => $query->where('status', $this->statusFilter))
            ->when($this->dateFilter !== '', fn (Builder $query) => $query->whereDate('booking_date', $this->dateFilter))
            ->orderByDesc('booking_date')->orderBy('arrival_time')->orderByDesc('id')->paginate(15);

        return view('livewire.bookings', ['bookings' => $bookings, 'statuses' => BookingStatus::cases()]);
    }
}
