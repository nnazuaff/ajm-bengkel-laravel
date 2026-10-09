<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string|null $code
 * @property bool $active
 * @property CarbonImmutable|null $expires_at
 */
class CheckInCode extends Model
{
    protected $guarded = [];

    protected $hidden = ['code'];

    protected function casts(): array
    {
        return ['code' => 'encrypted', 'active' => 'boolean', 'expires_at' => 'immutable_datetime'];
    }
}
