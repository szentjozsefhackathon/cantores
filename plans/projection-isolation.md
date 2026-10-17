# Keeping the Show Up When the Rest of the Site Is Not

## Context

Projection is the one part of the site that must not go down: a wall in a
church, a cantor at the organ with a phone, and a Mass that does not wait for a
server to recover. Today it shares everything with every other page. There is
one `app` container, `mem_limit: 1024m`, eight FrankenPHP workers, one Mercure
hub in the same process. A public page that is slow, heavy or flooded takes the
workers projection needs. That includes the psalm-tone setter, the music
database, score previews and a crawler. If the whole container is killed for
running out of memory, the show goes with it.

The psalm-tone setter is now capped (`PsalmTones::MAX_FORMULA_LENGTH`,
`PsalmToneSetter::MAX_PART_SYLLABLES`), and client IPs are now real
(`App\Support\CloudflareNetwork`). Neither of those is isolation. This plan
gives projection its own container, so that overload on the public side can
reach projection only through what the two still share, and that shared part is
kept small and named.

### What is not the goal

- Throttling the public pages. Labs and parishes share IPs, and Cloudflare
  already sees more than we do. If abuse ever shows up, its rules are the place.
- Surviving Cloudflare, the host or Postgres going down. One machine stays one
  machine.

## What a running show asks the server for

Mapped from `resources/js/projection-follow.js`, the presenter and remote views,
and `routes/web.php:445–590`.

**Every second or so, from the wall and the phone:**

| Request | Notes |
|---|---|
| `GET /show/state` | `not-modified`, usually a 304 |
| `GET /presentations/{p}/state` | where the running deck is |
| `POST /presentations/{p}/state` | an advance |
| `POST /screens/{s}/ack` | the wall's report, every ~10 s |
| `POST /show/stream` | the Mercure subscriber token, again when the hub turns a device away |
| `GET /.well-known/mercure` | the push channel; held in Go by Caddy, no PHP worker |

**When something changes:**
- `GET /presentations/{p}/payload`, `POST /presentations/{p}/resync`,
  `POST /show/state` (another deck), `POST /screens/{s}/fit`.
- Images: `/projections/{p}/score-page/…` for scanned decks, and
  `/scores/{score}/incipit`. The incipit route is the music database's own route,
  not projection's.
- From the phone: `/projections/{p}/score-toggle`, `music-search`,
  `added-musics`, `move`.
- **Mercure publish:** every write that moves a show POSTs to
  `services.mercure.publish_url` (`ShowStream::publish()`). That includes deck
  edits made in the projection editor, through `ShowStreamObserver`, which runs
  on the public side.

**Livewire round trips:** these all go to `POST /livewire-{hash}/update`, the same
path for every component.
- Remote: the `projection.show-status` refresh when the walls change, and the
  lazy `music-search` when "Add music" is opened.
- Presenter: the `reload` action and the screen-settings modal.

**Page loads and setup:** `/present`, `/remote`, `/remote/decks`,
`/projections/{p}/present`, login, QR pairing (`/qr`, `/qr/{token}`), and
`/livewire-{hash}/livewire.js`, which PHP serves. `/build/*` is static and served
by Caddy without a worker.

Slides are engraved in the browser from the payload. There is no server-side
engraving during a show.

## Design

### Two services, one image

`app` stays as it is for everything public. A new `projection` service runs the
same `creshu-app-prod` image with the same `.env.prod`, volumes and networks. It
differs in four ways:

- its own `OCTANE_WORKERS` (start at 4) and `mem_limit` (start at 512m);
- the only Mercure hub (see below);
- a higher CPU weight than `app`;
- its own healthcheck, and its own place in `deploy-prod.sh`.

Same image, same code: the split is entirely routing. Any route still works on
either container, which is what makes a misrouted request harmless and the
change reversible by deleting one router.

### Traefik routers

A second router on the `projection` service, at a priority above `myapp`'s 10:

```
Host(`${APP_DOMAIN}`) && (
    PathPrefix(`/show/`) || PathPrefix(`/screens/`) || PathPrefix(`/presentations/`)
 || PathRegexp(`^/projections/[0-9]+/(present|payload|score-page|score-toggle|music-search|added-musics|move)`)
 || Path(`/present`) || PathPrefix(`/remote`)
 || PathPrefix(`/.well-known/mercure`)
)
```

It gets the same `hsts` and `compress` middlewares. Do not apply `compress` to
the hub: an SSE stream must not be buffered, so give `/.well-known/mercure` a
router of its own without it.

`/projections/{p}/edit` is deliberately left on `app`. Editing a deck is
preparation, not the show. An edit still reaches the walls through the publish
below.

`/scores/{score}/incipit` stays on `app`. It is shared with the music database,
and a missing thumbnail on the remote does not stop a Mass. Revisit this if it
turns out to matter.

### Livewire

The update path cannot be told apart by component, so do two things:

1. **Convert the `projection.show-status` refresh to plain JSON.** The remote
   already has `/show/state`, so let the status light read the screens from that
   answer instead of a `$wire.$refresh()`. The Livewire left on the remote and
   presenter after that (music search, settings modal, reload) is not needed to
   keep a show running.
2. **Optional, after 1:** route `POST /livewire-[0-9a-f]+/update` to
   `projection` when `Referer` starts with `https://${APP_DOMAIN}/present` or
   `/remote` (`HeaderRegexp`). Same-origin fetches send the full referrer under
   the default policy. It is fragile against a future `Referrer-Policy` change,
   which is why it is optional and not the mechanism the show relies on.

Route `/livewire-*/livewire.js` the same way, or leave it on `app`. It is
cached by the browser and Cloudflare, and a wall that has loaded once does not
ask again until it reloads.

### Mercure

The hub uses `transport local`: its subscriptions live in the memory of one
process. So exactly one container may run it, and it is `projection`.

- `docker/frankenphp/Caddyfile`: make the `mercure` block conditional, or give
  `app` a Caddyfile without it. If `app` keeps a hub nobody subscribes to, a
  publish sent there is lost silently.
- `MERCURE_PUBLISH_URL` on both services points at
  `http://projection:8000/.well-known/mercure` over the `internal` network.
  Publishing is already plain HTTP with a timeout, and a failed publish already
  degrades to polling (`ShowStream::publish()` logs and carries on). So a dead
  `app` cannot hurt the hub, and a dead hub cannot hurt a write.

### CPU and memory

Two containers on one host still share the CPU. Without weights, a flood on
`app` still slows `projection`, just without killing it.

- `projection`: `cpu_shares: 4096` (default is 1024), so under contention it
  gets four times `app`'s share.
- `app`: `cpus:` capped below the host's count, for example `cpus: 3` on a 4-core
  machine, so even an unweighted burst leaves a core free.
- Separate `mem_limit`s are the main win. An out-of-memory kill of `app` no
  longer touches the show.

Size `OCTANE_WORKERS` for `projection` from its traffic, not the site's. Two
devices per running service at ~2 requests a second each, at a few ms per
request, is far below one worker per parish. Four workers is generous. Measure a
worker's resident size after an hour, as the comment on `app` already says.

### What stays shared, and why that is acceptable

- **Postgres.** Projection's reads are four queries (`ProjectionLoadTest`) and
  mostly answered with 304. A heavy public query can still slow them. If that is
  ever seen, give the public side a `statement_timeout`. Separate databases are
  not proposed.
- **Redis** (sessions, cache, locks, and the rate limiter buckets). It is cheap
  per operation, but a single instance. If cache and sessions share it, a cache
  flood can evict a wall's session and sign it out mid-Mass. Check its
  `maxmemory` and eviction policy, and consider a separate Redis for cache so
  sessions are never evicted.
- **The `storage` volume.** Maintenance mode lives there (see Deploys).
- **Cloudflare and the origin.** See below.

### The origin firewall

Everything above is undone by a request that skips Cloudflare. Ports 80/443 are
published on the host, and the Origin CA certificate only stops clients that
check it. Allow 80/443 from Cloudflare's ranges only (`CloudflareNetwork::EDGE_RANGES`),
on the host firewall or the provider's, or turn on Authenticated Origin Pulls
and require the client certificate in Traefik. This is independent of the split
and could go first.

### Deploys

`deploy-prod.sh` today runs `artisan down`, stops `app`, migrates, and starts
`app` again. Every deploy is an outage for the show too, and maintenance mode is
shared through `storage`.

- Deploy `projection` separately from `app`, and never during the usual Mass
  hours without an override flag.
- Exempt the projection paths from maintenance mode with
  `$middleware->preventRequestsDuringMaintenance(except: [...])` in
  `bootstrap/app.php`, so `artisan down` for a public deploy no longer blanks
  the walls. Migrations that touch projection tables
  still need a real window. Say so in the migration's PR.
- Healthcheck `projection` on `/up` like `app`, and have the verification step
  check both.

## Steps

1. Origin firewall to Cloudflare's ranges. Independent, smallest, largest gain.
2. Show-status from JSON instead of a Livewire refresh. Code only, with a test.
3. `projection` service, Traefik routers, CPU and memory settings, Mercure moved,
   `MERCURE_PUBLISH_URL` repointed. Compose and Caddyfile only.
4. Maintenance exemption for projection paths, and `deploy-prod.sh` deploying
   the two separately.
5. Optional: the Livewire `Referer` router; incipits on `projection`.

## Verifying it

- Run a show on staging or a smoke deploy (`smoke-prod-image.sh`) with the wall
  and the phone. Then load `app` until its workers are saturated, for example
  `hey -c 50` against `/psalm-tones` and the music database. The advance-to-wall
  latency should not move.
- Kill `app` (`docker kill`). The show keeps advancing and the push keeps
  arriving.
- `docker compose exec app` with `stress-ng --vm` to force an out-of-memory
  kill of `app`. `projection` stays up.
- Check the Traefik access log or `whoami` that each mapped path lands on the
  intended container.

## Open questions

- Which hours does "never during Mass" mean in practice? Sunday mornings are
  obvious, but weekday Masses and evening vigils differ by parish.
- Is the incipit on the remote important enough to route it to `projection`?
- Should the score editor's publish (`ShowStreamObserver` on deck edits) be
  allowed to fail silently while `projection` is down? Today it does, and the
  walls catch up by polling.
