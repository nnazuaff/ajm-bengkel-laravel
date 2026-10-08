<?php

namespace App\Enums;

enum Role: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Mechanic = 'mechanic';
    case Customer = 'customer';

    public function managesWorkshop(): bool
    {
        return in_array($this, [self::Owner, self::Admin], true);
    }

    public function isStaff(): bool
    {
        return $this !== self::Customer;
    }

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Pemilik',
            self::Admin => 'Admin / Kasir',
            self::Mechanic => 'Mekanik',
            self::Customer => 'Pelanggan',
        };
    }
}
