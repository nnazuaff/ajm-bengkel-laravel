<?php

namespace App\Actions;

use App\Models\AuditLog;
use App\Models\CheckInCode;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ManageCheckInCode
{
    public function generate(User $actor): CheckInCode
    {
        return $this->update($actor, true);
    }

    public function disable(User $actor): CheckInCode
    {
        return $this->update($actor, false);
    }

    private function update(User $actor, bool $active): CheckInCode
    {
        Gate::forUser($actor)->authorize('work-services');

        return DB::transaction(function () use ($actor, $active) {
            $actor = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('work-services');
            $code = CheckInCode::whereKey(1)->lockForUpdate()->firstOrFail();
            $next = null;
            if ($active) {
                do {
                    $next = (string) random_int(100000, 999999);
                } while ($next === $code->code);
            }
            $code->update(['code' => $next, 'active' => $active, 'created_by' => $actor->id, 'expires_at' => $active ? now()->addDay() : null]);
            $audit = AuditLog::create(['actor_id' => $actor->id, 'action' => $active ? 'check_in.code_rotated' : 'check_in.code_disabled', 'entity_type' => CheckInCode::class, 'entity_id' => 1, 'context' => ['active' => $active]]);

            if (! $audit->exists) {
                throw new \RuntimeException('Audit gagal disimpan.');
            }

            return $code;
        }, attempts: 5);
    }
}
