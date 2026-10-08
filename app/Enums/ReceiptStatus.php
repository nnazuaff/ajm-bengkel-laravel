<?php

namespace App\Enums;

enum ReceiptStatus: string
{
    case Draft = 'draft';
    case Final = 'final';
    case Paid = 'paid';
    case Voided = 'voided';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draf',self::Final => 'Final',self::Paid => 'Lunas',self::Voided => 'Dibatalkan',
        };
    }
}
