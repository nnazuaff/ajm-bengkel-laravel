<?php

use App\Actions\NextServiceNumber;
use Illuminate\Support\Facades\DB;

it('generates a daily sequence without counting service orders', function () {
    $this->travelTo(now()->setDate(2026, 10, 7));

    expect(app(NextServiceNumber::class)->generate())->toBe('SRV-20261007-001')
        ->and(app(NextServiceNumber::class)->generate())->toBe('SRV-20261007-002');

    $this->travel(1)->days();
    expect(app(NextServiceNumber::class)->generate())->toBe('SRV-20261008-001');
});

it('uses separate safe counters for booking and service documents', function () {
    $this->travelTo(now()->setDate(2026, 10, 7));
    expect(app(NextServiceNumber::class)->generate('BKG'))->toBe('BKG-20261007-001')
        ->and(app(NextServiceNumber::class)->generate())->toBe('SRV-20261007-001')
        ->and(app(NextServiceNumber::class)->generate('BKG'))->toBe('BKG-20261007-002')
        ->and(app(NextServiceNumber::class)->generate('BON'))->toBe('BON-20261007-001');
});

it('refuses unsupported numbering prefixes', function () {
    expect(fn () => app(NextServiceNumber::class)->generate('UNTRUSTED'))
        ->toThrow(InvalidArgumentException::class);
});

it('rolls a number allocation back with its transaction', function () {
    $this->travelTo(now()->setDate(2026, 10, 7));

    try {
        DB::transaction(function () {
            app(NextServiceNumber::class)->generate();
            throw new RuntimeException('Simulated intake failure');
        });
    } catch (RuntimeException) {
    }

    expect(app(NextServiceNumber::class)->generate())->toBe('SRV-20261007-001');
});
