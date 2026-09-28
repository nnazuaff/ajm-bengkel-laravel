<?php

namespace App\Observers;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditObserver
{
    private array $auditableTables = [
        'spareparts',
        'customers',
        'vehicles',
        'work_orders',
        'work_order_items',
        'transactions',
    ];

    public function created(Model $model): void
    {
        if (! $this->shouldAudit($model)) {
            return;
        }

        AuditLog::create([
            'user_id' => Auth::id() ?? 1,
            'table_name' => $model->getTable(),
            'row_id' => $model->id,
            'action' => 'create',
            'old_values' => null,
            'new_values' => $model->getAttributes(),
        ]);
    }

    public function updated(Model $model): void
    {
        if (! $this->shouldAudit($model)) {
            return;
        }

        AuditLog::create([
            'user_id' => Auth::id() ?? 1,
            'table_name' => $model->getTable(),
            'row_id' => $model->id,
            'action' => 'update',
            'old_values' => $model->getOriginal(),
            'new_values' => $model->getAttributes(),
        ]);
    }

    public function deleted(Model $model): void
    {
        if (! $this->shouldAudit($model)) {
            return;
        }

        AuditLog::create([
            'user_id' => Auth::id() ?? 1,
            'table_name' => $model->getTable(),
            'row_id' => $model->id,
            'action' => 'delete',
            'old_values' => $model->getAttributes(),
            'new_values' => null,
        ]);
    }

    private function shouldAudit(Model $model): bool
    {
        return in_array($model->getTable(), $this->auditableTables);
    }
}
