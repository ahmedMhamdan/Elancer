<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;

class TrustHostProxy extends TrustProxies
{
    /**
     * Behind the host's proxy every request arrives from the proxy's own address, so all guests shared one
     * counter for each per-address limit. The forwarded chain cannot settle who the visitor is, because a
     * visitor may write its first entries. Where the host's edge names the visitor in a header of its own
     * (app.client_address_header), that single address replaces the chain and becomes the request's address.
     * Without the setting, or when the header is missing or is not one address, the proxy's address stays.
     */
    public function handle(Request $request, Closure $next)
    {
        if ($header = config('app.client_address_header')) {
            $address = trim((string) $request->headers->get($header));
            if (filter_var($address, FILTER_VALIDATE_IP)) {
                $request->headers->set('X-Forwarded-For', $address);
            } else {
                $request->headers->remove('X-Forwarded-For');
            }
        }

        return parent::handle($request, $next);
    }

    protected function getTrustedHeaderNames()
    {
        return parent::getTrustedHeaderNames() | (config('app.client_address_header') ? Request::HEADER_X_FORWARDED_FOR : 0);
    }
}
