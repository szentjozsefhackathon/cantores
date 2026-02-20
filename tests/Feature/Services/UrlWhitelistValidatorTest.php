<?php

use App\Models\WhitelistRule;
use App\Services\UrlWhitelistValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->validator = new UrlWhitelistValidator;
});

test('validates correct URL with matching whitelist rule', function () {
    WhitelistRule::factory()->create([
        'hostname' => 'example.com',
        'path_prefix' => '/music',
        'scheme' => 'https',
        'allow_any_port' => false,
        'is_active' => true,
    ]);

    $result = $this->validator->validate('https://example.com/music/song123');
    expect($result)->toBeTrue();
});

test('validates URL with exact path match', function () {
    WhitelistRule::factory()->create([
        'hostname' => 'example.com',
        'path_prefix' => '/music',
        'scheme' => 'https',
        'is_active' => true,
    ]);

    $result = $this->validator->validate('https://example.com/music');
    expect($result)->toBeTrue();
});

test('validates URL with query parameters and fragments', function () {
    WhitelistRule::factory()->create([
        'hostname' => 'example.com',
        'path_prefix' => '/music',
        'scheme' => 'https',
        'is_active' => true,
    ]);

    $result = $this->validator->validate('https://example.com/music/song?param=value#section');
    expect($result)->toBeTrue();
});

test('validates URL with default ports', function () {
    WhitelistRule::factory()->create([
        'hostname' => 'example.com',
        'path_prefix' => '/',
        'scheme' => 'https',
        'allow_any_port' => false,
        'is_active' => true,
    ]);

    // No port specified (default 443)
    $result = $this->validator->validate('https://example.com/');
    expect($result)->toBeTrue();

    // Explicit default port
    $result = $this->validator->validate('https://example.com:443/');
    expect($result)->toBeTrue();
});

test('validates URL with allow_any_port flag', function () {
    WhitelistRule::factory()->create([
        'hostname' => 'example.com',
        'path_prefix' => '/',
        'scheme' => 'https',
        'allow_any_port' => true,
        'is_active' => true,
    ]);

    $result = $this->validator->validate('https://example.com:8080/');
    expect($result)->toBeTrue();
});

test('rejects URL with non-default port when allow_any_port is false', function () {
    WhitelistRule::factory()->create([
        'hostname' => 'example.com',
        'path_prefix' => '/',
        'scheme' => 'https',
        'allow_any_port' => false,
        'is_active' => true,
    ]);

    $result = $this->validator->validate('https://example.com:8080/');
    expect($result)->toBeFalse();
});

test('rejects URL with wrong scheme', function () {
    WhitelistRule::factory()->create([
        'hostname' => 'example.com',
        'path_prefix' => '/',
        'scheme' => 'https',
        'is_active' => true,
    ]);

    $result = $this->validator->validate('http://example.com/');
    expect($result)->toBeFalse();
});

test('rejects URL with wrong hostname', function () {
    WhitelistRule::factory()->create([
        'hostname' => 'example.com',
        'path_prefix' => '/',
        'scheme' => 'https',
        'is_active' => true,
    ]);

    $result = $this->validator->validate('https://other.com/');
    expect($result)->toBeFalse();
});

test('rejects URL with wrong path prefix', function () {
    WhitelistRule::factory()->create([
        'hostname' => 'example.com',
        'path_prefix' => '/music',
        'scheme' => 'https',
        'is_active' => true,
    ]);

    $result = $this->validator->validate('https://example.com/other');
    expect($result)->toBeFalse();
});

test('rejects URL when rule is inactive', function () {
    WhitelistRule::factory()->create([
        'hostname' => 'example.com',
        'path_prefix' => '/',
        'scheme' => 'https',
        'is_active' => false,
    ]);

    $result = $this->validator->validate('https://example.com/');
    expect($result)->toBeFalse();
});

test('throws exception for malformed URL', function () {
    $this->expectException(InvalidArgumentException::class);
    $this->validator->validate('not-a-url');
});

test('throws exception for URL without scheme', function () {
    $this->expectException(InvalidArgumentException::class);
    $this->validator->validate('example.com/path');
});

test('throws exception for URL without hostname', function () {
    $this->expectException(InvalidArgumentException::class);
    $this->validator->validate('https:///path');
});

test('throws exception for URL with userinfo', function () {
    $this->expectException(InvalidArgumentException::class);
    $this->validator->validate('https://user:pass@example.com/');
});

test('throws exception for non-http/https scheme', function () {
    $this->expectException(InvalidArgumentException::class);
    $this->validator->validate('ftp://example.com/');
});

test('handles multiple rules with same hostname', function () {
    WhitelistRule::factory()->create([
        'hostname' => 'example.com',
        'path_prefix' => '/music',
        'scheme' => 'https',
        'is_active' => true,
    ]);

    WhitelistRule::factory()->create([
        'hostname' => 'example.com',
        'path_prefix' => '/docs',
        'scheme' => 'https',
        'is_active' => true,
    ]);

    $result = $this->validator->validate('https://example.com/music/song');
    expect($result)->toBeTrue();

    $result = $this->validator->validate('https://example.com/docs/api');
    expect($result)->toBeTrue();

    $result = $this->validator->validate('https://example.com/other');
    expect($result)->toBeFalse();
});

test('case insensitive hostname matching', function () {
    WhitelistRule::factory()->create([
        'hostname' => 'example.com',
        'path_prefix' => '/',
        'scheme' => 'https',
        'is_active' => true,
    ]);

    $result = $this->validator->validate('https://EXAMPLE.COM/');
    expect($result)->toBeTrue();

    $result = $this->validator->validate('https://Example.Com/');
    expect($result)->toBeTrue();
});

test('normalizes path without leading slash', function () {
    WhitelistRule::factory()->create([
        'hostname' => 'example.com',
        'path_prefix' => '/music',
        'scheme' => 'https',
        'is_active' => true,
    ]);

    // Path prefix in rule doesn't have leading slash (should be normalized)
    WhitelistRule::factory()->create([
        'hostname' => 'example2.com',
        'path_prefix' => 'docs', // no leading slash
        'scheme' => 'https',
        'is_active' => true,
    ]);

    $result = $this->validator->validate('https://example2.com/docs/api');
    expect($result)->toBeTrue();
});

test('getAllowedHosts returns active hostnames', function () {
    WhitelistRule::factory()->create([
        'hostname' => 'example.com',
        'is_active' => true,
    ]);

    WhitelistRule::factory()->create([
        'hostname' => 'test.com',
        'is_active' => true,
    ]);

    WhitelistRule::factory()->create([
        'hostname' => 'inactive.com',
        'is_active' => false,
    ]);

    $hosts = $this->validator->getAllowedHosts();
    expect($hosts)->toBe(['example.com', 'test.com']);
});

test('getErrorMessage returns message with allowed hosts', function () {
    WhitelistRule::factory()->create([
        'hostname' => 'example.com',
        'is_active' => true,
    ]);

    WhitelistRule::factory()->create([
        'hostname' => 'test.com',
        'is_active' => true,
    ]);

    $message = $this->validator->getErrorMessage('https://invalid.com/');
    expect($message)->toBe('The URL must be whitelisted. Allowed hosts: example.com, test.com');
});

test('getErrorMessage returns no rules message when no active rules', function () {
    $message = $this->validator->getErrorMessage('https://example.com/');
    expect($message)->toBe('The URL must be whitelisted. No whitelist rules are configured.');
});

test('validates http scheme with default port 80', function () {
    WhitelistRule::factory()->create([
        'hostname' => 'example.com',
        'path_prefix' => '/',
        'scheme' => 'http',
        'allow_any_port' => false,
        'is_active' => true,
    ]);

    $result = $this->validator->validate('http://example.com/');
    expect($result)->toBeTrue();

    $result = $this->validator->validate('http://example.com:80/');
    expect($result)->toBeTrue();
});

test('rejects http URL with wrong port when allow_any_port is false', function () {
    WhitelistRule::factory()->create([
        'hostname' => 'example.com',
        'path_prefix' => '/',
        'scheme' => 'http',
        'allow_any_port' => false,
        'is_active' => true,
    ]);

    $result = $this->validator->validate('http://example.com:8080/');
    expect($result)->toBeFalse();
});
