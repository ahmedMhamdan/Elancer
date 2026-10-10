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

    public function test_the_visitor_address_comes_only_from_the_hosts_own_header(): void
    {
        config(['app.client_address_header' => 'CF-Connecting-IP']);
        Route::get('/_forwarded', fn (Request $request) => ['secure' => $request->isSecure(), 'ip' => $request->ip(), 'host' => $request->getHost()]);
        $host = explode(':', preg_replace('#^https?://#', '', rtrim((string) config('app.url'), '/')))[0];
        $seen = fn (array $headers) => $this->getJson('/_forwarded', ['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'elsewhere.example', ...$headers])->assertOk()->json();

        // The edge's address wins over anything the visitor wrote into the forwarded chain, for both address families.
        $this->assertSame(['secure' => true, 'ip' => '198.51.100.7', 'host' => $host], $seen(['CF-Connecting-IP' => '198.51.100.7', 'X-Forwarded-For' => '203.0.113.9, 10.0.0.1']));
        $this->assertSame('2001:db8::7', $seen(['CF-Connecting-IP' => '2001:db8::7'])['ip']);
        // Without the edge's header, or with one that is not a single address, the chain is still not believed.
        $this->assertSame('127.0.0.1', $seen(['X-Forwarded-For' => '203.0.113.9'])['ip']);
        $this->assertSame('127.0.0.1', $seen(['CF-Connecting-IP' => '198.51.100.7, 203.0.113.9', 'X-Forwarded-For' => '203.0.113.9'])['ip']);
        $this->assertSame('127.0.0.1', $seen(['CF-Connecting-IP' => 'unknown', 'X-Forwarded-For' => '203.0.113.9'])['ip']);
    }
}
