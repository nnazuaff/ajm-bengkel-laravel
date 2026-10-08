<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        // MySQL DDL survives failed migrations/table resets; retries preserve existing objects.
        DB::unprepared(<<<'WORKSHOP_SQL'
CREATE FUNCTION IF NOT EXISTS workshop_receipt_balance(receiptId BIGINT UNSIGNED)
RETURNS DECIMAL(14,2)
READS SQL DATA
SQL SECURITY INVOKER
BEGIN
    DECLARE amountDue DECIMAL(14,2) DEFAULT 0.00;
    SELECT CASE WHEN r.status = 'voided' THEN 0.00 ELSE GREATEST(0.00, r.grand_total - COALESCE((
        SELECT SUM(p.amount) FROM payments p WHERE p.receipt_id = r.id AND p.reversed_at IS NULL
    ), 0.00)) END INTO amountDue FROM receipts r WHERE r.id = receiptId;
    RETURN amountDue;
END
WORKSHOP_SQL);
        DB::unprepared(<<<'WORKSHOP_SQL'
CREATE PROCEDURE IF NOT EXISTS workshop_daily_payments(IN fromDate DATE, IN toDate DATE)
SQL SECURITY INVOKER
BEGIN
    SELECT day,
        CAST(SUM(gross) AS DECIMAL(14,2)) AS gross,
        CAST(SUM(reversed) AS DECIMAL(14,2)) AS reversed,
        CAST(SUM(gross) - SUM(reversed) AS DECIMAL(14,2)) AS net
    FROM (
        SELECT DATE(paid_at) AS day, amount AS gross, 0.00 AS reversed
        FROM payments WHERE paid_at >= fromDate AND paid_at < DATE_ADD(toDate, INTERVAL 1 DAY)
        UNION ALL
        SELECT DATE(reversed_at) AS day, 0.00 AS gross, amount AS reversed
        FROM payments WHERE reversed_at >= fromDate AND reversed_at < DATE_ADD(toDate, INTERVAL 1 DAY)
    ) events
    GROUP BY day ORDER BY day;
END
WORKSHOP_SQL);
        DB::unprepared(<<<'WORKSHOP_SQL'
CREATE TRIGGER IF NOT EXISTS stock_movement_audit AFTER INSERT ON stock_movements
FOR EACH ROW
INSERT INTO audit_logs (actor_id, action, entity_type, entity_id, context, created_at)
VALUES (NEW.created_by, 'stock_movement.created', 'App\\Models\\StockMovement', NEW.id,
    JSON_OBJECT('inventory_item_id', NEW.inventory_item_id, 'quantity', NEW.quantity,
        'stock_before', NEW.stock_before, 'stock_after', NEW.stock_after, 'reason', NEW.reason), CURRENT_TIMESTAMP)
WORKSHOP_SQL);
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }
        DB::unprepared('DROP TRIGGER IF EXISTS stock_movement_audit');
        DB::unprepared('DROP PROCEDURE IF EXISTS workshop_daily_payments');
        DB::unprepared('DROP FUNCTION IF EXISTS workshop_receipt_balance');
    }
};
