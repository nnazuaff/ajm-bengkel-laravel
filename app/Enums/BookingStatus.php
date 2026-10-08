<?php

namespace App\Enums;

enum BookingStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Rescheduled = 'rescheduled';
    case Arrived = 'arrived';
    case ConvertedToService = 'converted_to_service';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    /** @return list<self> */
    public function transitions(): array
    {
        return match ($this) {
            self::Pending => [self::Confirmed, self::Rescheduled, self::Rejected, self::Cancelled],
            self::Confirmed, self::Rescheduled => [self::Rescheduled, self::Arrived, self::Rejected, self::Cancelled],
            self::Arrived => [self::Cancelled],
            default => [],
        };
    }

    public function customerCanCancel(): bool
    {
        return in_array($this, [self::Pending, self::Confirmed, self::Rescheduled], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu konfirmasi', self::Confirmed => 'Dikonfirmasi',
            self::Rescheduled => 'Dijadwalkan ulang', self::Arrived => 'Sudah datang',
            self::ConvertedToService => 'Diterima servis', self::Rejected => 'Ditolak', self::Cancelled => 'Dibatalkan',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'amber', self::Confirmed, self::Rescheduled => 'blue',
            self::Arrived, self::ConvertedToService => 'emerald', self::Rejected => 'red', self::Cancelled => 'zinc',
        };
    }
}
