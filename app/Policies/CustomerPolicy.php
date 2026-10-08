<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role->managesWorkshop();
    }

    public function view(User $user, Customer $customer): bool
    {
        return $user->role->managesWorkshop()
            || ($user->role === Role::Customer && $customer->user_id === $user->id);
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $user->role->managesWorkshop();
    }

    public function create(User $user): bool
    {
        return $user->role->managesWorkshop();
    }

    public function update(User $user, Customer $customer): bool
    {
        return $user->role->managesWorkshop();
    }
}
