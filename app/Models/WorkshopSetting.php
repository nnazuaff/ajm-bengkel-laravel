<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

#[Fillable(['name', 'phone', 'address', 'receipt_footer', 'logo_path', 'horizontal_logo_path', 'favicon_path'])]
class WorkshopSetting extends Model
{
    public function horizontalLogoUrl(): ?string
    {
        $path = $this->horizontal_logo_path;
        if (! is_string($path) || ! preg_match('/\Alogos\/[a-zA-Z0-9_-]+\.(?:png|jpe?g|webp)\z/', $path)) {
            return null;
        }
        $disk = Storage::disk('public');
        if (! $disk->exists($path) || $disk->size($path) > 2097152) {
            return null;
        }
        $info = @getimagesize($disk->path($path));
        if (! $info || $info[0] !== 1600 || $info[1] !== 560
            || ! in_array($info['mime'], ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return null;
        }

        return $disk->url($path);
    }

    public function faviconUrl(): ?string
    {
        $path = $this->favicon_path;
        if (! is_string($path) || ! preg_match('/\Alogos\/[a-zA-Z0-9_-]+\.png\z/', $path)) {
            return null;
        }
        $disk = Storage::disk('public');
        if (! $disk->exists($path) || $disk->size($path) > 1048576) {
            return null;
        }
        $info = @getimagesize($disk->path($path));
        if (! $info || $info[0] < 32 || $info[0] > 512 || $info[0] !== $info[1] || $info['mime'] !== 'image/png') {
            return null;
        }

        return $disk->url($path);
    }

    public static function current(): self
    {
        return static::firstOrCreate(['id' => 1], [
            'name' => 'AJM Bengkel', 'phone' => '', 'address' => '', 'receipt_footer' => '',
        ]);
    }
}
