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
  revision, drawn_revision
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

**The deck changes under the presentation, and rehearsal is when it changes
most.** The half hour before the service is exactly when a stanza is retyped, a
row moved, a wrong note fixed in the score itself — and both clients engraved
their deck once at load and are told nothing. Following the laptop therefore
means two different things, and the state carries a number for each: `version`
moves when someone presses space, `revision` moves when someone saves an edit. A
client that sees a new `version` swaps a slide; a client that sees a new
`revision` reads the payload again and engraves it again. Both ride the poll
that is already running, so nothing new is asked of the network.

**`revision` is read off the rows rather than bumped by hand.** It is the newest
of three timestamps: the projection's own `updated_at`, the newest `updated_at`
among its rows, and the newest among the scores those rows name — one joined
query, and the same trick the application already dates an engraving with
(`Score::incipitUrl` hangs `?v=updated_at->timestamp` off every one of them).
Nothing has to remember to raise it: retyping a stanza, reordering the deck,
changing the ratio and correcting the score all land in one of those three
columns, and a row that changes without touching its parent — `ProjectionSlide`
has no `$touches` today — is still caught, which is why it does not need one.

Entitlement is deliberately outside the fingerprint. A loan recalled between
Thursday and Sunday is not an edit to the deck, and it needs no bump to take
effect: every payload read resolves it afresh through
`ProjectionRenderPayload::for`, so the next re-engraving for any reason drops
what may no longer be read. What it must never do is take the picture off the
wall by itself in the middle of a Mass.

**Re-engraving never takes the picture down.** The new deck is drawn into a
second array in the background and swapped in when it is finished, the way
`applyUpdate` and the presenter's reload button already do it — that seam exists,
and this feature only gives it a reason to fire without a hand on the laptop. The
slide address and `blanked` survive the swap. If the read or the engraving fails,
the old deck simply stays and the client keeps its old revision, so the next
change tries again; nothing is drawn over the deck to say so.

**The address is resolved against the new deck forgivingly.** If the row still
exists, `slide_index` is clamped to the number of slides it now comes to; if it
is gone, the service lands on the first slide of the next row that survived, and
on the last slide of the deck if there is none. A today-only reveal keyed to a
row that no longer exists is dropped with it.

**Each client reports the revision it has actually drawn, not the one it has
heard of.** The presenter writes `drawn_revision` with its heartbeat, and the
remote compares it with `revision`. That is the honest answer to *is the wall
showing my edit yet*: the phone can say the wall is still on the previous deck
and that it is catching up, instead of implying the room already sees what the
phone sees. It also costs nothing to display — one comparison of two strings the
poll already carries.

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
2. `revision` in the state answer, a payload endpoint to re-read from, and the
   presenter re-engraving on its own when the deck moves under it — the reload
   button stays as the manual override it is. Two presenter tabs now also follow
   an edit made in a third.
3. `/remote`: the list, then the control page — previous, next, blank, jump,
   current and next slide, plan context, and whether the wall has caught up with
   the last edit.
4. Verses revealed for today only.
5. Later: the websocket transport, and several screens under one controller.

## Testing

Pest feature tests for the state round trip and for `version` never going
backwards; a read carrying a stale version ignored; another user's presentation
answering 404; a loan revoked underneath ending the payload; a slide address that
no longer exists after an edit resolving to something sane; the `live` scope
ageing out on `last_seen_at`. For following the deck: `revision` moving when the
projection, a row or a score behind a row is saved, and standing still when
nothing was; an address resolved across a deleted row and across a row that lost
slides; a today-only reveal pruned with the row it named; `drawn_revision`
reported behind `revision` until the presenter has finished engraving. A browser test that drives the deck from the
keyboard with both endpoints failing, so that the rule about the wall is enforced
by something other than good intentions.

## Since: the phone aims at a screen, not at a deck

The first pass shipped and is right about everything below the seam — the row,
the addressing, the two endpoints, the poll that doubles as a heartbeat. What it
got wrong is the thing the phone is pointed at.

`ProjectionPresenter::mount()` derives the `Presentation` from the URL the laptop
loaded, and the blade then bakes that row's id into `stateUrl` and `payloadUrl`
as constants. Both pages are bound to one deck for their whole life. Nothing in
the application means *the wall*, so the remote has to guess which deck the room
is seeing, and `ProjectionRemoteList` guesses by taking the newest live row —
which is why `/remote` silently enters a deck nobody chose, why a laptop that has
not started anything yet leaves the phone with an empty page instead of an
answer, and why the back arrow on the control page returns to a list that
immediately redirects into the deck it just left.

The workaround for each of those separately is a flag: a query string that
suppresses the redirect, an empty state, a stop button. Three patches around one
missing noun.

### Decisions

**A `Screen` is one browser that is showing the room something.** It is the noun
the first pass left out, and it holds exactly one interesting column: which
presentation it is currently showing, nullable, because a screen that is showing
nothing is the normal state before the service and the one the phone most needs
to be able to say out loud.

```
screens
  id, user_id, device_pairing_id (nullable)
  session_id, user_agent
  presentation_id (nullable)
  last_seen_at
```

Live means `last_seen_at` inside the last few minutes, after `Presentation` and
for the same reason — a closed tab goes stale rather than depending on an event
browsers do not reliably give.

**There is nothing to pair, and the button is not a pairing button.** Both
devices already hold a session for the same person; that is what the QR sign-in
was for, and it is why the remote needed no code read across the room in the
first pass. What the phone is missing is not permission but an address: *which*
browser is the wall. So claiming a screen is not a ceremony between two devices,
it is one device saying "I am the one facing the room" — and the honest way to
say that is to open the page that only a wall would open. Opening `/present`
claims the screen for that session; nothing is typed, nothing is scanned, and a
laptop signed in with a password rather than a QR code works identically.

The button that is genuinely needed is therefore a way *to* that page, not a way
to pair: a sidebar entry next to the remote's, so the parish laptop reaches the
waiting screen without anyone remembering a URL. `device_pairing_id` is recorded
where there is one, so that revoking a borrowed screen from the phone — which
already ends the session — takes the screen with it rather than leaving a live
row pointing at a laptop that has been signed out.

**The laptop opens a screen; the deck arrives afterwards.** `/present` shows a
waiting state naming the screen, and then follows its own pointer: the poll it
already runs answers which presentation it is showing, and it draws whatever that
is. `/projections/{projection}/present` stays exactly as it is and keeps working
for someone driving the laptop directly — it simply also sets this browser's
screen pointer on the way in. Starting a deck at the laptop and starting it from
the phone become the same write, which is the only reason the two-screen setup
later needs no new concept.

**A screen changing decks goes black, and black is a state of its own.** The new
deck has to be engraved before it can be shown, and that is seconds, not frames.
The wall shows black while it engraves — deliberately, because a room watching
the previous hymn linger while the next one is prepared is worse than a room
watching nothing for two seconds. It is not the cantor's blank: `blanked` is an
instruction and this is a condition, they clear on different events, and the
phone must be able to say *the screen is preparing* rather than implying the
cantor pressed something. The remote already has the vocabulary for exactly this
distinction in `drawn_revision` against `revision`, and this is the same question
one level up — which deck is drawn, rather than which version of it.

**The remote is bound to the screen, and so it can be left.** `/remote` becomes
the screen — the one live screen entered without asking, a picker when there are
two, and when there are none the useful sentence the current page cannot say,
that no screen is waiting and the laptop has not been started yet. The control
page then never traps, because going back to the deck list is ordinary navigation
rather than a redirect into the row the list just came from. When the laptop
starts a deck, the phone's poll sees the screen's pointer change and follows it
in; that is the same poll, one field wider.

**Leaving the remote and clearing the wall are different actions.** Stopping mid
service by accident is a far worse failure than a stale slide lingering after
everyone has gone home, so the ordinary back gesture means only that this phone
is done driving, and the wall keeps what it has. Clearing the screen — pointer to
null, wall back to waiting — is a deliberate, separately worded action, and it is
also what ends the presentation.

**Pointing a screen at a deck belongs wherever decks are listed.** It is one
write, and the remote is not the only client that will want it: the two-screen
laptop is this feature's second caller, where one screen shows the deck and the
other browses, edits scores and starts the next one. That second screen is not a
new mechanism — it is a browser that can aim a screen and does not show a deck,
which is precisely what the phone is. Building the action on the projection and
plan-document pages rather than only inside the remote is what makes the later
setup a layout question instead of a design question.

**The presentation stays what it was.** A screen points at a presentation;
`Presentation::resumeFor` still joins or starts one; the address, the version,
the revision, the reveals and both endpoints are untouched. Everything the first
pass established survives, which is the test of whether the missing noun was
really missing.

**The state client becomes rebuildable, and that is the real refactor.**
`stateUrl` and `payloadUrl` stop being constants written at render time and
become derived from whichever presentation the screen currently points at, on
both pages. `stateClient` in `projection-follow.js` closes over its config today;
it needs to be re-made when the pointer moves, with the old poll stopped before
the new one starts, or two clients race each other writing where the service is.
The rule the first pass would not trade stands unchanged: a failed read or write
costs the remote and nothing else, and a screen that cannot reach the server goes
on showing what it is showing.

### Build order

1. Table, model, policy. `/present` claims a screen for the session and shows the
   waiting state; the sidebar reaches it; `/projections/{projection}/present` sets
   the pointer on the way in. Nothing else changes yet.
2. A screen's state in the poll, and the presenter rebuilding its client and
   re-engraving when the pointer moves — black while it does. Two laptops, or a
   laptop and a keyboard, can now hand a screen from deck to deck.
3. `/remote` rebound to the screen: waiting state, picker, following the pointer
   in, and a back gesture that leaves without redirecting.
4. Starting and switching a deck from the phone, then the same action on the
   projection and plan-document pages.
5. Clearing the screen, as the deliberate action that also ends the presentation.
6. Later, unchanged from the first pass: the websocket transport under the same
   endpoints, and the two-screen laptop, which by then is layout.

New strings land in `lang/hu.json` as they are written, not afterwards.

### Testing

Pest feature tests for claiming a screen from a session and re-claiming it on
reload rather than growing a second row; a screen aged out by `last_seen_at`; a
revoked `DevicePairing` taking its screen with it; another user's screen
answering 404 in the shape the state endpoints already use; pointing a screen at
a deck the user may not view refused; the pointer surviving a presentation that
ends and reading as cleared afterwards. For the remote: no live screen answering
the waiting state rather than an empty list, one live screen entered without
asking, two offering a choice, and the back gesture leaving the pointer alone
where clearing it changes both the screen and the presentation. A browser test
for the swap itself — the wall black while the next deck engraves, then showing
it, with the phone reporting the screen as preparing throughout.
