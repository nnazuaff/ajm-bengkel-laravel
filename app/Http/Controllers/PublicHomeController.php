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
        // Same location pin as the previous AJM public site.
        $mapsEmbedUrl = 'https://www.google.com/maps?q=-6.9983857%2C107.542855&z=17&output=embed';
        $mapsUrl = filled($workshop->address)
            ? 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($workshop->address)
            : 'https://www.google.com/maps?q=-6.9983857%2C107.542855';

        return view('public.home', compact('workshop', 'whatsappUrl', 'mapsUrl', 'mapsEmbedUrl'));
    }
}
