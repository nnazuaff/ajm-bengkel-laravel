<?php

namespace App\Http\Controllers;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class CheckInQrController extends Controller
{
    public function __invoke(): Response
    {
        Gate::authorize('work-services');
        $writer = new Writer(new ImageRenderer(new RendererStyle(320), new SvgImageBackEnd));

        return response($writer->writeString(route('check-in')), 200, [
            'Content-Type' => 'image/svg+xml', 'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
