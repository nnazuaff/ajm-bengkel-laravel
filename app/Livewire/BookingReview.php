<?php

namespace App\Livewire;

use App\Actions\ConvertBooking;
use App\Actions\UpdateBooking;
use App\Enums\Role;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class BookingReview extends Component
{
    use AuthorizesRequests;

    public bool $showForm = false;

    #[Locked]
    public ?int $selectedBookingId = null;

    /** @var array<string, mixed> */
    public array $detail = ['status' => '', 'booking_date' => '', 'arrival_time' => '', 'admin_notes' => '', 'mechanic_id' => ''];

    public function boot(): void
    {
        $this->authorize('viewAny', Booking::class);
    }

    #[On('open-booking-review')]
    public function openBooking(int $id): void
    {
        $this->authorize('viewAny', Booking::class);
        $booking = Booking::findOrFail($id);
        $this->authorize('view', $booking);
        $this->selectedBookingId = $booking->id;
        $this->showForm = true;
        $this->fillDetail($booking);
        $this->resetValidation();
    }

    public function closeBooking(): void
    {
        $this->authorize('viewAny', Booking::class);
        $id = $this->selectedBookingId;
        $this->reset('selectedBookingId', 'detail', 'showForm');
        $this->resetValidation();
        $this->dispatch('booking-review-closed', id: $id);
    }

    public function updatedShowForm(bool $open): void
    {
        if (! $open) {
            $this->closeBooking();
        }
    }

    public function saveBooking(): void
    {
        $this->authorize('viewAny', Booking::class);
        abort_if($this->selectedBookingId === null, 404);
        $booking = Booking::findOrFail($this->selectedBookingId);
        $this->authorize('update', $booking);
        /** @var User $actor */
        $actor = Auth::user();
        $this->resetValidation();
        try {
            $booking = app(UpdateBooking::class)->update($actor, $booking, $this->detail);
        } catch (ValidationException $exception) {
            $this->showErrors($exception);

            return;
        }
        $this->fillDetail($booking);
        session()->flash('status', 'Booking berhasil diperbarui.');
        $this->dispatch('booking-updated', message: 'Booking berhasil diperbarui.');
    }

    public function convert(): void
    {
        $this->authorize('viewAny', Booking::class);
        abort_if($this->selectedBookingId === null, 404);
        $booking = Booking::findOrFail($this->selectedBookingId);
        $this->authorize('convert', $booking);
        /** @var User $actor */
        $actor = Auth::user();
        $this->resetValidation();
        $this->validate([
            'detail.mechanic_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('role', Role::Mechanic->value)->whereNull('deleted_at')],
        ]);
        $mechanicId = filled($this->detail['mechanic_id'] ?? null) ? (int) $this->detail['mechanic_id'] : null;
        try {
            $order = app(ConvertBooking::class)->convert($actor, $booking, $mechanicId, array_intersect_key($this->detail, array_flip(['link_account', 'restore_archived', 'ownership_verified'])));
        } catch (ValidationException $exception) {
            $this->showErrors($exception);

            return;
        }
        $this->closeBooking();
        $this->dispatch('booking-updated', message: 'Servis berhasil dibuat: '.$order->service_number.'.');
    }

    private function fillDetail(Booking $booking): void
    {
        $this->detail = [
            'link_account' => $booking->submitted_by !== null, 'restore_archived' => false, 'ownership_verified' => false,
            'status' => $booking->status->value,
            'booking_date' => $booking->booking_date->toDateString(),
            'arrival_time' => substr($booking->arrival_time, 0, 5),
            'admin_notes' => $booking->admin_notes ?? '', 'mechanic_id' => '',
        ];
    }

    private function showErrors(ValidationException $exception): void
    {
        foreach ($exception->errors() as $key => $messages) {
            foreach ($messages as $message) {
                $this->addError('detail.'.$key, $message);
            }
        }
    }

    public function render(): View
    {
        $this->authorize('viewAny', Booking::class);
        $selectedBooking = $this->showForm && $this->selectedBookingId !== null
            ? Booking::query()->with(['submitter', 'serviceOrder'])->findOrFail($this->selectedBookingId) : null;
        if ($selectedBooking !== null) {
            $this->authorize('view', $selectedBooking);
        }

        $matchingVehicle = $selectedBooking !== null ? Vehicle::withTrashed()->where('license_plate', $selectedBooking->license_plate)->first() : null;
        $matchingCustomer = $matchingVehicle !== null
            ? Customer::withTrashed()->find($matchingVehicle->customer_id)
            : ($selectedBooking !== null ? Customer::withTrashed()->where('phone', $selectedBooking->phone)->first() : null);

        return view('livewire.booking-review', [
            'matchingCustomer' => $matchingCustomer, 'matchingVehicle' => $matchingVehicle,
            'selectedBooking' => $selectedBooking,
            'allowedStatuses' => $selectedBooking !== null
                ? array_unique([$selectedBooking->status, ...$selectedBooking->status->transitions()], SORT_REGULAR) : [],
            'mechanics' => $selectedBooking !== null
                ? User::query()->where('role', Role::Mechanic)->orderBy('name')->get(['id', 'name']) : collect(),
        ]);
    }
}
