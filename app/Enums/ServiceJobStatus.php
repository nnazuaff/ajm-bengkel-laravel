<?php

namespace App\Enums;

enum ServiceJobStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /** @return list<self> */
    public function transitions(): array
    {
        return match ($this) {
            self::Pending => [self::InProgress, self::Completed, self::Cancelled],
            self::InProgress => [self::Completed, self::Cancelled],
            self::Completed, self::Cancelled => [],
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu', self::InProgress => 'Dikerjakan',
            self::Completed => 'Selesai', self::Cancelled => 'Dibatalkan',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'amber', self::InProgress => 'blue',
            self::Completed => 'green', self::Cancelled => 'red',
        };
    }
}
