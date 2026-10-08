<?php

namespace App\Policies;

use App\Models\ServiceJob;
use App\Models\ServiceOrder;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class ServiceJobPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role->isStaff();
    }

    public function view(User $user, ServiceJob $job): bool
    {
        return $this->create($user, $job->serviceOrder)
            && ($user->role->managesWorkshop() || $job->mechanic_id === null || $job->mechanic_id === $user->id);
    }

    public function create(User $user, ServiceOrder $order): bool
    {
        return $this->viewAny($user) && Gate::forUser($user)->allows('update', $order);
    }

    public function update(User $user, ServiceJob $job): bool
    {
        return $this->view($user, $job);
    }
}
