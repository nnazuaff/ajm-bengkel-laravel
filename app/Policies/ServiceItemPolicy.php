<?php

namespace App\Policies;

use App\Models\ServiceItem;
use App\Models\ServiceOrder;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class ServiceItemPolicy
{
    public function view(User $user, ServiceItem $part): bool
    {
        return $user->role->isStaff() && Gate::forUser($user)->allows('view', $part->serviceOrder);
    }

    public function create(User $user, ServiceOrder $order): bool
    {
        return $user->role->isStaff() && Gate::forUser($user)->allows('update', $order);
    }

    public function update(User $user, ServiceItem $part): bool
    {
        return $this->create($user, $part->serviceOrder);
    }
}
