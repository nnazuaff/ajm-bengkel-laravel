<?php

namespace App\Http\Controllers;

use App\Models\WorkshopSetting;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;

class PublicHomeController extends Controller
{
    public function __invoke(): View
    {
        $workshop = WorkshopSetting::current();
        $logoUrl = null;
        $path = $workshop->logo_path;
        if (is_string($path) && preg_match('/\A[a-zA-Z0-9_\/-]+\.(?:png|jpg|jpeg|webp)\z/', $path)) {
            $disk = Storage::disk('public');
            if ($disk->exists($path) && in_array($disk->mimeType($path), ['image/png', 'image/jpeg', 'image/webp'], true)) {
                $logoUrl = $disk->url($path);
            }
        }

        return view('public.home', compact('workshop', 'logoUrl'));
    }
}
