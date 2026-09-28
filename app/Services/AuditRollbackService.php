<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\DB;

class AuditRollbackService
{
    /**
     * Rollback a specific audit log entry.
     * Restores the record to its previous state.
     */
    public function rollback(AuditLog $auditLog): bool
    {
        try {
            DB::beginTransaction();

            $table = $auditLog->table_name;
            $rowId = $auditLog->row_id;

            switch ($auditLog->action) {
                case 'create':
                    // Revert creation by deleting the record
                    DB::table($table)->where('id', $rowId)->delete();
                    break;

                case 'update':
                    // Restore to old values
                    if ($auditLog->old_values) {
                        DB::table($table)
                            ->where('id', $rowId)
                            ->update($auditLog->old_values);
                    }
                    break;

                case 'delete':
                    // Restore deleted record
                    if ($auditLog->old_values) {
                        DB::table($table)->insert($auditLog->old_values);
                    }
                    break;
            }

            // Mark this audit log as rolled back
            $auditLog->update([
                'new_values' => array_merge(
                    $auditLog->new_values ?? [],
                    ['_rolled_back_at' => now()->toIso8601String()]
                ),
            ]);

            DB::commit();

            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Audit rollback failed: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Check if an audit log can be rolled back safely.
     */
    public function canRollback(AuditLog $auditLog): bool
    {
        // Already rolled back
        if (isset($auditLog->new_values['_rolled_back_at'])) {
            return false;
        }

        // Check if the record still exists (for update/delete operations)
        if (in_array($auditLog->action, ['update', 'delete'])) {
            $exists = DB::table($auditLog->table_name)
                ->where('id', $auditLog->row_id)
                ->exists();

            // For updates, record must exist
            // For deletes, record must NOT exist (otherwise already restored)
            if ($auditLog->action === 'update' && ! $exists) {
                return false;
            }
            if ($auditLog->action === 'delete' && $exists) {
                return false;
            }
        }

        return true;
    }
}
