<?php

namespace App\Http\Middleware;

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

        return sha1(($route?->getName() ?? $request->method().' '.$route?->uri()).'|'.parent::resolveRequestSignature($request));
    }
}
