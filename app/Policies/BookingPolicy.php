<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Booking;
use App\Models\User;

class BookingPolicy
{
    public function viewAny(User $user): bool
    {
        return User::find($user->id)?->role->managesWorkshop() ?? false;
    }

    public function request(User $user): bool
    {
        $current = User::find($user->id);

        return $current?->role === Role::Customer && $current->canUseCustomerAccess();
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user) || $this->request($user);
    }

    public function view(User $user, Booking $booking): bool
    {
        return $this->viewAny($user) || ($this->request($user) && $booking->submitted_by === $user->id);
    }

    public function update(User $user, Booking $booking): bool
    {
        return $this->viewAny($user);
    }

    public function cancel(User $user, Booking $booking): bool
    {
        return $this->request($user) && $booking->submitted_by === $user->id && $booking->status->customerCanCancel();
    }

    public function convert(User $user, Booking $booking): bool
    {
        return $this->viewAny($user);
    }
}
