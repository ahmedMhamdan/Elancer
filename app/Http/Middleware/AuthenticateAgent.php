<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Http\Request;
use Laravel\Passport\Passport;
use Symfony\Component\HttpFoundation\Response;

/**
 * Plan 033: /mcp takes either kind of bearer token. An API token from Settings (plan 028) has Sanctum's
 * "id|secret" form; a token from Connect is Passport's signed token, which never contains "|". Asking only
 * the guard that issued it keeps a stranger's token from being tried, and logged, twice.
 */
class AuthenticateAgent
{
    public function __construct(private Authenticate $authenticate) {}

    public function handle(Request $request, Closure $next): Response
    {
        $bearer = $request->bearerToken();
        // Without a token, or before the host has Passport's keys, Sanctum answers: a 401, never a failure.
        $guard = $bearer === null || str_contains($bearer, '|') || ! self::connectReady() ? 'sanctum' : 'api';

        return $this->authenticate->handle($request, $next, $guard);
    }

    /** Passport reads a connected app's token only with the public key, set or in storage/. */
    private static function connectReady(): bool
    {
        return filled(config('passport.public_key')) || is_file(Passport::keyPath('oauth-public.key'));
    }
}
