<?php

namespace App\Support;

use App\Models\Booking;
use App\Models\WorkshopSetting;

final class BookingWhatsapp
{
    public static function url(Booking $booking): ?string
    {
        $phone = WorkshopInput::phone(WorkshopSetting::current()->phone ?? '');
        if (! preg_match('/\A62[1-9][0-9]{7,12}\z/', $phone)) {
            return null;
        }
        $message = "Halo, saya sudah mengajukan booking melalui website, tolong dicek.\n\n"
            ."Nomor booking: {$booking->booking_number}\nNama: {$booking->name}\nHP: {$booking->phone}\n"
            ."Motor: {$booking->brand} {$booking->model}\nPelat: {$booking->license_plate}\n"
            .'Jadwal: '.$booking->booking_date->format('d/m/Y').' '.substr($booking->arrival_time, 0, 5)." WIB\n"
            ."Servis: {$booking->service_type}\nKeluhan: {$booking->complaint}\n\nMohon konfirmasi ketersediaan jadwal. Terima kasih.";

        return 'https://wa.me/'.$phone.'?text='.rawurlencode($message);
    }
}
