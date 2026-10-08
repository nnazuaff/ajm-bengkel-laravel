<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case Transfer = 'transfer';
    case Qris = 'qris';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Tunai',self::Transfer => 'Transfer',self::Qris => 'QRIS',self::Other => 'Lainnya',
        };
    }
}
