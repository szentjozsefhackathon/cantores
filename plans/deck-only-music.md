# Music the Plan Does Not Know About (#36)

## Context

A plan is published a week ahead. The deck and the booklet are made from it on
Thursday. Then, one minute before Mass, the priest asks for a song after the
homily because it is somebody's birthday. There is no slot for it, and a music
added to the plan would be wrong: the plan is the published service, and this
is one occasion's change to it.

The deck can already hold things the plan does not: a screen of words is a row
with no score, written under a slot, under a music or at the top of the deck.
What it cannot hold is a *music*. A score picked outside the plan can get into
a row (`music_plan_slot_assignment_id` null), but nothing in the editor makes
one, and a lone score is not a music anyway. There are no other engravings to
offer, no music title to show or hide, no collection reference, and no group of
rows the arrows move together.

The issue settles two questions, and this plan takes both as given:

- **It belongs to the document, and it stays there.** A deck is made for one
  occasion, and the next occasion gets a copy. So a change made on the remote
  during the service is saved to the deck like any other edit. There is no third
  "what was actually sung" version next to the plan and the deck.
- **Booklets get it too.** The problem comes from decks, but the two editors are
  twins through `PlanOutline`/`PlanDocument`. If only one of them had this, the
  UX would drift apart.

## What is already in place

- `PlanOutline::for()` builds the tree out of `slot → music (assignment) →
  entry` plus loose entries. `flatten()` returns the order that sequences are
  written from. `moved()`, `insertIndex()` and `appendIndex()` all work on that
  tree.
- `PlanRenderPayload::headingsFor()` works out slot, music and reference lines
  from the assignment. For a scored row with no assignment it already prints the
  score's title, so that path exists and gets replaced here.
- `ProjectionScoreToggle` is the single write behind the editor's "+"/"×" and
  the remote's `POST /projections/{projection}/score-toggle`.
  `BookletEditor::toggleScore()` still has its own copy.
- `MusicPlanScoreListService::forViewer()` gives the scores a viewer may see,
  grouped by music. It gets its music ids from the plan
  (`$plan->assignedMusicIds()`).
- `livewire:music-search` (selectable) sends `music-selected{source}`, which is
  how the plan editor adds a music to a slot.
- `ProjectionRemote` gets `outline` from
  `ProjectionRenderPayload::outlineFor()`, and polling picks up
  `Projection::revision()`.

## Decisions

**A deck-only music is a row of its own: `ProjectionMusic` / `BookletMusic`.**
One table per document, following how `projection_slides` and `booklet_scores`
are already split:

```
projection_musics
  id
  projection_id            FK cascade
  music_id                 FK restrict (see merging below)
  music_plan_slot_plan_id  nullable FK nullOnDelete — the slot it was added in; null = between slots
  sequence                 int — only read while the music is empty (see ordering)
  timestamps
```

`booklet_musics` has the same columns with `booklet_id`. Both row tables get a
nullable `added_music_id` FK that cascades on delete. The name is the same on
both tables, as `music_plan_slot_assignment_id` already is, so that
`PlanEntry` can declare it once and add an `addedMusic(): BelongsTo`.

The music has to exist before any of its scores do. The issue asks for the music
to be added first and its scores chosen afterwards "the same way as for other
musics". That means a node that shows offers while it has no rows, and only a
row of its own can do that. A row with a score is also the thing that keeps its
place, gets named in headings and moves as a group with its scores, which is
exactly what an assignment does for plan music.

**In the outline it is a `music` node with `local: true`.** `PlanOutline::for()`
reads the document's added musics next to the plan's slots:

- An added music with a slot goes into that slot's children, next to the plan's
  musics. One without a slot goes to the top level, next to slots and loose
  entries. The second case covers "after the homily": the music can be moved
  between slots with the arrows, because `moved()` already swaps top-level nodes.
- Node ids become strings (`music:12`, `added:5`) wherever `moved()`, `find()`
  and `countUpTo()` compare them. The alternative is a separate `kind`
  (`addedMusic`), but then every `match` on `kind` in both editors, both blade
  panes and the remote's JS would need another case for what is really the same
  node. A different kind would be the smaller diff, but the pane would branch
  in more places.
- Offers come from a new `MusicPlanScoreListService::forMusicIds(array $musicIds,
  ?User $viewer)`. `forViewer()` is refactored to call it with the plan's ids.
  Scoping and loans stay identical, so an added music never offers a score the
  service list would hide.

**Ordering: an empty added music keeps its place by counting.** A music that
has rows sits wherever its first row sits, as plan music does. An empty one has
no row to stand on. Plan music handles that with `anchor()` and `planIndex`, and
an added music has no plan index. So its `sequence` column holds *the number of
rows printed before it*. That is the same counting `insertIndex()` does. On a
tie it comes before the row. `writeOrder()` in both editors (and the toggle
service) rewrites that number for every empty added music whenever it rewrites
row sequences. `PlanOutline` gets a `placeholders(array $outline): array<int,
int>` next to `flatten()` for this. Without it, an empty music added after the
homily would jump to the end of the deck until its first score was chosen.

**Headings: it names itself the way plan music does.**

- Inside a slot: the slot line comes from `music_plan_slot_plan_id`, the music
  line from `addedMusic->music->title`, and `musicCountsPerSlot()` counts added
  musics as well. So a birthday song added to Communion next to the planned
  hymn gets its own line.
- Between slots: the music's title takes the slot line, like today's score
  outside the plan does, and it follows the row's `show_slot` switch.
  `show_music_title`, `show_collections` and `show_variation` work unchanged.

**How it looks in the editor.** Added musics are the one thing in the pane that
the plan does not have. So they get an amber band (a dashed amber start border,
with the music icon in amber) and a small "Only in this projection" / "Only in
this booklet" badge. The music title row gets one more button, a trash icon
(*Remove this music from the projection*), behind a `flux:modal` confirm when it
has scores. The music's rows are removed along with it.

Where it is added:

- Slot band: an extra `plus` button next to "Add text under the slot name"
  → music search → the music is added inside that slot, at the end.
- Row: nothing. Keeping the add points small is the same choice text blocks made.
- Card header: "Add music" next to "Add text" → music search → the music is
  added between slots, at the end of the deck, and the user moves it from
  there. There is no insertion-point picker, because the arrows already do that
  job and a one-minute-before-Mass flow cannot afford another dialog.

Always at the end of its container, never "where we are now". A music asked for
during the Kyrie is almost always for later in the service, often for
Communion, and the end is where the arrows have the least distance to cover.

**No promotion into the plan.** A between-slots music has no slot, so promoting
it would first ask for one. That is more friction than opening the plan and
adding the music there. It is rare, and it stays that way on purpose.

Once a music is picked, the search modal closes and the new node opens with its
offers visible. It does not take the first score automatically: which of a
music's engravings is on the wall is the reason this editor exists.

This also works for a deck with no plan. Added musics are then the only music it
can hold, and the empty-state text "can hold words but no music" is rewritten.

**The remote gets the same write, over JSON.**

- `GET /projections/{projection}/music-search?q=` is a small JSON controller
  that uses `HasMusicSearchScopes` for the title/collection-number search, is
  limited to 15 results and uses the `throttle:projection-payload` limiter. It
  is not the Livewire `music-search`: the remote talks JSON so nothing touches
  the `wire:ignore`'d canvas mid-service, and the same reason applies here.
- `POST /projections/{projection}/added-musics` (with `music_id` and an optional
  `slot_plan_id`) and `DELETE /projections/{projection}/added-musics/{addedMusic}`
  both return the fresh payload and revision, as `ProjectionScoreToggleController`
  already does.
- `ProjectionScoreToggleRequest` accepts `addedMusicId` next to `assignmentId`,
  and `ProjectionScoreToggle::toggle()` checks that it belongs to *this*
  projection, the way `assignmentInPlan()` checks the plan.
- `slimOutline()` passes along `addedMusicId` and `local`, and the remote's
  music block uses the same amber marker. The add points match the editor's: a
  `+` on each slot band (added at the end of that slot) and "Add music" at the
  top of the outline column (added at the end of the deck). Both open a bottom
  sheet with a search field. The rule is the editor's too: the end of the
  container, not the current slide.
- `Projection::readRevision()` also takes `max(projection_musics.updated_at)`,
  and `ProjectionRevisionObserver` also forgets on `ProjectionMusic`. Otherwise
  an empty music added from the laptop editor would never reach the phone.

**The remote moves things the way the editors do.** A music added at the end
has to be movable from the phone, or adding it from there is useless. Moving is
also worth having for anything else in the deck, since "sing the Gloria after
the reading today" is the same kind of last-minute change.

- **One tree and one rule.** `PlanOutline::moved()` already refuses every move
  that would break the hierarchy: a row cannot leave its music, a music cannot
  leave its slot, and a slot cannot be moved past the Introit unless the Introit
  holds nothing. The remote does not get a rule of its own. It sends
  `POST /projections/{projection}/move` with `{kind, id, direction}`, and the
  controller runs the same `moved()` → `flatten()` → write as the editors.
  `kind` is `entry | slot | music | added`, the same node keys the outline now
  uses.
- **Writing the order leaves the editors.** `normalizeOrder()`, `applyOrder()`
  and `writeOrder()` are copied almost word for word into both editors, and the
  toggle service has a third copy. They move into a `PlanOrder` service with
  `move(PlanDocument, kind, id, direction): bool` and
  `write(PlanDocument, list<int>)`. That service also rewrites the positions of
  empty added musics (see ordering), so the rule lives in exactly one place.
  `ProjectionEditor::moveNode()`, `BookletEditor::moveNode()`, the toggle and
  the new controller all call it.
- **The slim outline says what may move.** Slot nodes now carry their `id`, and
  every node, entries included, carries `canMoveUp`/`canMoveDown`. In the
  editors a row's arrows are greyed by the stylesheet, because a row is its own
  Livewire component and doesn't know its neighbours. The remote has no such
  component, so `slimOutline()` works the flags out per entry from the weights
  of its siblings, using the same "nearest sibling with anything in it" test as
  `withMoves()`. An arrow the phone shows as enabled is then never a move the
  server refuses.
- **UI.** On a touch screen, arrows on every line would crowd a remote that is
  mainly for pressing Next. So the outline column gets a "Reorder" toggle in its
  header. When it's on, every slot band, music title and row shows a pair of
  up/down buttons, and tapping a row stops navigating. When it's off, the column
  is exactly what it is today. The toggle is kept per viewer in `localStorage`,
  wrapped in try/catch.
- **The wall does not jump.** `Presentation` stores its position as
  `{entryId, slideIndex}`, and `applyPayload()` finds that address again in the
  reordered deck. That is the path the "shown score removed" fix
  (e795e59) hardened. A move changes the order but removes nothing, so the slide
  on screen stays on screen even if its own row is the one being moved. A test
  pins this down, because it is the one way reordering could interrupt a Mass.
- **Response.** The same payload and revision as the toggle, so the phone
  redraws without polling and the wall picks it up at its next revision check.

**Booklets reuse the toggle.** `ProjectionScoreToggle` is generalised to
`PlanScoreToggle` over `PlanDocument`. Its only projection-specific
dependency is the render payload's `fileOf`/`sourcesFor`, and those live on
`PlanRenderPayload`. `BookletEditor::toggleScore()` then calls it, and its own
copy is deleted. Nothing else about booklets changes: no remote, no revision.

**Copies, merges, deletions.**

- `duplicate()` on both models copies added musics first, then remaps
  `added_music_id` on the copied rows.
- The music merger (`⚡music-merger.php:492`, which repoints
  `music_plan_slot_assignments` by hand) repoints `projection_musics.music_id`
  and `booklet_musics.music_id` in the same transaction. `music_id` is
  `restrict` so that a merger that forgets fails loudly instead of emptying a
  deck.
- If the plan slot an added music sits in is deleted from the plan, the music
  becomes a between-slots music (`nullOnDelete`). It is not lost.

## Rejected alternatives

- **`music_id` directly on the row, with no table of its own.** Rows sharing a
  music id would form a group. But an empty music could not exist, so the
  "add the music, then pick scores" flow from the issue would be impossible, and
  "remove this music" would mean deleting rows by matching a value.
- **An ad hoc `MusicPlanSlotAssignment` flagged as belonging to one document.**
  It reuses the whole outline for free, but every plan reader (service list,
  plan view, suggestions, lending, copies of the plan) would have to learn to
  skip it. The whole point is that the plan does not change.
- **Not saving remote changes.** The issue rejects this outright: three versions
  of one service.
- **One polymorphic `document_musics` table.** The documents already keep
  separate row tables, and FKs with cascade are worth more than one fewer
  migration.

## Files touched

| File | Change |
|---|---|
| `database/migrations/*_create_projection_musics_table.php`, `*_create_booklet_musics_table.php` | new tables |
| `database/migrations/*_add_added_music_id_to_document_rows.php` | FK on `projection_slides`, `booklet_scores` |
| `app/Models/ProjectionMusic.php`, `BookletMusic.php` + factories | new |
| `app/Models/Projection.php`, `Booklet.php` | `addedMusics()`, `duplicate()`, `readRevision()` |
| `app/Models/ProjectionSlide.php`, `BookletScore.php`, `app/Contracts/PlanEntry.php`, `PlanDocument.php` | `added_music_id`, `addedMusic()`, `addedMusics()` |
| `app/Services/PlanOutline.php` | added-music nodes, string node ids, `placeholders()` |
| `app/Services/PlanRenderPayload.php` | headings for added musics |
| `app/Services/MusicPlanScoreListService.php` | `forMusicIds()` |
| `app/Services/ProjectionScoreToggle.php` → `PlanScoreToggle.php` | generalised, `addedMusicId` |
| `app/Services/PlanOrder.php` | new: `move()`, `write()`, empty-music positions; the three copies of order writing go |
| `app/Services/ProjectionRenderPayload.php` | `slimOutline()` carries added musics, slot ids, `canMoveUp`/`canMoveDown` on every node |
| `app/Livewire/Pages/ProjectionEditor.php`, `BookletEditor.php` | `addMusic()`, `removeAddedMusic()`, `moveMusic()` by node key, order writing via `PlanOrder`, music-search listener |
| `resources/views/livewire/pages/projection-editor/plan.blade.php`, `booklet-editor/plan.blade.php` | amber node, badge, add/remove buttons, search modal |
| `app/Http/Controllers/ProjectionMusicSearchController.php`, `ProjectionAddedMusicController.php`, `ProjectionMoveController.php` + Form Requests | remote endpoints |
| `app/Http/Requests/ProjectionScoreToggleRequest.php` | `addedMusicId` |
| `resources/views/livewire/pages/projection-remote.blade.php` + its JS | add-music sheet, amber block, remove, Reorder mode with arrows |
| `app/Observers/ProjectionRevisionObserver.php` | forget on `ProjectionMusic` |
| `routes/web.php` | four routes |
| `resources/views/components/editor/⚡music-merger/music-merger.php` | repoint both tables |
| `lang/hu.json` | new strings |

## Build order

1. Tables, models, `duplicate()`, merger. No UI yet, fully testable.
2. `PlanOutline` + headings + `forMusicIds()`. This is the core, and the rest
   is wiring on top of it.
3. `PlanOrder`, taken out of both editors with no change in behaviour. The
   existing editor tests are the safety net.
4. Projection editor (add, offers, toggle, move, remove).
5. Booklet editor, via `PlanScoreToggle`.
6. Remote: revision, move endpoint + Reorder mode, then add/remove + sheet.

Steps 1–5 are shippable without 6. The remote can still *show* an added music
(`slimOutline`) before it can *add* one. Within 6, moving comes first: it is
useful on its own, even for a deck with no added music.

## Verification

New tests: `tests/Feature/ProjectionAddedMusicTest.php`,
`tests/Feature/BookletAddedMusicTest.php`, plus cases added to existing files:

- Adding a music inside a slot or between slots creates no
  `MusicPlanSlotAssignment`, and the plan's service list is unchanged.
- An empty added music keeps its place through `normalizeOrder()` and through
  moving other rows. Its first score lands under it.
- Offers respect visibility: a private score of someone else is not offered and
  cannot be toggled in by id. Neither can an `addedMusicId` from another deck.
- Headings: the slot line plus music line inside a slot, the title as heading
  between slots, and all four switches honoured.
- Removing the music removes its rows. Deleting the plan slot makes it a
  between-slots music.
- `duplicate()` copies added musics and the copied rows point at the *copy's*
  music.
- Merging two musics repoints added musics.
- Remote: search returns JSON, adding bumps `revision()` immediately (cache
  forgotten), adding puts the music at the end of the deck (or of the slot),
  and toggling with `addedMusicId` works while a presentation is live.
- Remote moves: every move `PlanOutline::moved()` refuses is refused at the
  endpoint (a row out of its music, a music out of its slot, past an empty
  sibling), and an added music moves across whole slots. A deck of someone
  else's gets a 404.
- `canMoveUp`/`canMoveDown` in the slim outline match what the endpoint
  accepts, for every node in a mixed fixture.
- Moving the row currently on the wall leaves the presentation on the same
  `{entryId, slideIndex}`. The JS side of this goes in
  `tests/Unit/projection-remote.test.mjs`, next to the e795e59 case.

```
php artisan test --compact --filter=AddedMusic
php artisan test --compact tests/Feature/ProjectionEditorTest.php tests/Feature/BookletEditorTest.php
php artisan test --compact tests/Feature/ProjectionScoreToggleTest.php tests/Feature/ProjectionRemoteTest.php tests/Feature/ProjectionModelTest.php
php artisan test --compact --filter=ProjectionMove
php artisan test --compact --filter=Merge
node --test tests/Unit/projection-remote.test.mjs
```

Manually: on a phone during a live presentation, while at the Kyrie, add a
music, move it up into place in front of Communion and pick its score. The wall should pick it up at the next poll, and the
laptop editor should show it in amber after a reload.
