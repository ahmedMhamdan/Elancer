<?php

namespace App\Http\Middleware;

use App\Actions\Auth\DemoAccess;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Plan 025: a session opened from the demo buttons looks at everything and changes nothing, because
 * every visitor who clicks shares the one account. What may pass is listed; anything else that is not
 * a plain page load is refused, so a route added later stays closed until someone opens it here.
 */
class RestrictDemoSession
{
    /** Ends the session, belongs to the visitor alone, or only moves a read mark. */
    private const ALLOWED = ['logout', 'demo.leave', 'locale.update', 'notifications.open', 'notifications.read-all'];

    /** Page loads that stay closed: account settings, credentials, provider sign-in and a payment's return. */
    private const CLOSED = ['settings', 'settings/*', 'user/*', 'auth/*', 'passkeys/*', 'payments/*/return'];

    public function handle(Request $request, Closure $next): Response
    {
        if (! DemoAccess::active($request)) {
            return $next($request);
        }
        // The live-update handshake is answered with both methods and stores nothing.
        if ($request->is('broadcasting/auth') || ($request->isMethodSafe() ? ! $request->is(self::CLOSED) : $request->routeIs(self::ALLOWED))) {
            return $next($request);
        }
        $message = __('This is a shared demo account, so changes are switched off. Create your own free account to try it yourself.');
        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['message' => $message], 403);
        }
        Inertia::flash('toast', ['type' => 'info', 'message' => $message]);

        return $request->isMethodSafe() ? redirect()->route('dashboard') : back(303, [], route('dashboard'));
    }
}
