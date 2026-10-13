<?php

namespace App\Http\Middleware;

use App\Actions\Auth\DemoAccess;
use Illuminate\Routing\Middleware\ThrottleRequests;

class ThrottleRequestsPerRoute extends ThrottleRequests
{
    /**
     * The framework keys a plain throttle:N,M rule by the member alone (or by the address for a guest),
     * so every such route shared one counter and a burst on one action refused unrelated ones.
     * Counting per route gives each rule the limit written beside it. Named limiters build their own keys.
     */
    protected function resolveRequestSignature($request)
    {
        $route = $request->route();
        // Plan 027: a demo account is shared, so its visitors are counted by address, as guests are.
        $who = DemoAccess::active($request) ? 'demo|'.$request->ip() : parent::resolveRequestSignature($request);

        return sha1(($route?->getName() ?? $request->method().' '.$route?->uri()).'|'.$who);
    }
}
