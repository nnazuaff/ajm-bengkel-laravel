<?php

namespace App\Support;

final class WorkshopInput
{
    public static function phone(string $value): string
    {
        $value = str_replace([' ', '-', '(', ')', '+'], '', trim($value));

        return str_starts_with($value, '0') ? '62'.substr($value, 1) : $value;
    }

    public static function like(string $value): string
    {
        return '%'.strtr($value, ['!' => '!!', '%' => '!%', '_' => '!_']).'%';
    }

    public static function plate(string $value): string
    {
        return strtoupper(preg_replace('/\s+/u', '', $value) ?? $value);
    }
}
