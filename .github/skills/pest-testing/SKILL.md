---
name: pest-testing
description: "Tests applications using the Pest 4 PHP framework. Activates when writing tests, creating unit or feature tests, adding assertions, testing Livewire components, browser testing, debugging test failures, working with datasets or mocking; or when the user mentions test, spec, TDD, expects, assertion, coverage, or needs to verify functionality works."
license: MIT
metadata:
  author: laravel
---

# Pest Testing 4

## When to Apply

Activate this skill when:

- Creating new tests (unit, feature, or browser)
- Modifying existing tests
- Debugging test failures
- Working with browser testing or smoke testing
- Writing architecture tests or visual regression tests

## Documentation

Use `search-docs` for detailed Pest 4 patterns and documentation.

## Basic Usage

### Creating Tests

All tests must be written using Pest. Use `php artisan make:test --pest {name}`.

### Test Organization

- Unit/Feature tests: `tests/Feature` and `tests/Unit` directories.
- Browser tests: `tests/Browser/` directory.
- Do NOT remove tests without approval - these are core application code.

### Basic Test Structure

<!-- Basic Pest Test Example -->
```php
it('is true', function () {
    expect(true)->toBeTrue();
});
```

### Running Tests

- Run minimal tests with filter before finalizing: `php artisan test --compact --filter=testName`.
- Run all tests: `php artisan test --compact`.
- Run file: `php artisan test --compact tests/Feature/ExampleTest.php`.

## Assertions

Use specific assertions (`assertSuccessful()`, `assertNotFound()`) instead of `assertStatus()`:

<!-- Pest Response Assertion -->
```php
it('returns all', function () {
    $this->postJson('/api/docs', [])->assertSuccessful();
});
```

| Use | Instead of |
|-----|------------|
| `assertSuccessful()` | `assertStatus(200)` |
| `assertNotFound()` | `assertStatus(404)` |
| `assertForbidden()` | `assertStatus(403)` |

## Mocking

Import mock function before use: `use function Pest\Laravel\mock;`

## Datasets

Use datasets for repetitive tests (validation rules, etc.):

<!-- Pest Dataset Example -->
```php
it('has emails', function (string $email) {
    expect($email)->not->toBeEmpty();
})->with([
    'james' => 'james@laravel.com',
    'taylor' => 'taylor@laravel.com',
]);
```

## Pest 4 Features

| Feature | Purpose |
|---------|---------|
| Browser Testing | Full integration tests in real browsers |
| Smoke Testing | Validate multiple pages quickly |
| Visual Regression | Compare screenshots for visual changes |
| Test Sharding | Parallel CI runs |
| Architecture Testing | Enforce code conventions |

### Browser Test Example

Browser tests run in real browsers for full integration testing:

- Browser tests live in `tests/Browser/`.
- Use Laravel features like `Event::fake()`, `assertAuthenticated()`, and model factories.
- Use `RefreshDatabase` for clean state per test.
- Interact with page: click, type, scroll, select, submit, drag-and-drop, touch gestures.
- Test on multiple browsers (Chrome, Firefox, Safari) if requested.
- Test on different devices/viewports (iPhone 14 Pro, tablets) if requested.
- Switch color schemes (light/dark mode) when appropriate.
- Take screenshots or pause tests for debugging.

<!-- Pest Browser Test Example -->
```php
it('may reset the password', function () {
    Notification::fake();

    $this->actingAs(User::factory()->create());

    $page = visit('/sign-in');

    $page->assertSee('Sign In')
        ->assertNoJavaScriptErrors()
        ->click('Forgot Password?')
        ->fill('email', 'nuno@laravel.com')
        ->click('Send Reset Link')
        ->assertSee('We have emailed your password reset link!');

    Notification::assertSent(ResetPassword::class);
});
```

### Smoke Testing

Quickly validate multiple pages have no JavaScript errors:

<!-- Pest Smoke Testing Example -->
```php
$pages = visit(['/', '/about', '/contact']);

$pages->assertNoJavaScriptErrors()->assertNoConsoleLogs();
```

### Visual Regression Testing

Capture and compare screenshots to detect visual changes.

### Test Sharding

Split tests across parallel processes for faster CI runs.

### Architecture Testing

Pest 4 includes architecture testing (from Pest 3):

<!-- Architecture Test Example -->
```php
arch('controllers')
    ->expect('App\Http\Controllers')
    ->toExtendNothing()
    ->toHaveSuffix('Controller');
```

## Test Behaviour, Not the Implementation

A test earns its place only if it can fail for a reason that matters. If the sole way to
break it is to edit the very lines it mirrors, it is tautological: it asserts that the code
says what it says. It never catches a bug, and it breaks on every harmless rename or
restyle, so the suite grows expensive and nobody trusts a red run.

Signals a test is tautological:

- Asserting on CSS classes, Tailwind utilities, DOM structure, element order, or attribute
  strings copied out of a Blade file.
- `substr_count($html, ...)` or `toContain('<div class="...">')` over rendered markup.
- Restating a constant, config value, enum case, or translation the code already declares:
  `expect(Loan::STATUS_ACTIVE)->toBe('active')`.
- Mocking the method under test, then asserting it was called.
- Asserting a factory produced the attributes the test just passed to that factory.
- Recomputing an accessor's formula in the test and comparing the two.

Assert instead on what a user or a caller would notice:

| Test this | Not this |
|-----------|----------|
| Text the user reads: `assertSee('Adventi ének')` | The classes on the element wrapping it |
| Persisted state: `assertDatabaseHas`, model counts | That an Eloquent call was made |
| `assertRedirect`, `assertForbidden`, policy outcomes | The route string in the controller |
| Dispatched events, sent notifications, queued jobs | That the code calls `dispatch()` |
| JSON payload shape and values | The Resource class's property list |
| Livewire: `assertSet`, `assertSee`, `assertDispatched` | Livewire: rendered HTML fragments |

<!-- Tautological: mirrors the template, cannot catch a bug -->
```php
expect($html)->toContain('bg-accent-foreground/20 text-accent-foreground');
```

<!-- Meaningful: fails when the tab actually shows the wrong scores -->
```php
Livewire::test(Loans::class)
    ->call('selectTab', 'lent')
    ->assertSee('Saját kottám')
    ->assertDontSee('Kölcsönkapott kotta');
```

### Changes With No Behaviour to Assert

A colour swap, a spacing tweak, an icon change: there is nothing a Pest assertion can
observe that isn't a copy of the diff. Do not manufacture one. Either

- cover it where it can genuinely fail — a browser test asserting what the user can see or
  do, or a visual regression screenshot, when the project runs them; or
- add no test, and say plainly in your reply that the change is presentational, how you
  verified it, and that a unit test would only restate the markup.

"No test for this one, and here is why" is a better report than a green assertion that
restates the diff.

## Common Pitfalls

- Not importing `use function Pest\Laravel\mock;` before using mock
- Using `assertStatus(200)` instead of `assertSuccessful()`
- Forgetting datasets for repetitive validation tests
- Deleting tests without approval
- Forgetting `assertNoJavaScriptErrors()` in browser tests
- Asserting on CSS classes or markup the test copied out of the template
- Writing a test for a purely presentational change instead of reporting that none applies
