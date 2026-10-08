<?php

namespace App\Actions;

use Illuminate\Support\Facades\DB;

class NextServiceNumber
{
    public function generate(string $prefix = 'SRV'): string
    {
        if (! in_array($prefix, ['SRV', 'BKG', 'BON'], true)) {
            throw new \InvalidArgumentException('Unsupported document prefix.');
        }

        return DB::transaction(function () use ($prefix): string {
            $date = now()->toDateString();
            $key = ['prefix' => $prefix, 'date' => $date];

            // Upsert locks an existing row exclusively, avoiding shared-lock upgrades under concurrent intake.
            DB::table('document_sequences')->upsert([...$key, 'value' => 0], ['prefix', 'date'], ['prefix']);
            $sequence = DB::table('document_sequences')->where($key)->lockForUpdate()->first();
            $value = (int) $sequence->value + 1;
            DB::table('document_sequences')->where($key)->update(['value' => $value]);

            return $prefix.'-'.str_replace('-', '', $date).'-'.str_pad((string) $value, 3, '0', STR_PAD_LEFT);
        }, attempts: 5);
    }
}
