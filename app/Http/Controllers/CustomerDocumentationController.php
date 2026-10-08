<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\ServiceDocumentation;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerDocumentationController extends Controller
{
    public function show(ServiceDocumentation $documentation): StreamedResponse
    {
        $actor = User::query()->find(Auth::id());
        abort_unless($actor && $actor->role === Role::Customer && $actor->canUseCustomerAccess(), 403);
        Gate::authorize('customer-portal');
        abort_if($documentation->trashed(), 404);
        $order = $documentation->serviceOrder;
        abort_unless($order && Customer::query()->whereKey($order->customer_id)->where('user_id', $actor->id)->exists(), 404);
        abort_unless($documentation->disk === 'local'
            && preg_match('/\Aservice-documentation\/'.$documentation->service_order_id.'\/[a-f0-9]{32}\.(?:jpg|png|webp)\z/', $documentation->path), 404);
        $disk = Storage::disk('local');
        abort_unless($disk->exists($documentation->path), 404);
        $mime = $disk->mimeType($documentation->path);
        abort_unless(in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true), 404);

        return $disk->response($documentation->path, 'documentation-'.$documentation->id.'.'.pathinfo($documentation->path, PATHINFO_EXTENSION), [
            'Content-Type' => $mime, 'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
        ], 'inline');
    }
}
