<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class EnsureCategoryAdministrator
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user && $user->is_admin && $user->canParticipateInMarketplace(), 403);

        $configured = $user->two_factor_secret !== null && $user->two_factor_confirmed_at !== null;
        // Fortify's successful challenge records both user and secret fingerprint.
        // Rotating/disabling 2FA invalidates the proof, including in older sessions.
        $proof = $user->id.':'.hash('sha256', (string) $user->two_factor_secret);
        if (! $configured || $request->session()->get('admin.two_factor_proof') !== $proof) {
            if ($request->isMethod('GET') && ! $request->expectsJson()) {
                return Inertia::render('admin/categories/security', ['configured' => $configured])->toResponse($request);
            }
            abort(403, 'Complete two-factor authentication before managing categories.');
        }

        return $next($request);
    }
}
