<?php

namespace App\Http\Middleware;

use App\Actions\Auth\DemoAccess;
use App\Actions\Demo\DemoWorld;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Plans 025 and 027: every visitor who clicks a demo button shares the one account. Such a session
 * looks at everything; it changes things only among demo accounts, and only while a saved starting
 * point lets a reset take the changes back. What may pass is listed; anything else that is not a plain
 * page load is refused, so a route added later stays closed until someone opens it here.
 */
class RestrictDemoSession
{
    /** Ends the session, belongs to the visitor alone, or only moves a read mark. */
    private const ALLOWED = ['logout', 'demo.leave', 'demo.switch', 'locale.update', 'notifications.open', 'notifications.read-all',
        // Plan 033: turning down an app's request to connect stores nothing; approving stays closed.
        'passport.authorizations.deny'];

    /**
     * Plan 027: the hiring journey between demo accounts. Left out on purpose: the shared profile, its photo,
     * publication and portfolio, every file, reports, blocking, identity documents, settings and credentials.
     */
    private const ACTIONS = [
        'projects.store', 'projects.update', 'projects.publish', 'projects.extend', 'projects.clarify', 'projects.destroy', 'projects.restore', 'contracts.repost',
        'proposals.save', 'proposals.withdraw', 'proposals.review', 'invitations.store', 'invitations.update',
        'messages.start', 'messages.send', 'messages.state', 'messages.edit', 'offers.store', 'offers.update',
        'contracts.deliver', 'contracts.revise', 'contracts.complete', 'contracts.review',
        'contracts.cancellation.store', 'contracts.cancellation.update', 'contracts.cancellation.refund', 'contracts.amendments.store', 'contracts.amendments.update',
        'payments.store', 'payments.decide', 'payments.check', 'payments.cancel',
        'saved.projects.store', 'saved.projects.destroy', 'saved.freelancers.store', 'saved.freelancers.destroy',
        'saved.searches.store', 'saved.searches.update', 'saved.searches.destroy',
    ];

    /** Page loads that stay closed: account settings, credentials and provider sign-in. */
    private const CLOSED = ['settings', 'settings/*', 'user/*', 'auth/*', 'passkeys/*'];

    /** A payment's return address settles the payment, so it is a page load only a session that may act can open. */
    private const SETTLES = 'payments/*/return';

    public function handle(Request $request, Closure $next): Response
    {
        if (! DemoAccess::active($request)) {
            return $next($request);
        }
        $acting = DemoWorld::acting($request);
        // The live-update handshake is answered with both methods and stores nothing.
        if ($request->is('broadcasting/auth') || $request->routeIs(self::ALLOWED)
            || ($request->isMethodSafe() && ! $request->is(self::CLOSED) && ! $request->is(self::SETTLES))) {
            return $next($request);
        }
        // No file enters or leaves storage through an account anyone can open.
        if ($acting && $request->allFiles() === [] && ($request->isMethodSafe() ? $request->is(self::SETTLES) : $request->routeIs(self::ACTIONS))) {
            DemoWorld::touched();

            return $next($request);
        }
        $message = $acting
            ? __('This part is switched off in the shared demo. Create your own free account to use it.')
            : __('This is a shared demo account, so changes are switched off. Create your own free account to try it yourself.');
        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['message' => $message], 403);
        }
        Inertia::flash('toast', ['type' => 'info', 'message' => $message]);

        return $request->isMethodSafe() ? redirect()->route('dashboard') : back(303, [], route('dashboard'));
    }
}
