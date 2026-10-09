<?php

use App\Support\WorkshopInput;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $updates = [];
        foreach (['customers' => ['phone', 'phone'], 'vehicles' => ['license_plate', 'plate']] as $table => [$column,$method]) {
            $seen = [];
            foreach (DB::table($table)->orderBy('id')->get(['id', $column]) as $row) {
                $normalized = WorkshopInput::$method($row->{$column});
                $valid = $method === 'phone' ? preg_match('/^62[1-9][0-9]{7,12}$/D', $normalized) : preg_match('/^[A-Z0-9]{1,20}$/D', $normalized);
                if (! $valid || isset($seen[$normalized])) {
                    throw new RuntimeException('Normalisasi tertahan: data tidak valid atau konflik pada '.$table.'. Tinjau ID '.$row->id.' sebelum melanjutkan.');
                }
                $seen[$normalized] = true;
                if ($normalized !== $row->{$column}) {
                    $updates[] = [$table, $row->id, $column, $normalized];
                }
            }
        }
        DB::transaction(function () use ($updates): void {
            foreach ($updates as [$table,$id,$column,$value]) {
                DB::table($table)->where('id', $id)->update([$column => $value]);
            }
        });
    }

    public function down(): void
    { /* Canonical identifiers intentionally retained on rollback. */
    }
};
