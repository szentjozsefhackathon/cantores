# Log in with a QR code (issue #28)

## Context

A cantor prepares a projection at home and then has to get it onto the screen in church,
from a laptop that is already set up there, already on the internet, and belongs to
nobody in particular. Typing an email address and a password into that laptop is the
whole friction: it is slow on a strange keyboard, it is done in front of the
congregation, and whatever the browser remembers afterwards it remembers for the next
person who sits down.

Issue #28 asks for the pairing flow every messaging app now has. The laptop opens
`cantores.hu/qr` and shows a QR code and nothing else. The phone — already signed in, or
signed in on the spot — scans it, confirms, and the laptop lands on `/plan-documents`
signed in as the cantor. Closing the laptop's browser ends that session; forgetting to
close it can be undone from the phone; and an hour of standing still during the liturgy
must not end it.

Nothing like this exists today. Every authenticated page is `['auth', 'verified']`,
`projections.present` has no lending-link equivalent the way `booklet.loan` does, and the
only way into a session is Fortify's password form.

## The flow

1. **Laptop** opens `GET /qr`. A `device_pairings` row is minted against the laptop's
   guest session; the page renders a QR encoding `https://…/qr/{token}` and, under it, a
   four-character **confirmation code**.
2. The page polls. While the token sits unscanned and ages out, it is rotated **on the
   same row** — one laptop is one row, however long it waits.
3. **Phone** opens `/qr/{token}`. The route is `['auth', 'verified']`, so a signed-out
   phone is bounced to Fortify's login and returns through `url.intended` with no code of
   ours involved. Opening it marks the row scanned, which freezes the token so it cannot
   rotate out from under the phone.
4. The phone shows what it is about to sign in — browser, IP, and the confirmation code
   that is on the laptop screen — and waits for an explicit **Approve** tap.
5. The laptop's next poll sees the approval and redirects to `GET /qr/claim`, a plain
   controller route. It reads the pending pairing id from the laptop's **own session**,
   re-applies the `blocked` / `only_admin_login` gates, signs the user in, records the new
   session id on the row, and redirects to `route('plan-documents')`. The token never
   appears in this step.
6. From then on the laptop's cookie is a **browser-session cookie**, and the phone can end
   the session from a new **Settings → Signed-in devices** page.

## Decisions

**The pairing is claimed by a full page load, not inside the Livewire poll.** Signing in
rotates the session id — `SessionGuard::updateSession()` calls `$this->session->regenerate(true)`
(`vendor/laravel/framework/src/Illuminate/Auth/SessionGuard.php:584`) — and the id we must
record for remote logout is the one that exists *after* that. A plain GET makes the
ordering obvious and puts the new cookie on a normal navigation. The poll's only job is
`$this->redirect(route('qr-login.claim'))`, without `navigate`, so it is a real page load.
No manual `session()->regenerate()` is needed; `Auth::login()` has already done it.

**Closing the browser is a cookie property set per session, not a config change.**
`StartSession::getCookieExpirationDate()` reads `config('session.expire_on_close')` through
`SessionManager::getSessionConfig()`, which is a live `$this->config->get('session')`, and
it reads it inside `addCookieToResponse()` — *after* `$next($request)` has returned
(`vendor/laravel/framework/src/Illuminate/Session/Middleware/StartSession.php:221-272`).
So a middleware appended to the `web` group that sets `config(['session.expire_on_close' => true])`
when the session carries our flag gives that one browser a cookie with `Max-Age` 0, and
leaves `SESSION_EXPIRE_ON_CLOSE` alone for everyone else. The cookie is re-emitted on every
response, so it sticks. (Verified in the installed framework, not assumed.)

**The hour of inactivity is already safe.** `expire_on_close` governs only the cookie. The
server-side window is `config('session.lifetime')` — 120 minutes, untouched — so a paired
session survives an hour of stillness and then some. We do not touch `SESSION_LIFETIME`,
and we do not sign the laptop in with **remember me**: a remember cookie is persistent by
definition and would defeat step 6 entirely.

**Revocation is enforced in middleware, not only by deleting a row.** Deleting the laptop's
`sessions` row is the immediate effect and works because `SESSION_DRIVER=database`. But
making that the only mechanism ties the feature to a config value. The same middleware that
sets the cookie property also checks that the session's pairing is still live and, if it is
not, logs the browser out. Driver-independent, trivially testable under the array driver
the test suite uses, and about five lines.

**Timestamps, not a status enum.** `App\Models\Loan` is the house pattern for a token that
expires and can be revoked (`expires_at`, `revoked_at`, `isLive()`, `scopeLive()`), and it
records *when* alongside *whether*. `device_pairings` follows it.

## Database

New migration `database/migrations/<ts>_create_device_pairings_table.php`, following the
essayistic-docblock style of `2026_09_13_145051_create_projections_table.php` and the column
style of `2026_08_31_150949_create_shares_table.php`:

```php
Schema::create('device_pairings', function (Blueprint $table) {
    $table->id();

    // Null until someone approves: a pending pairing belongs to nobody.
    $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();

    $table->string('token', 32)->unique();

    // The four characters printed under the QR, and read back off the other screen.
    $table->string('confirmation_code', 4);

    // The browser that asked for the code — the only one allowed to claim it.
    $table->string('requesting_session_id')->index();

    // The browser's session once it is signed in; what "log this device out" ends.
    $table->string('session_id')->nullable()->index();

    $table->string('ip_address', 45)->nullable();
    $table->text('user_agent')->nullable();

    $table->timestamp('expires_at');                    // the token's own short life
    $table->timestamp('scanned_at')->nullable();
    $table->timestamp('approved_at')->nullable();
    $table->timestamp('claimed_at')->nullable();
    $table->timestamp('revoked_at')->nullable();
    $table->timestamp('last_seen_at')->nullable();

    $table->timestamps();

    $table->index(['user_id', 'claimed_at']);
});
```

`expires_at` governs only the pre-claim token. Once `claimed_at` is set the row is a
signed-in device and only `revoked_at` and `last_seen_at` matter.

## Files

### Create

| File | What it holds |
|---|---|
| `database/migrations/<ts>_create_device_pairings_table.php` | The table above. |
| `app/Models/DevicePairing.php` | Mirrors `Loan`: `TOKEN_LENGTH = 32`, `generateToken()`, `casts()`, `isPending()`/`isApproved()`/`isLive()`/`hasExpired()`, `rotate()`, `approveFor(User)`, `scopeLiveDevices()`, `describeDevice()`. |
| `database/factories/DevicePairingFactory.php` | `fake()` style of `ProjectionFactory`, with `approved()`, `claimed()` and `expired()` states. |
| `app/Services/QrCodeRenderer.php` | `toSvg(string $data, int $size = 320): string`. Same Writer/`ImageRenderer`/`RendererStyle`/`SvgImageBackEnd` recipe Fortify uses in `vendor/laravel/fortify/src/TwoFactorAuthenticatable.php:65`, black on white, XML declaration stripped so it inlines. |
| `app/Services/SessionStore.php` | The one place that touches the framework-owned `sessions` table, through `config('session.connection')` / `config('session.table')`. Exists because that table has no model and `CLAUDE.md` bans loose `DB::` calls. |
| `app/Http/Middleware/EnforcePairedDeviceSession.php` | Sets `session.expire_on_close` for a paired session, refreshes `last_seen_at` at most once a minute, and signs the browser out if its pairing is gone or revoked. Docblock in the voice of `EnsureVisitorIsHuman`. |
| `app/Http/Controllers/QrLoginClaimController.php` | Single `__invoke`. Reads the pairing id from the laptop's session, checks it is approved, unexpired and unclaimed; re-applies `blocked` and `only_admin_login`; `Auth::login($user)` (no remember); records `session()->getId()` and `claimed_at`; redirects to `plan-documents`. |
| `app/Livewire/Pages/QrLogin.php` + `resources/views/livewire/pages/qr-login.blade.php` | The laptop page. |
| `app/Livewire/Pages/QrLoginApproval.php` + `resources/views/livewire/pages/qr-login-approval.blade.php` | The phone page. |
| `resources/views/pages/settings/⚡devices.blade.php` | Signed-in devices, as a Livewire SFC matching `⚡appearance.blade.php`. |
| `app/Console/Commands/PruneDevicePairingsCommand.php` | `cantores:prune-device-pairings`, `handle(): int`, `--dry-run`. |
| `tests/Feature/QrLoginTest.php`, `tests/Feature/Settings/PairedDevicesTest.php`, `tests/Feature/PruneDevicePairingsCommandTest.php` | See the test plan. |

### Modify

- **`composer.json`** — add `"bacon/bacon-qr-code": "^3.0"` to `require`. It is already installed and locked (Fortify pulls it in for the 2FA QR); this only stops us depending on somebody else's dependency by accident. No `composer update` beyond `composer require --no-update` + `composer update --lock`.
- **`routes/web.php`** — three routes, next to the `/emberi-ellenorzes` block, which is the other "a guest device proves something" flow:

  ```php
  Route::get('/qr/claim', \App\Http\Controllers\QrLoginClaimController::class)
      ->middleware('throttle:30,1')
      ->name('qr-login.claim');

  Route::livewire('/qr', \App\Livewire\Pages\QrLogin::class)
      ->middleware('throttle:30,1')
      ->name('qr-login');

  Route::livewire('/qr/{token}', \App\Livewire\Pages\QrLoginApproval::class)
      ->where('token', '[A-Za-z0-9]{32}')
      ->middleware(['auth', 'verified'])
      ->name('qr-login.approve');
  ```

  `claim` is declared first *and* `{token}` is constrained, so `/qr/claim` cannot be
  swallowed either way. The slug stays `/qr` — the issue names that URL, and it is what
  gets typed on a strange keyboard.
- **`routes/settings.php`** — `Route::livewire('settings/devices', 'pages::settings.devices')->name('devices.edit');` in the `['auth', 'verified']` group.
- **`resources/views/pages/settings/layout.blade.php`** — a `flux:navlist.item` for it.
- **`bootstrap/app.php`** — `$middleware->appendToGroup('web', \App\Http\Middleware\EnforcePairedDeviceSession::class);` It must sit inside `StartSession`, which `appendToGroup` guarantees.
- **`routes/console.php`** — `Schedule::command('cantores:prune-device-pairings')->hourly()->withoutOverlapping();`
- **`app/Models/User.php`** — `devicePairings(): HasMany`.
- **`resources/views/pages/auth/login.blade.php`** — a small link to `route('qr-login')` under the form. Without it nobody discovers `/qr`.
- **`public/robots.txt`** — `Disallow: /qr/` in the existing secret-links block. See *Search engines*.
- **`lang/hu.json`** — the new strings.

## The two pages

### `/qr` — the laptop

`mount()`: if `Auth::check()`, redirect to `plan-documents` — this page is for a browser
that is not signed in. It does **not** mint anything.

The pairing is minted by `startPairing()`, fired from `wire:init` on the wrapper: find the
live pairing for `session()->getId()` or create one (`expires_at = now()->addMinutes(2)`),
recording `ip_address` and `user_agent`. Minting on the first Livewire round-trip rather
than in `mount()` costs a real user about one extra hop and costs a crawler nothing at all
— see *Search engines* below, which is the reason it is done this way.

`rendering()` puts it on `layouts::auth` with `'logo' => true` and `'title' => __('Sign in with a QR code')` — the centred `max-w-lg` column of `resources/views/layouts/auth/simple.blade.php`, which is already "a page with nothing else on it". Pass `'noindex' => true`.

The view is a white `p-4` box holding `{!! $qrSvg !!}` at `w-64 h-64` (SVG, so CSS scales it),
the confirmation code in a large tracking-wide monospace line, and one sentence of
instruction. A `wire:poll.2s="checkPairing"` wrapper, rendered only while `$this->waiting`,
per the house idiom at `resources/views/livewire/pages/score-view.blade.php:69`.

`checkPairing()`:
- approved → `$this->redirect(route('qr-login.claim'))` (no `navigate`, so the cookie lands on a real navigation);
- expired, unscanned → `rotate()` a new token, code and `expires_at` on the same row;
- scanned but unapproved → stop rotating, swap the caption to *"Confirm on your phone"*;
- nothing for 15 minutes → stop polling and show a **Refresh** button, so an abandoned
  church laptop does not poll for a week.

### `/qr/{token}` — the phone

`mount(string $token)` resolves a live, pending pairing or sets `$this->invalid`. On a valid
one it stamps `scanned_at` and pushes `expires_at` to `now()->addMinutes(5)` using the
`incrementEach`-style write `Loan::touchLastViewed()` uses, so `updated_at` stays clean.

The body asks *"Sign this device in?"*, lists the browser and IP, prints the confirmation
code with *"check that this code is on the other screen"*, and offers **Approve** and
**Cancel**. Approve sets `user_id` and `approved_at`; cancel sets `revoked_at`. Afterwards
the page becomes the control panel for that device: *"The device is signed in"* with a
**Log out this device** button and a link to Settings → Signed-in devices.

An unknown, expired or already-used token renders one neutral line — *"This code is no
longer valid"* — with no distinction between the cases.

### Settings → Signed-in devices

Lists `auth()->user()->devicePairings()->liveDevices()`: `describeDevice()`, IP,
`claimed_at`, `last_seen_at`, and a **Log out** button per row. The row matching
`session('qr_pairing_id')` is labelled *This device*. `describeDevice()` is a deliberately
crude `str_contains` match over a handful of browser and OS names — a real UA parser is not
worth a dependency here.

Logging out calls `SessionStore::forget($pairing->session_id)` and sets `revoked_at`; the
middleware catches whatever the deletion misses.

## Search engines

Three things matter, and the app already has a settled position on each.

**The sitemap will not carry it.** `SitemapController::urls()` enumerates a hard-coded
whitelist of route names plus published scores, music and authors. `/qr` is not on that
list and nothing adds it, so no change is needed there — worth stating only because the
answer could have been otherwise.

**`/qr` must be fetchable but not indexable.** A "Sign in with a QR code" link on the login
page is the only way anyone discovers this feature, and that link is a crawlable path. So
the page passes `'noindex' => true` through `resources/views/partials/head.blade.php`, which
already supports it, and it is deliberately **not** disallowed in `robots.txt`: a crawler
has to fetch a page to see its `noindex`, and blocking the fetch is what leaves a bare URL
listed in results with no description. Let it be crawled, let it say no.

**A crawl must not mint a pairing.** This is the real cost, and it is why `startPairing()`
hangs off `wire:init` instead of `mount()`. A crawler carries no cookie, so every visit
would otherwise be a fresh session and a fresh `device_pairings` row — a page the hourly
prune exists to mop up after, forever. Crawlers do not boot Livewire, so under `wire:init`
a crawl reads a static "starting…" placeholder and writes nothing. The `throttle:30,1` then
covers the deliberate case rather than the routine one.

**The token pages follow the secret-link convention.** `public/robots.txt` already carries

```
Disallow: /s/
Disallow: /f/
Disallow: /p/
Disallow: /share/
```

with a comment explaining that those pages send `noindex` and the rules also cover the file
endpoints underneath, which cannot. Add `Disallow: /qr/` in the same block — note the
trailing slash, which matches `/qr/{token}` and `/qr/claim` but leaves `/qr` itself
crawlable, exactly as the paragraph above needs. The approval page sends `noindex` too, so
a token that somehow reaches a crawler is doubly covered.

## Security

The realistic attacks, and what is worth paying for:

- **A bystander photographs the QR on the screen.** They can sign *their own* account into
  the church laptop. That is a nuisance, not a compromise, and the operator is standing in
  front of the machine. The row is single-use and two minutes old. Nothing extra.
- **The link is phished to you remotely** — the one worth defending. The explicit Approve
  tap, the named device, and above all the confirmation code that must match a screen in
  front of you are the defence: an attacker's code has no screen for you to check it
  against. This is why the code is in the design at all.
- **Token replay.** One-time (`claimed_at`), and the claim never reads the token at all:
  it looks the pairing up by an id kept in the laptop's own session
  (`DevicePairing::PENDING_SESSION_KEY`), so a code photographed off the screen is useless
  in any other browser. The `requesting_session_id` column stays as a record of which
  session asked, but the session cookie is what actually proves it.
- **Session fixation.** `Auth::login()` migrates the session id with destroy, already.
- **Enumeration.** 32 alphanumerics from `Str::random`, `/qr/{token}` behind `auth` and a
  throttle, and one neutral message for every failure.
- **Row spam on `/qr`.** `throttle:30,1`, plus one row per browser session because the
  token rotates in place.

Cut deliberately: no Turnstile on `/qr` (it mints nothing worth having, and the throttle
covers it); no `password.confirm` before approving (a cantor in a sacristy with an unlocked
phone — the friction is not worth it); no geo-IP.

## Pruning

`cantores:prune-device-pairings`, hourly:

- unclaimed rows whose `expires_at` is more than an hour old → delete;
- rows whose `revoked_at` is more than seven days old → delete;
- claimed, unrevoked rows whose `last_seen_at` is older than `config('session.lifetime')`
  minutes → mark revoked, so the Settings list only ever shows devices that are genuinely
  still there.

## Tests

`tests/Feature/QrLoginTest.php` — flat `test()` style, `User::factory()`, `Livewire::test()`:

1. a plain GET of the qr page mints no pairing — the crawler case
2. booting the component mints exactly one pending pairing
3. both qr pages send noindex
4. a signed-in visitor is sent to plan documents instead
5. polling rotates the token on the same row once it has expired
6. a scanned pairing stops rotating and asks for confirmation
7. the approval page sends a signed-out phone to the login screen and back (`url.intended`)
8. an unknown or expired token is shown as no longer valid
9. approving records the user and the moment
10. claiming an approved pairing signs the laptop in and lands it on plan documents
11. a pairing cannot be claimed by a browser that did not request it
12. a pairing cannot be claimed twice
13. a blocked user cannot claim a pairing
14. admin-only login blocks a non-admin claim
15. `last_login_at` is set by a qr claim (the `Login` listener fires)
16. a paired session's cookie expires when the browser closes — assert the response cookie's
    `getExpiresTime()` is `0`; the array session driver still emits the cookie, because
    `StartSession::sessionIsPersistent()` only checks the driver is non-null
17. an ordinary session's cookie keeps its real expiry

`tests/Feature/Settings/PairedDevicesTest.php`:

18. the page lists only this user's live paired devices
19. logging a device out revokes the pairing and removes its session row
20. a revoked pairing signs its browser out on the next request
21. a user cannot log out someone else's device

`tests/Feature/PruneDevicePairingsCommandTest.php`:

22. expired unclaimed pairings are deleted
23. long-revoked pairings are deleted
24. a claimed pairing whose session went idle is revoked

## Hungarian strings

English keys, Hungarian values in `lang/hu.json`, per the house convention:

| Key | Value |
|---|---|
| `Sign in with a QR code` | Bejelentkezés QR-kóddal |
| `Scan this code with your phone` | Olvasd be ezt a kódot a telefonoddal |
| `Waiting for confirmation on your phone…` | Várakozás a telefonos megerősítésre… |
| `This code is no longer valid.` | Ez a kód már nem érvényes. |
| `Refresh` | Frissítés |
| `Sign this device in?` | Belépteted ezt az eszközt? |
| `Check that this code is on the other screen:` | Ellenőrizd, hogy ez a kód látható a másik képernyőn: |
| `Approve` | Jóváhagyás |
| `The device is signed in.` | Az eszköz be van jelentkezve. |
| `Signed-in devices` | Bejelentkezett eszközök |
| `Log out this device` | Eszköz kiléptetése |
| `This device` | Ez az eszköz |
| `No devices are signed in with a QR code.` | Nincs QR-kóddal bejelentkezett eszköz. |

## Order of work

1. Migration, model, factory.
2. `QrCodeRenderer`, the `/qr` page, its tests.
3. The approval page, its tests.
4. Claim controller and `EnforcePairedDeviceSession`, their tests — including the cookie
   assertion, which is the one piece worth proving early.
5. Settings → Signed-in devices, its tests.
6. Prune command and schedule, its tests.
7. `lang/hu.json`, the login-page link, `vendor/bin/pint --dirty --format agent`.

## Verification

```
php artisan test --compact --filter=QrLogin
php artisan test --compact --filter=PairedDevices
php artisan test --compact --filter=PruneDevicePairings
vendor/bin/pint --dirty --format agent
```

End to end, with two browsers — an ordinary window as the laptop, a private window signed
in as the phone:

1. Open `/qr` in the ordinary window. A QR and a four-character code appear.
2. Paste the encoded URL into the private window. It shows the device and the same code.
3. Approve. The first window should land on `/plan-documents`, signed in.
4. In devtools → Application → Cookies, the `…-session` cookie's Expires column should read
   **Session**, while a cookie from an ordinary password login carries a date.
5. Settings → Signed-in devices in the private window lists the laptop; **Log out**, then
   reload the first window — it should be a guest again.
6. Leave the paired window idle for over an hour and reload: still signed in.

## As built

Two notes where the code differs from the design above.

**The claim is keyed by session data, not by `requesting_session_id`.** Both derive from
the same cookie, so they are equally strong, but a value in the session store is the
thing that actually says "this browser" and it is the one the test harness can exercise —
Laravel's test client gives every request a fresh session id, so a column comparison could
only ever have been asserted in a browser. The column is still written, as a record of
which session asked.

**`layouts/auth.blade.php` and `layouts/auth/simple.blade.php` gained a `noindex` prop.**
They had no way to pass one through to `partials/head.blade.php`, which already supports
it. Defaulted to `false`, so every existing auth page is unchanged.

One test in the suite, `tests/Feature/Livewire/PageWidthConsistencyTest.php`, fails
intermittently — about five runs in twenty, on this branch and on `main` alike. Its
factories sometimes build an author or collection the test user may not view, and the 403
renders an error page instead of the layout. Pre-existing, and left alone.
