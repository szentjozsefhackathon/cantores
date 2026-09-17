# One Show per Person

## Context

The cantor's Sunday, as it should work:

1. The parish laptop is signed in as me and opens a deck. The wall shows it.
2. My phone, and any other device I am signed in on, follows that same deck and
   can drive it: next, blank, reveal a verse.
3. From the pews, playing guitar, I pick a different deck on the phone. The wall
   switches to it.
4. At 10 I finish. At 11 the other cantor signs in on the same laptop with their
   own account. From then on the wall is theirs, and nothing I do on my phone
   can reach it.

The code does not work this way. It lets one person run several shows at once:

- `screens.presentation_id` is a pointer on each device. Two of my devices can
  show two different decks, each with its own position.
- `Presentation::resumeFor()` finds a presentation by (projection, user) and
  ignores the screen. Two screens on the *same* deck mirror each other, and two
  screens on *different* decks do not. Whether devices stay in sync depends on
  which deck they are showing.
- Pressing Present on the phone points the phone's own screen at the deck. The
  wall does not change. That is the bug that started this plan: the phone
  started a show of its own.
- Because there can be several shows, the remote has to ask which screen
  (`/remote/{screen}`, `ProjectionRemoteList`). Send to screen shows a dropdown
  of screens. Device names and the not-a-screen flag were added mostly to make
  those pickers usable.
- Switching away from a deck does not end its presentation. It stays live until
  it goes stale, so switching back resumes where the deck was left.
- `PlanDocuments::currentPresentation()` already assumes there is only one
  (`->first()`), and its "take it off" action looks for every screen pointing at
  it.

`plans/projection-remote-control.md` left this open on purpose ("several screens
on one controller is deliberately not modelled yet"). The sections added to it
later built more screen-picking on top instead of settling the question. This
plan settles it.

## The model

- **Projection**: the deck. Content only. Unchanged.
- **Presentation**: one time a deck was put up. It holds the position, blank,
  splash and today's reveals. **A person has at most one current presentation.**
  That presentation is "the show", and every device of theirs follows it. Past
  presentations stay in the table and become the recents list.
- **Screen**: a device facing a room. It holds the device id, the owner, the
  heartbeat, the name and the fit (where the picture lands on that projector).
  **It no longer points at anything.** A screen always shows its owner's show.
- **Remote**: any page that reads and writes the show. It needs no screen id.

Whose show a screen displays comes from `screens.user_id`, which
`Screen::claimFor()` already rewrites when a different person opens `/present`
on the same device. That handles the shared parish laptop with no extra code:
at 11 the laptop becomes the other cantor's screen, and my show is no longer
shown anywhere.

## Decisions

**Current means not ended.** Every new presentation ends the previous one in
the same transaction. A partial unique index on `presentations (user_id) where
ended_at is null` (Postgres) guarantees there is never more than one. Staleness
works as it does now: a current presentation nobody has polled for
`STALE_MINUTES` counts as nothing (so a deck left up on Saturday does not come
back on Sunday), and it is ended when the next one starts.

**Putting a deck up starts it over, unless it is already up.** One method,
`Presentation::putUp(User $user, Projection $projection)`:

- If the current, live presentation is already this deck, return it. Reloading
  the wall, pressing Present twice, or picking the deck that is already on the
  phone changes nothing.
- Otherwise end the current one and create a new row at the start of the deck.
  Splash rules stay as they are: the title card if nothing was live, and
  straight to the deck if it replaces one mid-service
  (`Presentation::splashFor()` now reads the person, not a screen).

This replaces `resumeFor()` everywhere: the presenter mount, Send to screen, the
remote's deck list and `ScreenStateController`. Going back to a deck starts it
over, because the new row has a new id and every client already re-engraves
when the id changes.

**Present on any device changes the show.** `/projections/{id}/present` calls
`putUp()` and then shows the show, the same way `/present` does. No preview
mode. A phone that presses Present also becomes a screen, and that is harmless
now because it shows the same thing as the wall.

**The client protocol keeps its shape.** Today both clients poll
`/screens/{screen}/state`, and the answer nests the presentation's state along
with its `stateUrl` and `payloadUrl`. A change of `presentationId` makes a page
go black and engrave the new deck. All of that stays. Only the address changes:

- `GET /show/state`: the person's show (the same fields `ScreenState` returns
  today, minus `fit`) plus `screens`, the live screens as `{id, label, fit,
  isThisDevice}`. If the asking device is one of the person's screens, this read
  is its heartbeat.
- `POST /show/state {projectionId}`: put a deck up (`putUp`), or with `null`
  take it off (end the current presentation).
- `POST /screens/{screen}/fit`: the line-up nudge. It still belongs to the
  device, because every projector is hung differently.
- `/presentations/{id}/state` and `/presentations/{id}/payload` do not change.
  Position writes keep going to the presentation by id on purpose: a page that
  has not noticed a switch yet writes to the old, ended row and cannot move the
  new show.

The wall still needs its own fit. `/present` puts it in the page as it does now,
and the show answer gives it back from `screens` by `isThisDevice`.

**The remote is one page.** `/remote` is the control page and `/remote/decks`
chooses a deck. `ProjectionRemoteList` goes away. `/remote/{screen}` and
`/remote/{screen}/decks` redirect to the new addresses so bookmarks on phones
keep working. Where the list used to be, the remote shows a status line: "On:
Parish laptop" (the live screens other than this device, with their settings
cog), or "No screen connected", with the existing hint about opening the
projection screen on the laptop. Nothing is blocked while no screen is
connected: the show can be set up from the phone before the laptop is on.

**The fit panel is the only place a screen is chosen, and only when needed.** It
nudges the live screen other than this device. If there are two or more, a small
chooser appears inside the panel. That is rare (the laptop at home left open),
and it is the only choice left that really is about a device.

**Send to screen becomes Put on screen.** One button with no dropdown. It says
"On the screen" while this deck is the current show. The link that opens
`/present` in a new window stays for when no screen is live, because the
two-display laptop still needs a way to make its second window the wall.

**The not-a-screen flag stays, with less to do.** `DeviceName.offered = false`
keeps a device out of the status line and the fit target. It no longer takes
anything off the wall. Doing so would now end the show for every device, which
is not what "the laptop at home is not a screen" means.

**Recents come from the presentation rows.** On `/remote/decks`, and in the
current-show area of `PlanDocuments`: "Recently shown", the last few distinct
projections by `started_at`, above the full deck list. That covers the
adoration deck tried the day before and put back up the next day with one tap.
Nothing new is stored.

**`presentations.device_pairing_id` and `screens.presentation_id` are dropped.**
The first is recorded and never read. The second is the pointer this plan
removes.

## Rejected alternatives

- **The current presentation as a column on `users`.** It would be one more
  pointer to keep in step with `ended_at`. With the partial unique index,
  "the un-ended row" is already the pointer, and the database enforces it.
- **Resuming a deck where it was left when switching back.** Asked and
  answered: it starts over. Resuming would need one live row per deck, which is
  the multi-show model this plan removes.
- **A preview mode for Present on a phone.** Not now. A "screen device" role
  may come later, but it is a separate feature, and without it the rule stays
  simple: Present means put it up.

## Files touched

- Migration: drop `screens.presentation_id` and `presentations.device_pairing_id`,
  end all but the newest un-ended presentation per user, add the partial unique
  index.
- `app/Models/Presentation.php`: `putUp()`, `currentFor(User)`, `recentFor(User)`.
  Remove `resumeFor()` and `device_pairing_id`. `splashFor()` takes a user.
- `app/Models/Screen.php`: remove `presentation()`, `point()`, `showing()`.
- `app/Services/ScreenState.php` → `ShowState` (answer by user, plus `screens`).
- `app/Http/Controllers/ScreenStateController.php` → `ShowStateController`, and a
  small `ScreenFitController`. Update the requests to match.
- `app/Livewire/Pages/ProjectionPresenter.php`, `ProjectionRemote.php` and
  `ProjectionRemoteDecks.php`: no `Screen` route parameter. Delete
  `ProjectionRemoteList.php` and its view.
- `app/Livewire/Projection/SendToScreen.php` and its view: a single button.
- `app/Livewire/Projection/ScreenSettings.php`: stop calling `point(null)`.
- `app/Livewire/Pages/PlanDocuments.php`: `currentPresentation` uses
  `currentFor`, `removeFromScreen` ends it, and add recents.
  **Note:** its view and `tests/Feature/PlanDocumentsTest.php` have uncommitted
  work that must be kept.
- `resources/js/projection-follow.js`: `screenClient` → `showClient` (reads the
  show, `point`, and `adjust(screenId, fit)`).
- `resources/js/projection-presenter.js` and `projection-remote.js`: the new URLs,
  the status line, and the fit target.
- `routes/web.php`: the new routes and the redirects.
- `lang/hu.json`: new strings as they are written.

## Build order

1. Migration and the `Presentation` methods, with model tests. `resumeFor` stays
   for the moment and calls `putUp`.
2. `ShowState` and the `/show/state` endpoints next to the old ones, then the
   presenter and remote JS moved onto them. The screens now follow one show.
3. The remote reduced to `/remote`, with the status line, the fit target and
   the redirects. Delete `ProjectionRemoteList`.
4. Put on screen, `PlanDocuments` and `ScreenSettings`.
5. Recents.
6. Remove `/screens/{screen}/state`, `resumeFor`, `Screen::point()` and the
   dropped columns' leftovers.

## Verification

Pest feature tests:

- `putUp` of a new deck ends the previous presentation, and the new one starts
  at the beginning. `putUp` of the current deck returns the same row with its
  position kept.
- There is never more than one un-ended presentation per user, including when
  two `putUp` calls race (the index rejects the second, and it retries as a
  join).
- A deck put up from the phone is what the laptop's `/show/state` answers, and
  Present pressed on the phone changes the laptop's answer too.
- A stale current presentation counts as nothing, and the next deck put up
  opens on the title card.
- Shared laptop: user A's screen is claimed by user B. A's `/show/state` lists
  no screens, and a deck A puts up does not change B's answer.
- The fit is written only to the chosen screen, and only if it is the asking
  user's screen (404 otherwise).
- `/remote/{screen}` redirects to `/remote`.
- Recents list distinct projections, newest first, only the person's own.

The existing `ScreenStateTest`, `SendToScreenTest`, `ProjectionRemoteTest`,
`ProjectionPresenterTest`, `SplashScreenTest`, `ScreenNamingTest` and
`ProjectorFitTest` are rewritten to match, not deleted.

By hand: laptop on `/present`, and the phone opens `/remote` straight to the
controls. Pick a deck on the phone, and the wall switches. Press Present on the
phone for another deck, and the wall switches again. Sign the laptop in as a
second account, and the phone shows "No screen connected".
