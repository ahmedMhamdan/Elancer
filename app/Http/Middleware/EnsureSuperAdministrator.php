<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperAdministrator extends EnsureCategoryAdministrator
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->is_super_admin, 403);

        // Reuse the active, verified and current-session 2FA gate.
        return parent::handle($request, $next);
    }
}
