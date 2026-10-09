<?php

use App\Support\WorkshopInput;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained()->restrictOnDelete();
            $table->index(['vehicle_id', 'booking_date', 'arrival_time', 'status'], 'bookings_vehicle_slot_status_index');
        });

        // Include archives in ambiguity checks; never guess ownership or claim accounts.
        $customers = DB::table('customers')->get()->groupBy(fn ($row) => WorkshopInput::phone($row->phone));
        $vehicles = DB::table('vehicles')->get()->groupBy(fn ($row) => WorkshopInput::plate($row->license_plate));
        DB::table('bookings')->orderBy('id')->chunkById(200, function ($bookings) use ($customers, $vehicles) {
            foreach ($bookings as $booking) {
                $phoneMatches = $customers->get(WorkshopInput::phone($booking->phone));
                $plateMatches = $vehicles->get(WorkshopInput::plate($booking->license_plate));
                if ($phoneMatches?->count() !== 1 || $plateMatches?->count() !== 1) {
                    continue;
                }
                $customer = $phoneMatches->first();
                $vehicle = $plateMatches->first();
                if ($customer->deleted_at !== null || $vehicle->deleted_at !== null || $vehicle->customer_id !== $customer->id) {
                    continue;
                }
                DB::table('bookings')->where('id', $booking->id)->update(['customer_id' => $customer->id, 'vehicle_id' => $vehicle->id]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['vehicle_id']);
            $table->dropForeign(['customer_id']);
            $table->dropIndex('bookings_vehicle_slot_status_index');
            $table->dropColumn(['vehicle_id', 'customer_id']);
        });
    }
};
