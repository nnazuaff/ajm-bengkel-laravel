<?php

namespace App\Policies;

use App\Models\InventoryItem;
use App\Models\User;

class InventoryItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role->managesWorkshop();
    }

    public function view(User $user, InventoryItem $item): bool
    {
        return $user->role->managesWorkshop();
    }

    public function create(User $user): bool
    {
        return $user->role->managesWorkshop();
    }

    public function update(User $user, InventoryItem $item): bool
    {
        return $user->role->managesWorkshop();
    }

    public function delete(User $user, InventoryItem $item): bool
    {
        return $user->role->managesWorkshop();
    }
}
