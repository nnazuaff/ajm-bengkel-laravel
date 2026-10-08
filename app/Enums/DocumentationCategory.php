<?php

namespace App\Enums;

enum DocumentationCategory: string
{
    case Before = 'before';
    case Process = 'process';
    case After = 'after';
    case Evidence = 'evidence';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Before => 'Sebelum', self::Process => 'Proses', self::After => 'Sesudah',
            self::Evidence => 'Bukti', self::Other => 'Lainnya',
        };
    }
}
