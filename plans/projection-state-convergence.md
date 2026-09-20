# Projection State Convergence and Screen Acknowledgement (#44)

## Context

During a live projection, Black was pressed on one remote and its own preview
changed, but neither another remote nor the presenter changed. Next was then
pressed, and both other clients applied Black and Next together.

That sequence is possible in the protocol as built. A control write changes the
authoritative `presentations` row, then a Mercure message tells the person's
devices only that *something changed*. Each device answers that nudge with the
same `GET /show/state` it polls as a fallback. While the stream is open the
fallback is fifteen seconds, so one missed or delayed nudge can leave otherwise
healthy clients showing an older state until the next nudge arrives. They then
read the latest snapshot and appear to apply several controls at once.

The initiating remote is optimistic: it applies the press locally before the
write finishes. That responsiveness is right and stays. What is missing is a
distinction between three facts:

1. what the remote currently wants;
2. what the server has accepted as the show state;
3. what each presenter has actually applied.

Today only the first two exist. `drawn_revision` looks like an acknowledgement,
but acknowledges only which edition of the deck finished engraving. It says
nothing about slide position, Black, splash or today's reveals, and it is one
column on `presentations` even though several `Screen` rows can be live.

This plan makes the three facts explicit. The remote remains immediate, the
server remains the authority, the wall remains usable from its keyboard without
the network, and a green dot on the remote means that a named presenter has
reported applying the exact current server state.

## What is already in place

- `Presentation` is the authoritative snapshot for the person's show:
  `{entry_id, slide_index, blanked, splash, reveals, version}`.
- `version` orders reads. Clients ignore a state older than the newest one they
  have applied.
- Both presenter and remote optimistically update their local UI, then POST to
  `/presentations/{presentation}/state`.
- `ShowStreamObserver` publishes a private Mercure nudge after saves that change
  the show. The nudge carries no state; clients re-read `/show/state`.
- `poller()` keeps one read in flight, backs off on failure and polls every
  second without push or every fifteen seconds while push is open.
- The presenter POSTs every ten seconds. That request currently serves three
  unrelated purposes at once: presentation liveness, screen acknowledgement of
  `drawn_revision`, and writing the presenter's current address and splash.
- A presenter read marked with `?screen=1` is the `Screen` heartbeat.
- The remote already has a local blue press flash, a live-screen status line,
  and a warning when `revision !== drawnRevision`. None of those confirms a
  control state reached the wall.

## Failures this plan closes

**A lost nudge hides a successful command.** Mercure is an accelerator, not the
authority, but the fifteen-second fallback makes a lost acceleration visible in
the middle of a service.

**Black is not retried.** Address and splash travel in later writes, so a failed
Next is usually repeated accidentally by a later heartbeat or action. `blanked`
deliberately travels only with the B press, which prevents a stale client from
undoing Black but also means a failed B press is never sent again. The comments
currently promise a retry that the payload does not perform.

**A heartbeat can be mistaken for intent.** The presenter periodically writes
its address. If it has not heard a remote's newer address yet, that heartbeat can
put the old address back on the server and increment the version. Liveness and
acknowledgement must not change the desired show.

**Concurrent writes can share a version.** `applyState()` derives
`$this->version + 1` from the route-bound model without locking a freshly read
row. Two requests loaded at the same version may both publish the same next
version for different snapshots. A client that applied the first snapshot then
correctly ignores the second because its version is not greater.

**Gesture order is not request order.** Every press starts an independent fetch.
There is no command identity, source sequence, timeout or outgoing queue. A
slower earlier request can arrive after a later request and become the winner.

**One deck acknowledgement stands for every screen.** Two presenters overwrite
the same `presentations.drawn_revision`. The remote cannot say which wall is
ready, nor whether either wall applied the current control state.

## Protocol model

### Desired state: local and optimistic

The remote continues to react before the network:

- A press is applied immediately to an ordered local queue of pending changes.
- The preview is `canonical server state + pending local changes`.
- Incoming server state replaces the canonical base, then still-pending local
  changes are replayed over it. A poll answered before a press therefore cannot
  make the preview bounce backwards.
- Controls are never disabled while waiting for the wall. The delivery marker,
  not control latency, tells the user that the wall is behind.

The presenter uses the same small pending-command abstraction for local keyboard
actions. The difference remains that its picture is changed locally first and
never taken down because a request failed.

### Committed state: one atomic server version

`POST /presentations/{presentation}/state` becomes a command endpoint while
keeping its URL. A request has this shape:

```json
{
  "sourceId": "per-tab UUID",
  "sequence": 17,
  "changes": {
    "entryId": 42,
    "slideIndex": 3
  }
}
```

`sourceId` is generated once per tab and kept in `sessionStorage`. `sequence` is
strictly increasing for that source. The authenticated user and the existing
device cookie remain the identity and authorization; `sourceId` is ordering and
idempotency metadata, not a credential.

Changes are partial and action-shaped:

- Next, Previous and jump: address plus any splash transition;
- Black: `blanked` plus any splash transition;
- today's slide change: `reveals` plus the resulting address;
- ending and deck selection stay on their existing show endpoints.

Next must not repeat the remote's possibly stale Black value, and a heartbeat
must repeat neither. Partial changes retain the good part of the current design:
independent controls do not overwrite one another merely because a client has
not read the other change yet.

The server handles every command in one transaction:

1. authorize the presentation and lock its row with `lockForUpdate()`;
2. lock or create the `(presentation_id, source_id)` source row;
3. if `sequence <= last_sequence`, treat the command as a retry or stale
   delivery, change nothing and return the current canonical state;
4. validate the addressed entry against the freshly locked presentation;
5. apply only the named changes;
6. increment `version` exactly once if the canonical state changed;
7. store `last_sequence` and the version produced for that source;
8. commit, then publish the ordinary show nudge.

Every response contains the complete canonical state. A successful retry may
return a newer state than the original command produced; that is correct, since
the response answers what the show is now.

Cross-source conflicts remain last-server-arrival-wins. There is one liturgy and
two people at the wall can already see one another. What changes is that one
source's presses retain their order, duplicates are harmless, independent fields
do not clobber one another, and every accepted state has a unique version.

### Source sequencing without an event log

A small table keeps only the high-water mark for each live source:

```
presentation_sources
  id
  presentation_id  FK cascade
  source_id        UUID
  last_sequence    unsigned bigint
  applied_version  unsigned bigint
  timestamps

  unique (presentation_id, source_id)
```

This is not event sourcing. Past controls are not replayed and no permanent
command history is introduced. The presentation row remains the sole desired
state; a source row only rejects duplicate or late delivery and disappears with
the presentation.

### Applied state: one acknowledgement per screen

Each `screens` row gains:

```
applied_presentation_id  nullable FK presentations, null on delete
applied_version          unsigned bigint, default 0
drawn_revision           nullable string
applied_at               nullable timestamp
```

`presentations.drawn_revision` is removed after clients have moved to the new
answer. A deck revision belongs to what one wall drew, not to the show shared by
all walls.

The presenter receives its `screenId` in the existing page configuration. After
it has adopted a canonical state, updated the DOM and completed at least one
animation frame, it sends:

```json
POST /screens/{screen}/ack
{
  "presentationId": 12,
  "appliedVersion": 83,
  "drawnRevision": "2026-09-20T..."
}
```

The endpoint accepts only the screen belonging to the authenticated user whose
`device_id` is the current device. A phone cannot claim that a laptop rendered
anything.

This acknowledgement also refreshes `Screen.last_seen_at`. It is repeated every
ten seconds as the presenter heartbeat, even when nothing changed. It never
writes the presentation and never increments its version.

`appliedVersion` means that the presenter processed that immutable server
snapshot and committed its corresponding DOM state. It cannot prove that the
physical projector emitted the pixels; the browser has no such signal. The UI
uses language such as “screen updated”, not “projector verified”.

After engraving a changed payload, the presenter acknowledges only once both
conditions are true:

- the control state for `appliedVersion` has been applied;
- `drawnRevision` is the revision used by the deck now in the DOM.

If engraving fails, the old deck and old `drawnRevision` stay acknowledged and
the remote truthfully shows that the screen is behind.

## Reads and push

`GET /show/state` stays the single authoritative read. Each described screen
adds:

```json
{
  "id": 7,
  "label": "Parish laptop",
  "appliedPresentationId": 12,
  "appliedVersion": 83,
  "drawnRevision": "2026-09-20T...",
  "appliedAt": "2026-09-20T09:31:04Z"
}
```

Mercure stays a nudge rather than carrying state. Correctness must not depend on
receiving every event. Its data may include `{presentationId, version, kind}` for
diagnostics and to ignore obviously obsolete nudges, but every client still
confirms with `/show/state`.

The fallback while a stream is open moves from fifteen seconds to five. This is
still an 80% reduction from the original one-second polling and bounds an
undetected half-open stream to a service-appropriate interval. The existing
projection load measurements are rerun before settling the constant; five
seconds is the reliability target, not permission to exceed the rate limiter.

Every fetch gets an `AbortController` timeout. A dead request must release the
single-flight poller so a stream poke or fallback beat can try again. Reads and
command retries retain the current capped exponential backoff and jitter.

## Command delivery and retry

Commands from one source are sent serially in gesture order. The interface stays
optimistic while the queue works in the background.

- A request timeout or network failure leaves the command at the head of the
  queue and retries the same `(sourceId, sequence)`.
- Black and unblank therefore have the same delivery guarantee as Next.
- A response removes that command and advances to the next immediately.
- A newer local press may be rendered while an earlier command is retrying, but
  it is not delivered ahead of it.
- The existing half-second per-control press lock remains; it solves thumb
  bounce, not transport ordering.
- Pending commands are kept only for the life of the page. Reload discards them
  and starts from the canonical server state. Persisting offline controls across
  a browser restart is outside this issue.

The presenter follows the same queue for keyboard actions. This preserves the
non-negotiable rule that a keyboard works while offline: the wall changes
locally, the command remains pending, and reconnect attempts delivery without
putting an error over the picture.

## Remote status

The current preview continues to show the desired state immediately. A compact
dot beside each live screen tells whether that screen has caught up:

- **blue, pulsing — Sending:** one or more local commands are not yet accepted
  by the server;
- **amber — Waiting for screen:** the server accepted the desired state, but
  this screen has not acknowledged the current presentation version or deck
  revision;
- **green — Screen updated:** the remote has no pending command, its canonical
  state is the server state, and this screen reports both
  `appliedVersion === state.version` and
  `drawnRevision === state.revision` for this presentation;
- **grey — No live screen:** no presenter heartbeat is current;
- **red — Screen not responding:** a live-listed screen remains behind beyond a
  short threshold or its `appliedAt` has become stale.

With several screens, each gets its own dot. There is no ambiguous global green.
The preview's main status may be green only when every offered live screen is
green; the screen list remains the detailed answer.

If another remote or the presenter issues a newer command, this remote adopts
the newer canonical state. Green always describes the state now shown in the
preview, not an earlier command this tab happened to send.

The existing “screen is still catching up” warning is replaced by the per-screen
status. Deck preparation remains a separate message because a newly selected
deck is intentionally black while engraving and is not the same failure as a
slide waiting for delivery.

## Retry screen update

When a screen stays amber or red, its status exposes **Retry screen update**.
That action POSTs a rate-limited resync request for the presentation. It does not
change the state or increment `version`; it republishes the show nudge and pokes
this remote's own read immediately. The five-second fallback remains the final
safety net if the hub is unavailable.

One automatic resync is attempted when a newly committed command has not been
acknowledged after a short grace period. Further attempts are manual and
rate-limited, so a disconnected projector cannot create a publish loop.

## State transitions

For one ordinary Next press:

1. Remote advances its preview and shows blue.
2. The ordered command queue POSTs `{sourceId, sequence, changes: address}`.
3. The server locks the presentation, commits version `N + 1`, and responds.
4. Remote stores the canonical response and shows amber for each screen behind.
5. Mercure nudges the presenter; a missed nudge is bounded by fallback polling.
6. Presenter reads `N + 1`, swaps the slide locally, then acknowledges
   `appliedVersion: N + 1`.
7. The acknowledgement publishes another nudge.
8. Remote reads the screen status and turns that screen's dot green.

Black follows exactly the same sequence. It is no longer a special fire-and-
forget field whose only evidence is the initiating phone's preview.

## Rejected alternatives

- **Wait for the presenter before changing the remote.** This makes a bad mobile
  connection part of every button press. Desired and applied state are shown
  separately instead.
- **Treat the POST response as the green acknowledgement.** It proves only that
  the server committed the request, not that any presenter fetched or rendered
  it.
- **Use `drawn_revision` as the acknowledgement.** It describes deck content,
  not position or Black, and one value cannot describe several screens.
- **Put the full state in Mercure.** Screen liveness, device identity and fit are
  still per-reader facts; an authoritative GET is needed anyway. Keeping push as
  a nudge also keeps polling a valid fallback.
- **Send the complete remote snapshot on every action.** A stale Next would be
  able to undo Black from another device. Commands remain partial by dimension.
- **Keep presenter state in the heartbeat.** A heartbeat is evidence of life,
  not user intent. Local presenter controls use explicit commands instead.
- **Build a permanent event-sourced presentation log.** Issue #44 needs ordered,
  idempotent delivery and per-screen acknowledgement, not historical replay. A
  per-source high-water mark is enough.
- **Make green mean all physical equipment is working.** The browser can confirm
  its rendered state but cannot inspect the projector or the room.

## Files touched

| File | Change |
|---|---|
| `database/migrations/*_create_presentation_sources_table.php` | per-source sequence high-water marks |
| `database/migrations/*_add_applied_state_to_screens.php` | per-screen presentation/version/revision acknowledgement; remove presentation-level `drawn_revision` in a later compatibility migration |
| `app/Models/Presentation.php` | move state mutation behind the atomic command service; stop accepting heartbeat writes |
| `app/Models/Screen.php` | acknowledged presentation/version/revision and heartbeat helpers |
| `app/Models/PresentationSource.php` + factory | new source high-water model |
| `app/Services/PresentationCommand.php` | transaction, row locks, idempotency, partial changes and unique version assignment |
| `app/Services/PresentationState.php` | canonical state only; no global screen acknowledgement |
| `app/Services/ShowState.php` | include each screen's applied state |
| `app/Services/ShowStream.php` | resync nudge and optional diagnostic event data |
| `app/Http/Requests/PresentationStateRequest.php` | require source/sequence and validate `changes` |
| `app/Http/Requests/ScreenAcknowledgementRequest.php` | validate per-screen acknowledgement |
| `app/Http/Controllers/PresentationStateController.php` | delegate writes to `PresentationCommand` |
| `app/Http/Controllers/ScreenAcknowledgementController.php` | acknowledge only this device's screen |
| `app/Http/Controllers/PresentationResyncController.php` | rate-limited republish without state mutation |
| `resources/js/projection-follow.js` | timed requests, ordered command client, five-second pushed fallback |
| `resources/js/projection-presenter.js` | explicit commands for keys; DOM/frame acknowledgement and acknowledgement heartbeat |
| `resources/js/projection-remote.js` | canonical-plus-pending state, ordered delivery, per-screen sync status and resync |
| `resources/views/livewire/pages/projection-presenter.blade.php` | pass `screenId` and acknowledgement URL |
| `resources/views/livewire/pages/projection-remote.blade.php` | per-screen dots, status text and retry action |
| `resources/views/livewire/projection/show-status.blade.php` | render or host the detailed per-screen state without polling Livewire independently |
| `routes/web.php` | screen acknowledgement and presentation resync routes |
| `lang/hu.json` | status and retry strings |

The exact migrations are split so deployment can be compatible with old browser
tabs: add the new acknowledgement fields and serve both answer shapes first;
remove `presentations.drawn_revision` only after the new presenter bundle is in
production.

## Build order

1. Add `presentation_sources`, the screen acknowledgement fields and model
   helpers. Keep the existing response fields temporarily.
2. Add `PresentationCommand` and move state writes into its locked transaction.
   Accept the old request shape during this deployment, assigning it a legacy
   source, so an already-open presenter is not broken by the release.
3. Add the ordered JS command client with request timeouts; move remote controls
   and presenter keyboard actions onto it. Black retry and same-source ordering
   become effective here.
4. Add the screen acknowledgement endpoint and change the presenter's periodic
   report into an acknowledgement-only heartbeat. It no longer writes address,
   splash or Black.
5. Include per-screen applied state in `/show/state`; add the remote's
   canonical-plus-pending reconciliation and status dots.
6. Add resync and reduce the pushed fallback after measuring the affected show
   read under expected concurrency.
7. Remove the legacy request shape and presentation-level `drawn_revision` once
   the compatibility window has passed.

Steps 1–4 fix convergence before the green dot claims it. The UI is not shipped
against an acknowledgement that can still be invalidated by an old heartbeat.

## Verification

Feature coverage:

- A command increments `version` once and returns the complete canonical state.
- Retrying the same `(sourceId, sequence)` changes nothing and returns the
  current state.
- Sequence 12 arriving before delayed sequence 11 leaves 12 authoritative; 11
  is rejected as stale and does not increment the version.
- Two sources changing address and Black concurrently produce two distinct
  versions and a state containing both partial changes in commit order.
- A screen acknowledgement updates only its own screen and never changes the
  presentation version or state.
- A screen cannot acknowledge another device's `Screen` row.
- Two screens may acknowledge different versions and revisions independently;
  one cannot make the other appear green.
- A stale acknowledgement for the previous presentation does not make a screen
  green for the current one.
- Resync publishes a nudge without changing the presentation version and is
  throttled.
- Compatibility requests from an already-open old client remain safe during the
  rollout window.

JavaScript coverage:

- The preview changes immediately while the command promise is unresolved.
- Commands from one source are delivered in gesture order even when the first
  request is slow.
- A timed-out Black command retries with the same sequence; Next waits behind it
  and both eventually converge.
- An older poll response replaces the canonical base but cannot erase a pending
  optimistic change.
- A newer command from another source is adopted after local pending commands
  have settled.
- Presenter heartbeats contain acknowledgement fields only and cannot restore an
  old address or Black value.
- The presenter acknowledges only after state application and a rendered frame;
  a failed re-engraving keeps the old revision acknowledged.
- Blue means pending server acceptance, amber means committed but unacknowledged,
  green requires the exact presentation/version/revision, grey means no live
  screen and red means stale.
- A stream nudge pokes the single-flight poller, a missed nudge is recovered by
  the fallback, and a hung fetch is aborted so the next beat can run.

Suggested focused commands as the phases land:

```text
php artisan test --compact tests/Feature/PresentationStateTest.php
php artisan test --compact tests/Feature/ScreenStateTest.php
php artisan test --compact tests/Feature/ShowStreamTest.php
php artisan test --compact tests/Feature/ProjectionRemoteTest.php
php artisan test --compact tests/Feature/ProjectionPresenterTest.php
node --test tests/Unit/projection-poll.test.mjs
node --test tests/Unit/projection-remote.test.mjs
node --test tests/Unit/projection-presenter.test.mjs
```

Manual failure test: open one presenter and two remotes, throttle and interrupt
network traffic in each direction independently, then press Black and Next on
one remote. That remote must react immediately. The other remote must show the
server's canonical state. The presenter must never be moved backwards by its
heartbeat. The status must stay blue or amber until the presenter applies the
state, turn green only after its acknowledgement, and recover through retry or
fallback when one Mercure message is deliberately dropped.

## Observability and rollout

Log command source, sequence, old/new version and changed field names without
logging deck content. Log acknowledgements with screen, presentation and applied
version. These records make “the button did nothing” distinguishable as:

- command never reached the server;
- command committed but its nudge failed;
- presenter fetched but did not acknowledge;
- presenter acknowledged a different version;
- screen heartbeat stopped.

Add counters for command retries, stale/duplicate sequences, Mercure publish
failures, acknowledgement lag and resync requests. The remote UI does not expose
raw versions, but the values belong in browser debug logs so a field report can
be correlated with the server.

Roll out additively: schema and dual response, command protocol, screen
acknowledgement, status UI, then old-field removal. A deployment must not make an
already-open wall stop responding in the middle of a service.
