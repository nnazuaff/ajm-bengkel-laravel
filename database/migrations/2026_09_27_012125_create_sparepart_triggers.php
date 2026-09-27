<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Trigger: Auto-deduct stock when work_order status changes to 'completed'
        DB::unprepared("
            CREATE TRIGGER after_work_order_completed
            AFTER UPDATE ON work_orders
            FOR EACH ROW
            BEGIN
                IF NEW.status = 'completed' AND OLD.status != 'completed' THEN
                    UPDATE spareparts sp
                    INNER JOIN work_order_items woi ON sp.id = woi.sparepart_id
                    SET sp.stock = sp.stock - woi.quantity
                    WHERE woi.work_order_id = NEW.id
                      AND woi.sparepart_id IS NOT NULL;
                END IF;
            END;
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared("DROP TRIGGER IF EXISTS after_work_order_completed");
    }
};
