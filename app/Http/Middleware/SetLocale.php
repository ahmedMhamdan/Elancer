<?php

namespace App\Http\Middleware;

use App\Actions\Auth\DemoAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $own = $request->session()->get('locale', $request->cookie('locale', 'en'));
        // A demo account is shared, so its saved language belongs to nobody: the visitor's own choice is used.
        $locale = DemoAccess::active($request) ? $own : ($request->user()->locale ?? $own);
        app()->setLocale(in_array($locale, ['en', 'ar'], true) ? $locale : 'en');

        return $next($request);
    }
}
