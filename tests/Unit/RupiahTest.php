<?php

use App\Support\Rupiah;

it('formats decimal strings as whole Indonesian rupiah without losing the stored precision', function () {
    expect(Rupiah::format('5000.00'))->toBe('Rp 5.000')
        ->and(Rupiah::format('50000.00'))->toBe('Rp 50.000')
        ->and(Rupiah::format('1250000.00'))->toBe('Rp 1.250.000')
        ->and(Rupiah::format('0.00'))->toBe('Rp 0')
        ->and(Rupiah::format('12500.25'))->toBe('Rp 12.500,25')
        ->and(Rupiah::format('12500.50'))->toBe('Rp 12.500,50')
        ->and(Rupiah::format('-12500.50'))->toBe('-Rp 12.500,50')
        ->and(Rupiah::format('0.25'))->toBe('Rp 0,25')
        ->and(Rupiah::format('0.50'))->toBe('Rp 0,50')
        ->and(Rupiah::format('1.20'))->toBe('Rp 1,20')
        ->and(Rupiah::format('1000000000000000000000.01'))->toBe('Rp 1.000.000.000.000.000.000.000,01')
        ->and(Rupiah::format('999999999999.99'))->toBe('Rp 999.999.999.999,99');
});

it('preserves receipt arithmetic when fractional amounts are displayed', function () {
    expect(Rupiah::format('0.25'))->toBe('Rp 0,25')
        ->and(Rupiah::format('0.50'))->toBe('Rp 0,50');
});

it('rejects malformed money rather than silently displaying zero', function () {
    expect(fn () => Rupiah::format('abc'))->toThrow(InvalidArgumentException::class);
});
