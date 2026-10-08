<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'phone', 'address', 'receipt_footer', 'logo_path'])]
class WorkshopSetting extends Model
{
    public static function current(): self
    {
        return static::firstOrCreate(['id' => 1], [
            'name' => 'AJM Bengkel', 'phone' => '', 'address' => '', 'receipt_footer' => '',
        ]);
    }
}
