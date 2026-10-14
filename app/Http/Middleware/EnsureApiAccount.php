<?php

namespace App\Http\Middleware;

use App\Actions\Demo\DemoWorld;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Plan 028: a token answers only while its account could still sign in and read its own work
 * (Q64: active or suspended, with a verified address). A prepared demo account never holds one,
 * so a token that names one is refused whatever put it there.
 */
class EnsureApiAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        // Messages are English unless the caller asks for Arabic; the account's saved language is for its pages.
        app()->setLocale(str_starts_with(strtolower(trim((string) $request->header('Accept-Language'))), 'ar') ? 'ar' : 'en');
        $user = $request->user();
        abort_unless($user instanceof User && $user->keepsExistingAccess() && ! DemoWorld::holds($user->id), 403, __('This account cannot use the API.'));

        return $next($request);
    }
}
