<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\ServiceOrder;
use App\Models\User;

class ServiceOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role->isStaff();
    }

    public function view(User $user, ServiceOrder $order): bool
    {
        return $user->role->managesWorkshop()
            || ($user->role === Role::Mechanic && $order->mechanic_id === $user->id)
            || ($user->role === Role::Customer && $order->customer->user_id === $user->id);
    }

    public function create(User $user): bool
    {
        return $user->role->managesWorkshop();
    }

    public function update(User $user, ServiceOrder $order): bool
    {
        return $user->role->managesWorkshop()
            || ($user->role === Role::Mechanic && $order->mechanic_id === $user->id);
    }
}
