<?php

namespace App\Http\Middleware;

use App\Actions\Auth\DemoAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EndDemoForAuthentication
{
    public function handle(Request $request, Closure $next): Response
    {
        // Browser Back can restore a guest form while the server still holds the demo session.
        if (DemoAccess::active($request) && $request->routeIs('login', 'login.store', 'register', 'register.store',
            'social.redirect', 'passkey.login-options', 'passkey.login')) {
            DemoAccess::leave($request);
        }

        return $next($request);
    }
}
