<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;

class EnsureWorkshopEmailVerified extends EnsureEmailIsVerified
{
    public function handle($request, Closure $next, $redirectToRoute = null)
    {
        return config('fortify.require_email_verification') && ! ($request->user()?->canUseCustomerAccess() ?? false)
            ? parent::handle($request, $next, $redirectToRoute)
            : $next($request);
    }
}
