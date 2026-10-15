<?php

namespace App\Http\Controllers;

use App\Models\WorkshopSetting;
use App\Support\WorkshopInput;
use Illuminate\Contracts\View\View;

class PublicHomeController extends Controller
{
    public function __invoke(): View
    {
        $workshop = WorkshopSetting::current();
        $phone = WorkshopInput::phone($workshop->phone);
        $whatsappUrl = preg_match('/\A62[1-9][0-9]{7,12}\z/', $phone)
            ? 'https://wa.me/'.$phone.'?text='.rawurlencode('Halo AJM, saya mau tanya servis motor.')
            : null;
        // Pin from the workshop's Google Maps Street View link.
        $mapsUrl = 'https://www.google.com/maps?q=-6.998307%2C107.5428932';
        $mapsEmbedUrl = $mapsUrl.'&z=17&output=embed';

        return view('public.home', compact('workshop', 'whatsappUrl', 'mapsUrl', 'mapsEmbedUrl'));
    }
}
