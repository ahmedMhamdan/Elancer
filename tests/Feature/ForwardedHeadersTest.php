<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ForwardedHeadersTest extends TestCase
{
    public function test_a_proxy_sets_the_scheme_but_not_the_client_address_host_or_port(): void
    {
        Route::get('/_forwarded', fn (Request $request) => ['secure' => $request->isSecure(), 'ip' => $request->ip(), 'host' => $request->getHost(), 'link' => url('/jobs')]);

        // Test requests arrive at the configured application address over plain HTTP.
        $address = preg_replace('#^https?://#', '', rtrim((string) config('app.url'), '/'));
        $host = explode(':', $address)[0];

        $this->getJson('/_forwarded')->assertExactJson(['secure' => false, 'ip' => '127.0.0.1', 'host' => $host, 'link' => 'http://'.$address.'/jobs']);
        $this->getJson('/_forwarded', ['X-Forwarded-Proto' => 'https', 'X-Forwarded-For' => '203.0.113.9', 'X-Forwarded-Host' => 'elsewhere.example', 'X-Forwarded-Port' => '8443'])
            ->assertExactJson(['secure' => true, 'ip' => '127.0.0.1', 'host' => $host, 'link' => 'https://'.$address.'/jobs']);
    }
}
