<?php

namespace App\Enums;

enum ServiceSource: string
{
    case WalkIn = 'walk_in';
    case Booking = 'booking';

    public function label(): string
    {
        return $this === self::WalkIn ? 'Walk-in' : 'Booking';
    }
}
