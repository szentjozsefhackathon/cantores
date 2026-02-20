<?php

use App\Models\WhitelistRule;
use App\Rules\WhitelistedUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

beforeEach(function () {
    WhitelistRule::factory()->create([
        'hostname' => 'example.com',
        'path_prefix' => '/music',
        'scheme' => 'https',
        'is_active' => true,
    ]);
});

test('rule passes for whitelisted URL', function () {
    $rule = new WhitelistedUrl;
    $validator = Validator::make(
        ['url' => 'https://example.com/music/song123'],
        ['url' => $rule]
    );

    expect($validator->passes())->toBeTrue();
});

test('rule fails for non-whitelisted URL', function () {
    $rule = new WhitelistedUrl;
    $validator = Validator::make(
        ['url' => 'https://other.com/path'],
        ['url' => $rule]
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->first('url'))->toContain('The URL must be whitelisted');
});

test('rule passes for empty value', function () {
    $rule = new WhitelistedUrl;
    $validator = Validator::make(
        ['url' => ''],
        ['url' => $rule]
    );

    expect($validator->passes())->toBeTrue();
});

test('rule fails for non-string value', function () {
    $rule = new WhitelistedUrl;
    $validator = Validator::make(
        ['url' => 123],
        ['url' => $rule]
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->first('url'))->toBe('The url must be a string.');
});

test('rule fails for malformed URL', function () {
    $rule = new WhitelistedUrl;
    $validator = Validator::make(
        ['url' => 'not-a-valid-url'],
        ['url' => $rule]
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->first('url'))->toBe('The url is not a valid URL.');
});

test('rule fails for URL without scheme', function () {
    $rule = new WhitelistedUrl;
    $validator = Validator::make(
        ['url' => 'example.com/path'],
        ['url' => $rule]
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->first('url'))->toBe('The url is not a valid URL.');
});

test('rule fails for URL with userinfo', function () {
    $rule = new WhitelistedUrl;
    $validator = Validator::make(
        ['url' => 'https://user:pass@example.com/'],
        ['url' => $rule]
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->first('url'))->toBe('The url is not a valid URL.');
});

test('rule uses custom error message when provided', function () {
    $rule = new WhitelistedUrl('Custom error message');
    $validator = Validator::make(
        ['url' => 'https://other.com/path'],
        ['url' => $rule]
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->first('url'))->toBe('Custom error message');
});

test('rule with withMessage method sets custom error', function () {
    $rule = (new WhitelistedUrl)->withMessage('Please use a whitelisted URL.');
    $validator = Validator::make(
        ['url' => 'https://other.com/path'],
        ['url' => $rule]
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->first('url'))->toBe('Please use a whitelisted URL.');
});

test('rule passes for URL with query parameters', function () {
    $rule = new WhitelistedUrl;
    $validator = Validator::make(
        ['url' => 'https://example.com/music/song?param=value'],
        ['url' => $rule]
    );

    expect($validator->passes())->toBeTrue();
});

test('rule passes for URL with fragment', function () {
    $rule = new WhitelistedUrl;
    $validator = Validator::make(
        ['url' => 'https://example.com/music/song#section'],
        ['url' => $rule]
    );

    expect($validator->passes())->toBeTrue();
});

test('rule fails when no active whitelist rules exist', function () {
    WhitelistRule::query()->delete();

    $rule = new WhitelistedUrl;
    $validator = Validator::make(
        ['url' => 'https://example.com/music/song'],
        ['url' => $rule]
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->first('url'))->toContain('No whitelist rules are configured');
});

test('rule passes for http scheme when rule exists', function () {
    WhitelistRule::factory()->create([
        'hostname' => 'http-example.com',
        'path_prefix' => '/',
        'scheme' => 'http',
        'is_active' => true,
    ]);

    $rule = new WhitelistedUrl;
    $validator = Validator::make(
        ['url' => 'http://http-example.com/'],
        ['url' => $rule]
    );

    expect($validator->passes())->toBeTrue();
});

test('rule fails for wrong scheme', function () {
    $rule = new WhitelistedUrl;
    $validator = Validator::make(
        ['url' => 'http://example.com/music/song'], // rule is https only
        ['url' => $rule]
    );

    expect($validator->fails())->toBeTrue();
});

test('rule passes for case-insensitive hostname', function () {
    $rule = new WhitelistedUrl;
    $validator = Validator::make(
        ['url' => 'https://EXAMPLE.COM/music/song'],
        ['url' => $rule]
    );

    expect($validator->passes())->toBeTrue();
});

test('rule integration with form request validation', function () {
    $rule = new WhitelistedUrl;

    // Simulate form request validation
    $data = ['website' => 'https://example.com/music/song'];
    $rules = ['website' => ['required', $rule]];

    $validator = Validator::make($data, $rules);

    expect($validator->passes())->toBeTrue();
});

test('rule with multiple validation rules', function () {
    $rule = new WhitelistedUrl;

    $data = [
        'url' => 'https://example.com/music/song',
        'other_field' => 'value',
    ];

    $rules = [
        'url' => ['required', 'url', $rule],
        'other_field' => 'required',
    ];

    $validator = Validator::make($data, $rules);

    expect($validator->passes())->toBeTrue();
});

test('rule error message includes allowed hosts', function () {
    WhitelistRule::factory()->create([
        'hostname' => 'allowed.com',
        'path_prefix' => '/',
        'scheme' => 'https',
        'is_active' => true,
    ]);

    $rule = new WhitelistedUrl;
    $validator = Validator::make(
        ['url' => 'https://not-allowed.com/'],
        ['url' => $rule]
    );

    expect($validator->fails())->toBeTrue();
    $error = $validator->errors()->first('url');
    expect($error)->toContain('allowed.com');
    expect($error)->toContain('example.com');
});
