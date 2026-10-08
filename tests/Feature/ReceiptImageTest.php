<?php

use App\Models\Payment;
use App\Models\Receipt;
use App\Support\ReceiptImage;
use Illuminate\Validation\ValidationException;

it('renders real nonblank PNG with dynamic wrapped rows from immutable snapshots', function () {
    $receipt = Receipt::factory()->create(['status' => 'final', 'workshop_snapshot' => ['name' => 'Bengkel Asli', 'phone' => '081234', 'address' => 'Jl. Melati', 'receipt_footer' => 'Terima kasih'], 'customer_snapshot' => ['name' => 'Pelanggan Asli'], 'grand_total' => '500.00']);
    $receipt->items()->createMany([
        ['description' => str_repeat('Penggantian komponen motor ', 8), 'type' => 'custom', 'quantity' => 2, 'unit_price' => '100.00', 'total' => '200.00'],
        ['description' => 'Oli mesin', 'type' => 'product', 'quantity' => 3, 'unit_price' => '100.00', 'total' => '300.00'],
    ]);
    $bytes = app(ReceiptImage::class)->render($receipt);
    expect(substr($bytes, 0, 8))->toBe("\x89PNG\r\n\x1a\n");
    $info = getimagesizefromstring($bytes);
    expect($info[2])->toBe(IMAGETYPE_PNG)->and($info[0])->toBe(800)->and($info[1])->toBeGreaterThan(600)->toBeLessThanOrEqual(16000);
    $image = imagecreatefromstring($bytes);
    $dark = 0;
    for ($y = 0; $y < imagesy($image); $y += 5) {
        for ($x = 0; $x < imagesx($image); $x += 5) {
            if ((imagecolorat($image, $x, $y) & 0xFF) < 180) {
                $dark++;
            }
        }
    }
    expect($dark)->toBeGreaterThan(100);
    imagedestroy($image);
    $receipt->items()->create(['description' => 'Baris tambahan', 'type' => 'custom', 'quantity' => 1, 'unit_price' => '1', 'total' => '1']);
    expect(getimagesizefromstring(app(ReceiptImage::class)->render($receipt))[1])->toBeGreaterThan($info[1]);
});

it('keeps receipt PNG bounded when many real payment events accumulate', function () {
    $receipt = Receipt::factory()->create(['status' => 'paid', 'grand_total' => '500.00']);
    Payment::factory()->count(500)->create(['receipt_id' => $receipt->id, 'amount' => '1.00', 'reference' => str_repeat('Reference ', 25)]);
    $bytes = app(ReceiptImage::class)->render($receipt);
    expect(getimagesizefromstring($bytes)[1])->toBeLessThan(3000);
});

it('rejects unbounded image data', function () {
    $receipt = Receipt::factory()->create(['status' => 'final']);
    for ($i = 0; $i < 101; $i++) {
        $receipt->items()->create(['description' => 'X', 'type' => 'custom', 'quantity' => 1, 'unit_price' => '1', 'total' => '1']);
    }
    expect(fn () => app(ReceiptImage::class)->render($receipt))->toThrow(ValidationException::class);
});

it('rejects negative and oversized persisted image values', function (array $line) {
    $receipt = Receipt::factory()->create(['status' => 'final']);
    $receipt->items()->create(['description' => 'Biaya', 'type' => 'custom', 'unit_price' => '1', 'total' => '1', ...$line]);
    expect(fn () => app(ReceiptImage::class)->render($receipt))->toThrow(ValidationException::class);
})->with([[['quantity' => -1]], [['quantity' => 1000001]], [['quantity' => 1, 'unit_price' => '-1']], [['quantity' => 1, 'total' => '-1']]]);
