<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Receipt;
use App\Models\User;

class ReceiptPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role->managesWorkshop();
    }

    public function view(User $user, Receipt $receipt): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Receipt $receipt): bool
    {
        return $this->viewAny($user);
    }

    public function finalize(User $user, Receipt $receipt): bool
    {
        return $this->viewAny($user);
    }

    public function pay(User $user, Receipt $receipt): bool
    {
        return $this->viewAny($user);
    }

    public function void(User $user, Receipt $receipt): bool
    {
        return $user->role === Role::Owner;
    }

    public function reversePayment(User $user, Receipt $receipt): bool
    {
        return $user->role === Role::Owner;
    }
}
