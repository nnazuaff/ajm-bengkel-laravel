<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\ServiceDocumentation;
use App\Models\ServiceOrder;
use App\Models\User;

class ServiceDocumentationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role->isStaff();
    }

    public function viewOrder(User $user, ServiceOrder $order): bool
    {
        return $user->role->managesWorkshop()
            || ($user->role === Role::Mechanic && $order->mechanic_id === $user->id);
    }

    public function view(User $user, ServiceDocumentation $photo): bool
    {
        return ! $photo->trashed() && $this->viewOrder($user, $photo->serviceOrder);
    }

    public function create(User $user, ServiceOrder $order): bool
    {
        return $this->viewOrder($user, $order);
    }

    public function delete(User $user, ServiceDocumentation $photo): bool
    {
        return $this->view($user, $photo);
    }
}
