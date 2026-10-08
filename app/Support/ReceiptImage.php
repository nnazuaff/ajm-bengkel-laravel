<?php

namespace App\Support;

use App\Enums\PaymentMethod;
use App\Models\Receipt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ReceiptImage
{
    public static function safeLogoPath(mixed $path): ?string
    {
        if (! is_string($path) || ! preg_match('/\Alogos\/[a-zA-Z0-9_-]+\.(?:png|jpe?g|webp)\z/', $path)) {
            return null;
        }
        $disk = Storage::disk('public');
        if (! $disk->exists($path) || $disk->size($path) > 2097152) {
            return null;
        }
        $info = @getimagesize($disk->path($path));
        if (! $info || $info[0] < 1 || $info[1] < 1 || $info[0] > 1024 || $info[1] > 1024
            || ! in_array($info['mime'], ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return null;
        }

        return $path;
    }

    private function money(string $value): void
    {
        if (! preg_match('/\A(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?\z/', $value)) {
            throw ValidationException::withMessages(['receipt' => 'Nilai uang gambar tidak valid.']);
        }
    }

    public function render(Receipt $receipt): string
    {
        $receipt->load(['items', 'payments']);
        if ($receipt->items->count() > 100) {
            throw ValidationException::withMessages(['receipt' => 'Maksimal 100 baris untuk gambar bon.']);
        }
        foreach ([$receipt->subtotal, $receipt->discount, $receipt->grand_total] as $money) {
            $this->money($money);
        }
        foreach ($receipt->items as $line) {
            if ($line->quantity < 1 || $line->quantity > 1000000) {
                throw ValidationException::withMessages(['receipt' => 'Jumlah baris gambar tidak valid.']);
            }
            $this->money($line->unit_price);
            $this->money($line->total);
        }
        $font = resource_path('fonts/receipt.ttf');
        if (! extension_loaded('gd') || ! is_file($font)) {
            throw new \RuntimeException('GD dan font lokal diperlukan untuk gambar bon.');
        }
        $rows = [];
        $add = function (string $text, int $size = 18, int $gap = 8) use (&$rows, $font): void {
            // Bound persisted data as well as browser input before computing layout.
            if (mb_strlen($text) > 5000) {
                throw ValidationException::withMessages(['receipt' => 'Teks bon terlalu panjang.']);
            }
            $line = '';
            foreach (preg_split('//u', preg_replace('/[\x00-\x1f\x7f]/u', ' ', $text) ?? '', -1, PREG_SPLIT_NO_EMPTY) ?: [] as $char) {
                $box = imagettfbbox($size, 0, $font, $line.$char);
                if ($box === false) {
                    throw new \RuntimeException('Font bon tidak dapat dibaca.');
                }
                if ($line !== '' && $box[2] - $box[0] > 704) {
                    $rows[] = [rtrim($line), $size, 0];
                    $line = ltrim($char);
                } else {
                    $line .= $char;
                }
            }
            $rows[] = [$line, $size, $gap];
        };
        $shop = $receipt->workshop_snapshot ?? [];
        $add($shop['name'] ?? 'AJM Bengkel', 28, 12);
        $add($shop['address'] ?? '');
        $add($shop['phone'] ?? '', 18, 20);
        $add($receipt->receipt_number, 22);
        $add('Tanggal: '.$receipt->transaction_date->format('d/m/Y H:i'));
        $add('Pelanggan: '.($receipt->customer_snapshot['name'] ?? 'Umum'));
        if (! empty($receipt->customer_snapshot['phone'])) {
            $add('Telepon: '.$receipt->customer_snapshot['phone']);
        }
        if ($receipt->vehicle_snapshot) {
            $add('Motor: '.implode(' / ', array_filter($receipt->vehicle_snapshot)), 18, 16);
        }
        $add('Status: '.$receipt->status->label().' · '.$receipt->payment_status->label(), 18, 20);
        foreach ($receipt->items as $index => $line) {
            $add(($index + 1).'. '.$line->description, 20, 4);
            $add($line->quantity.' x Rp '.$line->unit_price.' = Rp '.$line->total, 18, 14);
        }
        $add('Subtotal: Rp '.$receipt->subtotal, 18, 10);
        $add('Diskon: Rp '.$receipt->discount, 18, 10);
        $add('TOTAL: Rp '.$receipt->grand_total, 25, 18);
        $paid = '0.00';
        // Fixed-size method summaries keep later installments/reversals from invalidating PNG output.
        foreach (PaymentMethod::cases() as $method) {
            $active = $reversed = '0.00';
            foreach ($receipt->payments as $payment) {
                if ($payment->method === $method) {
                    $this->money($payment->amount);
                    if ($payment->reversed_at) {
                        $reversed = bcadd($reversed, $payment->amount, 2);
                    } else {
                        $active = bcadd($active, $payment->amount, 2);
                    }
                }
            }
            $paid = bcadd($paid, $active, 2);
            $add($method->label().': Rp '.$active.' · Dibalik Rp '.$reversed, 17);
        }
        $add('Rincian tanggal dan referensi pembayaran tersedia pada bon web.', 16);
        $add('Dibayar: Rp '.$paid);
        $add('Sisa: Rp '.($receipt->status->value === 'voided' ? '0.00' : bcsub($receipt->grand_total, $paid, 2)), 20, 20);
        if ($receipt->notes) {
            $add('Catatan: '.$receipt->notes, 16, 16);
        }
        if ($receipt->void_reason) {
            $add('Dibatalkan: '.$receipt->void_reason, 16, 16);
        }
        $add($shop['receipt_footer'] ?? 'Terima kasih.', 18, 16);
        $logoPath = self::safeLogoPath($shop['logo_path'] ?? null);
        $height = 96 + ($logoPath ? 144 : 0);
        foreach ($rows as [, $size, $gap]) {
            $height += (int) ceil($size * 1.6) + $gap;
        }
        if ($height < 1 || $height > 16000) {
            throw ValidationException::withMessages(['receipt' => 'Gambar terlalu tinggi. Persingkat catatan atau baris pada draf sebelum finalisasi.']);
        }
        $image = imagecreatetruecolor(800, $height);
        if (! $image) {
            throw new \RuntimeException('Gambar bon tidak dapat dialokasikan.');
        }
        $bufferLevel = ob_get_level();
        try {
            $white = imagecolorallocate($image, 255, 255, 255);
            $ink = imagecolorallocate($image, 28, 35, 43);
            if ($white === false || $ink === false) {
                throw new \RuntimeException('Warna gambar gagal dialokasikan.');
            }
            imagefill($image, 0, 0, $white);
            $y = 48;
            if ($logoPath) {
                $logo = @imagecreatefromstring(Storage::disk('public')->get($logoPath));
                if ($logo !== false) {
                    try {
                        $scale = min(120 / imagesx($logo), 120 / imagesy($logo));
                        $width = max(1, (int) round(imagesx($logo) * $scale));
                        $logoHeight = max(1, (int) round(imagesy($logo) * $scale));
                        imagecopyresampled($image, $logo, 48 + intdiv(120 - $width, 2), 48 + intdiv(120 - $logoHeight, 2), 0, 0, $width, $logoHeight, imagesx($logo), imagesy($logo));
                    } finally {
                        imagedestroy($logo);
                    }
                }
                $y += 144;
            }
            foreach ($rows as [$text, $size, $gap]) {
                $y += (int) ceil($size * 1.6);
                imagettftext($image, $size, 0, 48, $y, $ink, $font, $text);
                $y += $gap;
            }
            ob_start();
            if (! imagepng($image, null, 6)) {
                throw new \RuntimeException('PNG gagal dibuat.');
            }

            return (string) ob_get_clean();
        } finally {
            while (ob_get_level() > $bufferLevel) {
                ob_end_clean();
            }
            imagedestroy($image);
        }
    }
}
