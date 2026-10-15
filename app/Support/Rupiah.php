<?php

namespace App\Support;

use InvalidArgumentException;

final class Rupiah
{
    public static function format(string|int $amount): string
    {
        $amount = (string) $amount;
        if (! preg_match('/\A-?(?:0|[1-9][0-9]*)(?:\.[0-9]{1,2})?\z/D', $amount)) {
            throw new InvalidArgumentException('Nominal Rupiah tidak valid.');
        }

        $negative = str_starts_with($amount, '-');
        $absolute = $negative ? substr($amount, 1) : $amount;
        [$whole, $fraction] = array_pad(explode('.', $absolute, 2), 2, '');
        $fraction = rtrim($fraction, '0');
        if ($fraction !== '') {
            $fraction = str_pad($fraction, 2, '0');
        }

        return ($negative && ($whole !== '0' || $fraction !== '') ? '-' : '').'Rp '.preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $whole).($fraction !== '' ? ','.$fraction : '');
    }
}
