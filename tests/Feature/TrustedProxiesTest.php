<?php

use App\Support\CloudflareNetwork;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::get('/_test/client-ip', fn (Request $request): string => (string) $request->ip());
});

it('finds the visitor behind Cloudflare and Traefik, past whatever the client claimed', function () {
    $this->withServerVariables(['REMOTE_ADDR' => '172.18.0.2'])
        ->withHeader('X-Forwarded-For', '203.0.113.9, 89.134.158.250, 104.23.162.74')
        ->get('/_test/client-ip')
        ->assertSee('89.134.158.250');
});

it('does not believe a forwarded address from a source that is not a proxy', function () {
    $this->withServerVariables(['REMOTE_ADDR' => '89.134.158.250'])
        ->withHeader('X-Forwarded-For', '203.0.113.9')
        ->get('/_test/client-ip')
        ->assertSee('89.134.158.250');
});

it('gives Traefik the same Cloudflare ranges Laravel trusts', function () {
    $compose = (string) file_get_contents(base_path('docker-compose.prod.yml'));
    preg_match('/forwardedHeaders\.trustedIPs=(\S+)/', $compose, $match);

    expect(explode(',', $match[1] ?? ''))->toBe(CloudflareNetwork::EDGE_RANGES);
});
