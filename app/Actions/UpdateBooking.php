<?php

namespace App\Actions;

use App\Enums\BookingStatus;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UpdateBooking
{
    /** @param array<string, mixed> $input */
    public function update(User $actor, Booking $booking, array $input): Booking
    {
        return DB::transaction(function () use ($actor, $booking, $input): Booking {
            $current = Booking::query()->lockForUpdate()->findOrFail($booking->id);
            Gate::forUser($actor)->authorize('update', $current);
            $data = Validator::make($input, [
                'status' => ['required', 'string', Rule::enum(BookingStatus::class)],
                'admin_notes' => ['nullable', 'string', 'max:5000'],
            ])->validate();
            $next = BookingStatus::from($data['status']);
            if ($current->status->transitions() === [] || ($next !== $current->status && ! in_array($next, $current->status->transitions(), true))) {
                throw ValidationException::withMessages(['status' => 'Perubahan status tidak tersedia. Muat ulang booking.']);
            }
            if (in_array($next, [BookingStatus::Cancelled, BookingStatus::Rejected], true) && blank($data['admin_notes'] ?? null)) {
                throw ValidationException::withMessages(['admin_notes' => 'Catat alasan penolakan atau pembatalan.']);
            }
            if ($next === BookingStatus::Rescheduled) {
                $data = [...$data, ...Validator::make($input, CreateBooking::slotRules())->validate()];
            }
            if (array_key_exists('admin_notes', $data)) {
                $data['admin_notes'] = filled($data['admin_notes']) ? trim($data['admin_notes']) : null;
            }

            return $this->save($actor, $current, $data, 'booking.updated');
        }, attempts: 5);
    }

    public function cancel(User $actor, Booking $booking): Booking
    {
        return DB::transaction(function () use ($actor, $booking): Booking {
            $current = Booking::query()->lockForUpdate()->findOrFail($booking->id);
            Gate::forUser($actor)->authorize('cancel', $current);

            return $this->save($actor, $current, ['status' => BookingStatus::Cancelled], 'booking.cancelled');
        }, attempts: 5);
    }

    /** @param array<string, mixed> $data */
    private function save(User $actor, Booking $booking, array $data, string $action): Booking
    {
        $before = $booking->status;
        $booking->update($data);
        if ($booking->wasChanged()) {
            AuditLog::create([
                'actor_id' => $actor->id, 'action' => $action,
                'entity_type' => Booking::class, 'entity_id' => $booking->id,
                'context' => ['before' => $before->value, 'after' => $booking->status->value, 'changed_fields' => array_keys($booking->getChanges())],
            ]);
        }

        return $booking;
    }
}
