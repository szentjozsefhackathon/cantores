# What a Sunday Costs, and How To Stop Paying It

## Context

Two requests a second, per running service, for the length of a Mass. That is
the number this whole document is about, and it comes from
`resources/js/projection-follow.js:42` — `POLL_MS = 1000`, asked by the wall and
by the phone alike, each of them reading `screens.state` with the presentation's
own state nested inside.

The work already done to make that cheap is real and should be said first,
because the rest of this argues that it was the wrong axis and that is only fair
if the right amount of credit goes to it. `ProjectionLoadTest` pins the read at
four queries and no writes. `Screen::touchLastSeen()` (`app/Models/Screen.php:340`)
collapses a per-second write into one per `SEEN_EVERY_SECONDS`.
`Projection::revision()` caches the one join. Sessions moved off Postgres because
every poll wrote a row to move `last_activity`. The poller backs off and jitters
rather than piling requests onto a server that is already struggling. All of that
stands, and none of it is undone here.

**What is left is not queries. It is the bootstrap.** The production image is
`php:8.4-apache` — mod_php on mpm_prefork, one process per request in flight, and
`docker/conf-available/performance.conf` caps that at sixteen because a gigabyte
will not pay for more. Every one of those two-a-second reads pays for a whole
PHP: the autoloader, every service provider, the eleven observers registered in
`AppServiceProvider::boot()`, the middleware stack, a session read and a session
write, and two `Gate::allows` evaluations — in order to answer six fields that
usually have not changed since the last time it answered them.

And the polling is not even the larger half of it. **This is a Livewire
application.** Every `wire:model.live`, every action, every component refresh
anywhere on the site is a full framework boot for a fragment of HTML. The score
editor, the projection editor, the admin tables — all of them pay the same price
the poll pays, dozens of times per page, and they do it whether or not anybody is
projecting.

### What is not the problem

Worth writing down, so that these are not re-litigated later.

**Memcached is not slow.** For a plain key-value cache it is arguably the better
engine — multi-threaded where Redis is single-threaded. Nobody should move this
cache expecting it to get faster, and the section below does not claim it will.

**Cloudflare is not the latency.** A round trip is 30–80ms against a poll
interval of 1000ms. The design already accepts this: `plans/projection-remote-control.md`
rejected the local-network path outright, on the grounds that both devices are
already authenticated against the same server and a hotspot built before every
Mass is the thing being replaced. The common case is a phone and a laptop, not
two windows on one machine, so there is no local shortcut to take.

**The four queries are not the cost.** Taking them to zero saves four indexed
reads against a Postgres on the same docker network. It leaves the PHP process
exactly where it was.

---

## Measured, 17 September 2026

Octane is in production and Redis carries the sessions. The poll itself is
unchanged: `POLL_MS` is still 1000 and both ends still ask. So before section 3
was started, the one thing it said must be measured rather than assumed was
measured — and it turned up a problem the section did not know it had.

**The network holds a wait comfortably.** A `traefik/whoami` behind the
production Traefik, asked to `?wait=`:

| Path | Held | Result |
|---|---|---|
| Browser → Cloudflare → Traefik | 110s | `200` |
| Server → Traefik directly | 95s | `200` |
| Phone on a mobile network | 60s | answered |

None of the layers between a phone and the container is the limit.

**The worker is.** `config/octane.php` sets `max_execution_time` to 30, and on
FrankenPHP that is wall-clock time: Octane's worker calls `set_time_limit()`
per request, and a ZTS build times the thread, sleeping or not. A route that
slept 40 seconds in dev died at 30.19s with *Maximum execution time of 30
seconds exceeded*. A wait blocked on Redis would die the same way.

**And the worker is the real objection, not the timeout.** Section 3 says that
an event-driven runtime does not pin a process on a hanging request. FrankenPHP
in worker mode is not that runtime: Go serves the connections, but each PHP
request occupies a worker thread from start to finish, and a PHP thread blocked
on `SUBSCRIBE` is blocked. A held read would take one of `OCTANE_WORKERS` for
its whole hold — eight workers, eight devices following a service, nationwide.
That is prefork's ceiling again, only lower.

So section 3 as written is withdrawn. The idea survives — a published write
reaching the wall as fast as the network carries it — but the waiting cannot
happen inside a PHP worker. It needs something that holds connections without
holding a thread:

- **Mercure**, which is built into FrankenPHP. Laravel publishes on a write;
  the hub, in Go, holds the Server-Sent Events connections. No new container,
  though the Caddyfile gains a `mercure` block and the subscribers need a JWT.
- **Reverb**, Laravel's own WebSocket server. Another long-running process and
  another route through Traefik, but first-party and with Echo on the client.

Either is a dependency to approve, and either keeps the rule of
`projection-follow.js`: the poller stays as the fallback, and a dead push
channel costs one beat of lag rather than the service. Until one of them is
chosen, the step worth taking is the conditional read (step 4), which is done —
see below.

### The conditional read, as built

Not quite as step 4 described it. The client does not send its version; the
browser does the asking. Both hot reads — `show.state` and
`presentations.state` — go through `NotModifiedWhenUnchanged`, which tags the
body with an ETag and `Cache-Control: no-cache, private`. The browser's own
cache then revalidates every `fetch` with `If-None-Match`, a quiet poll comes
back as an empty `304`, and `fetch` hands the page the stored body with a `200`
on it. Nothing in the JavaScript changed.

Why not the version: the show's answer is not only the presentation's state. It
carries the screens, and whether a screen is live is `last_seen_at` against a
clock — it changes without anything being written. No version covers that, and
a hash of the body covers everything. The price is that the answer is still
built on every poll: this saves the wire, the compression and the client's
parse, **not the queries**. The read is also the wall's heartbeat, so some of
that work was never going to go away.

The tag is **weak**, and that was measured on the dev FrankenPHP with the
production Caddyfile. Caddy's `encode` appends the encoding to a strong tag
(`"…-zstd"`) and removes it again from `If-None-Match` — but only from a strong
one. Cloudflare weakens a strong tag whenever it touches compression, and
`W/"…-zstd"` then matches nothing, silently, forever. A weak tag passes through
Caddy unchanged and matches under any encoding, with or without the `W/`.

Worth confirming once in production: the devtools network panel on `/remote`
should show `304` on the state reads while nothing moves.

### Mercure, as built

Mercure was chosen: it is already inside the FrankenPHP binary, so there is no
new container, no new route through Traefik and no new PHP dependency.

**The hub carries the show itself.** At first it said only "something changed":
the answer was worked out per device (`isThisDevice`, which screens are live),
so no one message could be the answer for every device. Since the show is
described for a *person*, with each screen carrying the flags a browser needs to
answer those two questions itself, one frame fits every device. `ShowStream`
publishes `{at, show}` to a private topic per person — `show` is exactly what
`ShowState::forUser()` returns to a read, `at` is the server's clock so that a
frame which overtook another is not drawn over it. A press of the space bar is
one publish instead of a read from every listening device.

The `show.state` read stays the authority. A frame can be lost or late, and
some of the answer moves with a clock rather than a save, so every device keeps
asking, just less often. A frame the client cannot parse into a stamped show —
the old bare nudge, a hub keep-alive — is answered with a read, as before.

- **Publishing** is `ShowStreamObserver` on the saves that move an answer:
  presentations, screens, device names and pairings to their person, and deck
  edits to whoever has that deck live. A write that only moves `last_seen_at` is
  ignored, so the wall's ten-second report publishes nothing. The call is
  `defer()`red and named per person, so one request publishes at most once per
  person, after the response. A hub that is down costs a log line.
- **Subscribing** is `POST /show/stream`, which sets an HS256 token scoped to
  that person's topic as an HttpOnly cookie on `/.well-known/mercure` (native
  `EventSource` cannot send a header). It lasts an hour. When the hub turns a
  device away, the client asks for a new one. The token outlives a sign-out,
  and since frames carry the show, a device that has lost its session goes on
  receiving its contents until the token lapses.
- **The client** (`showStream()` in `projection-follow.js`) hands every message
  to `pushedShow()`, draws it when `isNewerFrame()` says so, and falls back to a
  read when it is not a frame. While the stream is open, the poll slows from
  `POLL_MS` to `PUSHED_POLL_MS` (5s) and stays as a safety net for clock-driven
  changes and half-dead connections. When the stream closes, the poll is back to a second
  at once. With the hub unconfigured, the endpoint says so and nothing changes.
- **No Redis transport.** There is one app container and nothing to replay, so
  `transport local` is enough. A second app container would need a shared
  transport.
- **`grace_period 25s`.** Caddy waits for ever by default for connections to
  close on shutdown, and measured in dev, an open subscriber held a SIGTERM until
  that subscriber left. Without the limit, every deploy would wait for Docker's
  SIGKILL.

Expected load while a service runs: one read per device every 5 seconds, plus
one publish per person each time the slide moves.

---

## 1. The cache gets its own Redis

Memcached goes. Not for speed — for two reasons that have nothing to do with it.

**One store instead of two.** The memcached container holds `mem_limit: 256m` on
a box where Postgres already has 1280m and the app 1024m, and Redis is running
beside it anyway. Two volatile key-value stores is one more image to patch, one
more failure mode, and a quarter of a gigabyte not spent on anything else.

**Redis has the primitives the rest of this plan needs.** Pub/sub, blocking
reads, sorted sets, Lua. Section 3 is built out of them and memcached has none of
them. Once Redis is carrying live projection state, a separate memcached for the
ordinary cache is a duplicate capability kept for its own sake.

### A second container, not a second database

`REDIS_CACHE_DB` already exists in `config/database.php:174`, and the note in
`.env.prod.dist` is right that keeping the cache on its own database stops
`cache:clear` taking the sessions with it. It is not enough on its own, because
**`maxmemory` is a property of the instance, not of the database.** A cache
sharing the session instance shares its 128MB ceiling and its `volatile-lru`
policy, and those were chosen for sessions: every session key carries a TTL, so
under pressure Redis drops the ones nobody has touched and somebody signs in
again.

An earlier draft of this argument pointed at `Cache::rememberForever` in
`app/Models/Genre.php:135,149,163` as a present danger — TTL-less keys that
`volatile-lru` cannot evict. That was overstated and is withdrawn: **there are
three genres, there will always be three genres, and `rememberForever` is exactly
right for them.** A few kilobytes that never expire are not a threat to a 128MB
ceiling.

The forward-looking reason survives intact, though, and it is the one that
decides this. The cache is going to accumulate — that is the point of having one,
and the intention is to put more in it. Its growth is unbounded by design and its
eviction policy wants to be `allkeys-lru`, because a cache should shed its
coldest entries under pressure. The session store's growth is bounded by the
number of people signed in and its policy must never be `allkeys-lru`, because
evicting a session is signing a church laptop out mid-Mass. **Two workloads that
want opposite eviction policies do not belong behind one `maxmemory`.**

So: a `redis-cache` service, no `appendonly`, no volume, `maxmemory 128mb`,
`maxmemory-policy allkeys-lru`. Half the memory memcached had, a policy that
suits a cache, and a session instance whose blast radius is now literally zero —
a different process cannot be reached by a `FLUSHDB` aimed at this one.

### The changes

- `docker-compose.prod.yml`: drop the `memcached` service; add `redis-cache` on
  the `internal` network with the settings above and a `redis-cli ping`
  healthcheck. Mirror in `docker-compose.yml` for dev parity.
- `config/database.php`: point the `cache` connection's host at a new
  `REDIS_CACHE_HOST` defaulting to `REDIS_HOST`, so one variable moves the cache
  to its own box and nothing else changes. `REDIS_CACHE_DB` stays at `1` — it
  costs nothing and keeps the two legible if they are ever collapsed again.
- `.env.prod.dist`: `CACHE_STORE=redis`, `REDIS_CACHE_HOST=redis-cache`, and the
  comment on the `redis` service edited so it no longer says the cache is
  memcached's.
- `Dockerfile.prod:80`: drop `memcached` from `install-php-extensions`.
- `app` and `queue` gain a `depends_on` on `redis-cache`.

One thing to watch that is easy to miss: **the rate limiter lives in the cache
store.** `throttle:projection-poll` and `throttle:projection-payload`
(`AppServiceProvider:104,110`) keep their counters there, so under `allkeys-lru` a
counter can be evicted and a limit effectively reset. This is fine and is the
correct trade — the limiters exist to stop a runaway client, not to defend
against anybody, and their own docblock says so.

---

## 2. Octane

This is the item that matters, and the reason is not the projection poll. It is
that every Livewire round trip in the application is currently a cold boot of
Laravel.

**The instinct from the Tomcat world is the correct one here.** The application
is stateless per request; what is being rebuilt twice a second is not application
state but the *framework* — the container, the providers, the routes, the
observers. Octane is the servlet container staying warm between requests. The
request handler is thrown away as it always was; the engine underneath it is not.

`GenreContext` is the right thing to have checked, and it is clean:
`app/Services/GenreContext.php:15-23` reads `Auth::user()` and `Session::get()`
on every call rather than resolving them once and holding them. It is a singleton
that *asks* about the current request rather than one that *remembers* a request,
which is precisely the distinction Octane cares about. Same for the four
renderers registered beside it — they are built from config and hold nothing.

### The runtime: FrankenPHP

Over Swoole, for reasons that are mostly about this deployment rather than about
the runtimes:

- It is a single binary that speaks HTTP itself, so it replaces Apache rather
  than sitting behind it. `docker/conf-available/performance.conf` and the prefork
  ceiling it exists to lower both go away, and with them the whole class of
  problem that file was written about.
- No PECL extension to build and keep building. `Dockerfile.prod` changes its
  base image and little else.
- Traefik is unaffected: it goes on terminating TLS and routing by host label,
  and the container behind it still answers on port 80.

### What has to be verified before this ships

Not a list of fears — a list of greps, each of which is a real Octane hazard:

1. **Container bindings that capture request state in a constructor.** A
   singleton taking `Request` or a `User` as a promoted property holds the first
   request's copy for the life of the worker. `AppServiceProvider::register()` is
   clean; the rest of `bootstrap/providers.php` needs the same read.
2. **Static properties used as per-request memory.** `PresentationState::$entries`
   (`app/Services/PresentationState.php:35`) is the shape to look for and is
   *not* an instance of it — it is a plain instance property on a class resolved
   fresh per request, and its own docblock says "nothing here outlives the
   container that made it". That must stay true. Anything genuinely static that
   caches per-user or per-request data is a bug under Octane.
3. **`Auth::user()` resolved during a provider's `boot()`.** There is no request
   when a worker boots, and whatever is resolved there is frozen.
4. **Livewire 4 and Flux** are Octane-supported; no action beyond running the
   suite against the Octane runtime.
5. **The queue, scheduler and musescore workers do not change.** They are
   `artisan` processes and have never paid the web bootstrap. Octane is a web
   concern only.

### The changes

- `composer require laravel/octane` — **a dependency addition, which needs
  approval before it is made.**
- `Dockerfile.prod`: base `dunglas/frankenphp` instead of `php:8.4-apache`; drop
  `a2enmod` / `a2enconf` and the Apache config copies; the entrypoint becomes
  `php artisan octane:frankenphp`.
- `docker-compose.prod.yml`: the `app` service gains a healthcheck against a
  real URL, and worker count is set from the memory limit rather than inherited
  from a prefork default.
- `deploy-prod.sh`: a deploy must now restart or reload the workers, because the
  code they hold is the code they booted with. `octane:reload` where the
  container survives, a container replacement where it does not.
- Dev parity: `Dockerfile.dev` and `composer run dev` keep working the same way.

---

## 3. The poll becomes a wait

> **Withdrawn as written** — see *Measured, 17 September 2026*. A held request
> occupies a FrankenPHP worker for the whole hold; the waiting belongs in
> Mercure or Reverb, not in PHP.

With Octane in place, the second structural change becomes possible — and it is
*only* possible with Octane in place, which is why it is third and not second.

**On prefork, a hanging request pins a process.** Sixteen workers means sixteen
concurrent services nationwide, and the seventeenth parish gets nothing. That is
not a tuning problem; it is the reason long-polling has been unavailable to this
application from the start. An event-driven runtime does not have it.

### The shape

- **Writes stay exactly as they are.** `PresentationStateController::update` goes
  on being an ordinary authenticated POST. It happens when a slide moves —
  perhaps fifty times in a Mass — and there is nothing to save there.
- **A write publishes.** After `Presentation::applyState()` bumps the version
  (`app/Models/Presentation.php:292`), the new state is published on a Redis
  channel keyed to the presentation, and mirrored into a Redis key so a reader
  arriving mid-service has something to read without touching Postgres.
- **A read waits.** `screens.state` accepts the version the client already holds.
  If Redis says the version has moved, it answers immediately. If it has not, the
  request subscribes and blocks for up to about 25 seconds, returning the moment
  a write publishes or `304` when the timer runs out.

Two requests a second per service become roughly one per twenty-five seconds — a
fiftyfold reduction in requests, and a much larger one in database work, since
the waiting request touches Postgres only when something actually happened.

**And it makes the feature better, not merely cheaper.** Today the wall is up to
a second behind the thumb, because that is what a 1000ms poll means. A published
write arrives as fast as the network carries it. The lag that the optimistic
remote was built to paper over (`projection-remote.js:95`) largely stops
existing.

### What may not be traded for it

The rule at the head of `projection-follow.js` is not negotiable and this section
is written underneath it: *losing the network costs the remote and nothing else.*

- **The poller stays.** `poller()` and its backoff are not replaced; the waiting
  read is a longer interval through the same machinery, which already treats a
  failure as "answer `false` and try again later" rather than as an event.
- **A timed-out wait is a success, not a failure.** It must not trigger backoff.
  A `304` after 25 quiet seconds is the endpoint working perfectly.
- **The fallback is the current behaviour.** If the long read fails twice in a
  row, the client drops to `POLL_MS` short reads and keeps going. The site must
  never depend on the wait working.
- **Cloudflare and Traefik must both be checked for idle timeouts** shorter than
  the hold. This is the one thing most likely to make this section not work, and
  it should be tested against production before the client is changed to rely on
  it. The hold length is a constant precisely so it can be lowered.
- **Postgres stays the truth.** Redis carries the live copy for the readers;
  the row is still written, and a Redis that has lost everything means a colder
  read, not a lost service.

---

## 4. The ping

A number on the remote, the way an online game shows one: round-trip time to the
server, always visible, in the phone's chrome.

**Why it earns its place.** If the connection goes, the deck on the laptop keeps
working perfectly — it was engraved once at load
(`projection-presenter.js`, whole-deck engraving at mount) and its slide
advancing is local. So the failure mode is not "the service stops", it is "the
phone stops driving and the cantor does not know it". **That is the accepted
compromise: the answer to a dead network is to walk to the laptop and click.**
It is a good answer, and it only works if the cantor learns that they need to
take it — during the liturgy, from the organ bench, without a diagnosis.

A latency number does that better than an error would, because it degrades:
green at 40ms, amber as it climbs, red and then a plain *offline* when reads stop
landing. The cantor watches it drift the way a player watches a ping bar, and
decides to walk over before the wall is stuck rather than after.

### Rules

- **The remote only.** Nothing is ever drawn on the wall. The congregation does
  not see a network indicator.
- **Measured, not invented.** The elapsed time of the state read that already
  happens, in `pull()` — no extra request exists to produce this number.
- **Under the long-poll, the ping is not the hold.** A waiting read that returns
  after 25 seconds has a 25-second duration and a 40ms latency. What is measured
  is the write's round trip, or a cheap read's, and the field is named for what
  it actually is so nobody later "fixes" it to include the wait.
- **Quiet when healthy.** Small and grey at a good latency; it earns colour only
  when it has something to say.
- **It never blocks anything.** A missing measurement shows the last known value
  going stale, not an empty state that makes the panel jump.

Lives in `resources/js/projection-remote.js` beside `wallBehind`
(`projection-remote.js:233`), which is the existing precedent for "an honest
sentence about whether the room can see you yet", rendered in the foot band.

---

## 5. The free wins

No new dependencies, no approvals, and worth taking before any of the above so
that the measurements taken for Octane are taken against an honestly configured
PHP.

**`opcache.validate_timestamps=0`.** `Dockerfile.prod:114` copies
`php.ini-production`, which ships `validate_timestamps=1` with `revalidate_freq=2`.
In an immutable container image nothing on disk can change, so every included
file is `stat`ed on a two-second cycle for an answer that is known in advance.
Turning it off is free and safe *because* the image is immutable — and the same
immutability is why it would be wrong in `Dockerfile.dev`.

**`opcache.max_accelerated_files`.** The default is around 10,000 against 14,821
PHP files in `vendor`, `app`, `bootstrap`, `config` and `routes`. Not all are
loaded, but the margin is thin enough that the tail of the codebase risks being
recompiled per request. 20,000 costs a few megabytes of shared memory.

**`opcache.interned_strings_buffer`.** The default 8MB is low for a framework of
this size; 16MB is the usual recommendation and costs 8MB once per container.

**`opcache.preload` is deliberately *not* on this list.** It would be a real win
under mod_php, and it is redundant the moment Octane lands, since a warm worker
has already linked everything preload would have linked. Doing it now would be
work thrown away in a fortnight.

**The conditional read.** The client already knows its `version`
(`projection-remote.js:214`, `appliedVersion`). Send it; answer `304` with no
body when it has not moved. Worth doing on its own, before Octane and before the
long-poll, because it is the same wire protocol the long-poll needs — the version
travels up, and an unchanged state answers with nothing. It is section 3 minus
the waiting, and it takes Postgres out of the quiet path immediately.

**The poll routes come off the session.** They live in `routes/web.php:450-469`,
so every poll runs the `web` group and does a Redis session read *and* a write —
Laravel saves the session unconditionally, so the careful `X-Requested-With`
header in `projection-follow.js:148` stops the session being *dirtied* but not
being *written*. All of that is paid to satisfy `auth`. A token guard bound to
`device_pairing_id`, which the screen already carries, makes these routes
stateless: no session, no cookie decryption, no `ShareErrorsFromSession`.

This is the largest of the free wins and also the most invasive, because it
changes how these endpoints authenticate, and the 404-not-403 rule
(`PresentationStateController::allow()`) must survive it exactly. It should be
done on its own, with its own tests, and not folded into another step.

---

## Build order

Each step is shippable on its own and each is measurable before the next begins.

1. **Measure first.** A wall-clock timing header on `screens.state` in
   production, for one Sunday. Everything below is justified by a number, and
   right now there is no number — only a query count. If a poll is 15ms there is
   more runway than this document assumes; if it is 80ms, Octane pays for itself
   the week it lands.
2. **The opcache settings.** One `RUN` line in `Dockerfile.prod`. Re-measure.
3. **`redis-cache`, and memcached out.** Self-contained, reversible, and it
   clears the deck for everything that wants Redis later.
4. **The conditional read.** *Done, as a weak ETag rather than a version — see
   above.* An unchanged read answers an empty `304`. It saves the wire, not the
   queries; an unchanged read costing no Postgres at all waits on a version that
   can cover the screens' liveness.
5. **The ping.** Independent of all of the above and worth having regardless of
   which of them happen — it is the thing that makes the offline compromise
   workable in the field.
6. **The poll routes off the session.** Its own step, its own tests.
7. **Octane.** After the greps in section 2, and behind a dependency approval.
   The suite runs green on the Octane runtime before the image changes.
8. **Push, not a long-poll.** The idle timeouts are measured and are not the
   problem; the worker model is. Mercure or Reverb, behind a dependency
   approval, with the poller kept as the fallback.

New strings land in `lang/hu.json` as they are written, not afterwards.

## Testing

`ProjectionLoadTest` is the right home for most of this and its own preamble
already says what these numbers are: *budgets rather than observations, allowed
to be argued down and not to drift up.* Additions to it:

- The unchanged read answers `304` and costs zero queries.
- A read whose version has moved answers `200` and stays inside the four-query
  budget.
- The `304` path still refuses a screen belonging to somebody else with a 404,
  before it can be told whether anything changed — a not-modified answer must not
  become an oracle for the existence of a row.

For the long-poll, in `tests/Unit/projection-poll.test.mjs` beside the existing
poller cases: a timed-out wait does not increase the backoff; two failed waits
fall back to short polling; a successful short poll returns to waiting.

For the ping: that a failed read leaves the last value visible and marks it
stale rather than blanking it, and that the measurement is the request's own
round trip and not the hold.

For Octane, the whole suite against the Octane runtime is the test, plus one
case that would fail if the container leaked state between requests: two
sequential requests as two different users, asserting the second sees its own
genre and not the first's.
