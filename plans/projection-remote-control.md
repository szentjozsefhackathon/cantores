# The Deck Driven From a Phone

## Context

The Sunday this is designed around, as it is actually done:

1. The parish laptop opens `cantores.hu`.
2. It is signed in with the QR code. The phone was signed in already, so after
   the scan both devices are the same person.
3. The laptop starts the projection and goes full screen. The room sees the deck.
4. The cantor takes control of it from the phone, and does not touch the laptop
   again.

Steps 1 to 3 exist. Step 4 does not, and it is the one that matters during the
liturgy, because the person who knows when to advance is the person at the
organ and today advancing means a hand on a keyboard somewhere else in the
building.

The workaround in the field is OpenLP's remote reached over a phone hotspot: the
phone becomes an access point, the parish laptop joins it, and the phone opens a
remote served by the laptop itself. That it is worth doing every week is the
evidence this feature is wanted. What it costs is a network built by hand before
every Mass, the cantor's data plan carrying the laptop's traffic, a hotspot
draining the one device that must survive the service, and the laptop pulled off
the parish network that it — unlike the phone — is actually allowed to use.

None of that applies here. Both devices already hold an authenticated session
with the same server, and the presenter cannot run without one: the laptop is
online by construction. The shared network has nothing left to do, so the remote
is an ordinary page of the site rather than an arrangement of radios.

## What is already in place

- `ProjectionPresenter` resolves `ProjectionRenderPayload` at mount and hands
  `geometry`, `entries` and `excluded` to Alpine. Nothing about the *running*
  deck is written down anywhere.
- `projection-presenter.js` engraves the whole deck once at load into finished
  SVGs and then swaps one DOM node per slide. Its entire live state is `index`
  and `blanked`; its entire control surface is `go(index)`.
- Every slide is engraved, the excluded ones included — `draw()` filters after
  the fact, `drawn.filter((slide) => !isExcluded(slide, this.excluded))`. Each
  slide already carries a stable identity: `{entryId, index}`, the row it came
  from and its place within that row (`projection-deck.js:74`).
- `DevicePairing` already records which phone signed which laptop in.

So the work is not to make a projection controllable from elsewhere — it very
nearly is — but to give two devices a place to agree on `{index, blanked}`.

## Decisions

**The running deck becomes a row: `Presentation`.** A `Projection` is the deck; a
`Presentation` is that deck being shown, on one screen, once. The row is the
authority on where the service has got to, and both devices are its clients.
Server-side rather than peer-to-peer because it then survives a phone that locked
itself, a tab that reloaded and a cantor who picks the remote up after the first
hymn — and because it is the seam a websocket transport slides under later
without disturbing anything above it.

Timestamps rather than a status enum, after `Loan` and `DevicePairing`:
`started_at`, `last_seen_at`, `ended_at`, with live meaning `ended_at` null and
`last_seen_at` inside the last few minutes.

```
presentations
  id, projection_id, user_id, device_pairing_id (nullable)
  entry_id, slide_index, blanked, version
  started_at, last_seen_at, ended_at
```

**The protocol carries a slide's address, never an array offset.** `go()` takes a
position in the *filtered* array, and the filter is `excluded` — so the moment a
verse is brought back mid-service every position after it moves. The row stores
`{entry_id, slide_index}`, the identity each slide already has, and each client
resolves it against its own array. Two clients that disagree about the filter
still land on the same slide.

**The wall never loses its picture.** The presenter applies a keystroke locally
first and reports it afterwards. A failed read or write is swallowed: no error
drawn over the deck, no blanking, no jump to the beginning. Losing the network
costs the remote and nothing else — the keyboard, and so the service, carries on.
This is the one rule here that may not be traded for anything, and it gets a test
that fails if someone removes it.

**A monotonic `version` orders reads.** Every write increments it; a client
ignores any state whose version does not exceed the last it applied. Without
that, a read answered just before the cantor pressed space arrives just after it
and sends the room back a slide. Writes themselves are last-one-wins, which is
right: there is one liturgy, and the people driving it can see each other.

**The hot path is a JSON endpoint, not a Livewire round trip.** The presenter's
stage is `wire:ignore`d and its docblock says nothing there writes, both so that
no component re-render can touch the picture mid-service. Polling the Livewire
component would give that up. Two narrow routes instead — read the state, write
the state — with Alpine talking to them, while Livewire goes on doing only what
it does at mount. Authorization is `view` on the projection plus ownership of the
row, in the shape `ProjectionScorePageController` already uses.

**Polling first, websockets later, behind the same endpoints.** There is no
broadcasting layer in the application at all — no `config/broadcasting.php`, no
Echo, no Reverb. A poll of about a second from both ends is comfortably enough
for slides and ships without new infrastructure in the deployment. When the
fan-out cases arrive — a second screen, phones in the pews — only the transport
changes; the row, the addressing and the endpoints stay.

**The remote is optimistic.** The phone moves its own view on the tap and
reconciles when the answer comes. A round trip is 20–50ms on a good connection
and occasionally far worse on a bad cell; the wall may lag a beat, but the remote
must never feel like it is thinking.

**The remote shows the deck, and what the deck is for.** It engraves the same
payload through the same `renderDeck`, so what the phone shows is what the wall
shows: the current slide, the next one, and a list to jump by. Around them it
shows what no display program's remote can, because no display program has it —
the slot the row stands in, the music's title and variation, how many slides the
row came to. Engraving on the phone is the one performance risk in this design;
if it proves too slow, the fallback is to label the deck and engrave only the
current slide and its successor.

**Authorization is finished before this feature starts.** Both devices are the
same person, so there is nothing to pair, no code to read across the room and no
token to mint — step 2 of the flow did all of it. The remote resolves its own
payload through `ProjectionRenderPayload::for($projection, Auth::user())`, so a
score that stopped being readable between Thursday and Sunday is no more visible
on the phone than it is on the wall.

**A verse brought back is today's deviation, not an edit of the deck.** Because
excluded slides are engraved and merely filtered, revealing one costs nothing at
render time — it is the feature that makes this a remote rather than a clicker.
But `excluded_slides` on the row is a deliberate arrangement, "the verses left
out today, kept in the deck for the Sunday that wants them". A cantor reacting to
a long procession is not rewriting that. So the reveal lives on the presentation
as an override for this service only, and the projection is left as its author
arranged it.

**Several screens on one controller is deliberately not modelled yet.** One
presenter page is one row; the poll doubles as its heartbeat; a closed tab goes
stale rather than depending on an event browsers do not reliably give. The remote
lists the user's live presentations, which will almost always be exactly one and
should then be entered without asking. Grouping rows under one controller wants a
concept above the row, and nothing here forecloses it.

## Build order

1. Table, model, policy, the two endpoints, and the presenter reading and writing
   `{entry_id, slide_index, blanked}`. Nothing visible changes — but two
   presenter tabs on one deck now follow each other, which is the whole mechanism
   under test.
2. `/remote`: the list, then the control page — previous, next, blank, jump,
   current and next slide, plan context.
3. Verses revealed for today only.
4. Later: the websocket transport, and several screens under one controller.

## Testing

Pest feature tests for the state round trip and for `version` never going
backwards; a read carrying a stale version ignored; another user's presentation
answering 404; a loan revoked underneath ending the payload; a slide address that
no longer exists after an edit resolving to something sane; the `live` scope
ageing out on `last_seen_at`. A browser test that drives the deck from the
keyboard with both endpoints failing, so that the rule about the wall is enforced
by something other than good intentions.
