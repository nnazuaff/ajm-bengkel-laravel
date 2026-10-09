<?php

namespace App\Enums;

enum CheckInStatus: string
{
    case Waiting = 'waiting';
    case Processing = 'processing';
    case ConvertedToService = 'converted_to_service';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Waiting => 'Menunggu',self::Processing => 'Diproses',self::ConvertedToService => 'Sudah diterima servis',self::Cancelled => 'Dibatalkan'
        };
    }
}
