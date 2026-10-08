<?php

namespace App\Enums;

enum ServiceStatus: string
{
    case Waiting = 'waiting';
    case Inspection = 'inspection';
    case Approved = 'approved';
    case InProgress = 'in_progress';
    case WaitingPart = 'waiting_part';
    case Completed = 'completed';
    case ReadyForPickup = 'ready_for_pickup';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    /** @return list<self> */
    public function transitions(): array
    {
        return match ($this) {
            self::Waiting => [self::Inspection, self::Cancelled],
            self::Inspection => [self::Approved, self::Cancelled],
            self::Approved => [self::InProgress, self::Cancelled],
            self::InProgress => [self::WaitingPart, self::Completed, self::Cancelled],
            self::WaitingPart => [self::InProgress, self::Cancelled],
            self::Completed => [self::ReadyForPickup],
            self::ReadyForPickup => [self::Delivered],
            self::Delivered, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $status): bool
    {
        return in_array($status, $this->transitions(), true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Waiting => 'Menunggu',
            self::Inspection => 'Pemeriksaan',
            self::Approved => 'Disetujui',
            self::InProgress => 'Dikerjakan',
            self::WaitingPart => 'Menunggu part',
            self::Completed => 'Selesai',
            self::ReadyForPickup => 'Siap diambil',
            self::Delivered => 'Diserahkan',
            self::Cancelled => 'Dibatalkan',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Waiting, self::WaitingPart => 'amber',
            self::Inspection, self::Approved, self::InProgress => 'blue',
            self::Completed, self::ReadyForPickup, self::Delivered => 'green',
            self::Cancelled => 'red',
        };
    }
}
