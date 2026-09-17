# Sections: One Hymn, Any Stanzas

## Context

A hymn has several stanzas, and often a refrain. What a given Sunday needs is
seldom the whole hymn in the order it was written: stanzas 1, 2 and 4, the refrain
after each, or the refrain alone as a response somewhere else in the plan.

Today there are two ways to get that, and both hurt:

- **One score per stanza.** Flexible, but a hymn becomes five scores to make, name,
  keep in step and drag into every booklet and slide deck one by one.
- **One score for the whole hymn.** Easy to make, but a booklet prints all of it,
  with no way to leave anything out. A slide deck can hide slides with
  `excluded_slides`, but those are **positions** (`ProjectionSlide::excludedFor`),
  so moving a `%pagebreak` in the score quietly changes which slides are hidden
  (`ProjectionEditor.php:712` says so). Neither can repeat a refrain.

What is wanted is to write the hymn once, mark where its parts begin, and let each
booklet or slide-deck row say which parts it wants and in what order. Three
constraints:

1. **Opt-in, and cheap.** A score with no markers behaves exactly as it does today.
2. **One idea, not a taxonomy.** No difference between verse, refrain and
   anything else. A part is a part, and its name is whatever the author calls it.
3. **A part can stand alone.** A row that picks only `Refrén` looks like a short
   score of its own, with the format header it needs to render.

## Decision

### In the score: `%section`, with an optional label

```
X:1
T:Ki Jézus Szívét
K:G
%section 1
...music with the first stanza...
%section 2
...
%section Refrén
...
```

- A line matching `/^\s*%section(?:\s+(.+?))?\s*$/` starts a section, which runs
  to the next marker or to the end of the source.
- **Sections are numbered by position: 1, 2, 3, in the order they are written.**
  That number is how a row refers to one. The label is optional and is only a
  name for people to read: it appears beside the number in the row editor
  (`2 · versszak`). It never has to be unique, since `%section versszak` five
  times is exactly how a hymn is written.
- **Why one spelling, `%section`, rather than `%S1` as well.** A label may contain
  a space or an accent (`Refrén`, `Nagy doxológia`), which a glued-on short form
  can't hold. Two spellings for one idea is the confusion
  `plans/done/chordpro-slides-that-flow.md` already turned down for `{np}`. It is
  also still short, and it reads the way `%pagebreak` does.
- The same marker works in **all four formats**. In ABC, GABC and Aretino, `%`
  already begins a comment, so a marker the renderer never sees is harmless. In
  ChordPro it is not a comment, so the marker line is always removed before
  anything is laid out, which is how `%pagebreak` is handled there today.
- **Preamble.** Whatever stands between the format's header (`HEADER_END` in
  `score-editor-pages.js`) and the first marker, such as a ChordPro `{title}`, a
  GABC `name:` line or an Aretino `%indent`, belongs to no section. It is written
  once, in front of the first section the row prints.
- **A marker is a start, not a pair.** There is no closing marker to forget, and
  ending one section is starting the next.

### In a booklet or slide-deck row: an ordered list of section references

A new nullable JSON column, `sections`, on `booklet_scores` and on
`projection_slides`:

- `null` means the whole score, as written. That is every existing row, and every
  new row until someone chooses otherwise.
- Otherwise it is an ordered list of section numbers: `[1, 4, 2, 4]`.
  **Repeating a number repeats the section**, so a refrain sung after every
  stanza is simply listed that many times.
- A number the score no longer has (the score was shortened) is skipped when
  drawn and shown greyed in the row. It is kept rather than deleted.

Having the same score in two rows, one choosing sections 1 and 2 and another
choosing only the refrain, is how a section "acts as a full score": each row
carries its own heading, settings and position in the plan. No new model is
needed.

### Section identity

There is no reference that survives every edit, and pretending otherwise would
be worse than admitting it:

| score edit | position (`n`) | label |
| --- | --- | --- |
| text inside a section changes | ✅ | ✅ |
| a label is renamed | ✅ | ❌ |
| same label used several times | ✅ | ❌ ambiguous |
| a section is inserted or deleted before others | ❌ shifts | ✅ if unique |
| sections reordered | ❌ | ✅ if unique |

Stable ids (`%section #refr`) would fix both columns, but only by asking the
author to invent and maintain a key, which is the friction this feature exists
to remove.

So the design **resolves by position and accepts the drift.** A booklet or
slide deck is made for one date. When a score is edited later, for example to
mark sections properly or to put back a stanza that was left out, older
documents simply follow the score as it is now. That is usually what is wanted,
and when it isn't, the document is from a past date anyway. No logic tracks
section changes.

What the row does show is a plain **"score changed since" marker**: a small `*`
beside the score's name, with the tooltip *„A kotta azóta módosult”*, when
`score.updated_at` is later than the row's own `updated_at`. It says nothing
about sections. It is a general hint that this row was set up against an
earlier version of the score, and any change to the row clears it.

### How the chosen sections are drawn

- **Slide deck:** every chosen section **starts a new slide** at a fixed screen
  shape. The `%pagebreak`, `%pagebreak169` and `%pagebreak?` markers inside a
  section keep working as they do today, so a long section still breaks within
  itself. This gives "a slide per stanza" without writing any breaks.
- **Booklet:** the chosen sections flow on one after another, like one score. A
  forced new page between sections is left out on purpose, because
  `start_on_new_page` already exists for a whole row.
- **Score editor:** shows the whole score, as it always has. Marker lines stay in
  the source, so the character positions the Aretino editor uses to map clicks
  back to the text don't shift (the same reason given in
  `applyConditionalBlocks`). For ChordPro they are removed before the preview
  (no click mapping there).

### Why this and not…

- **An `%order 1 R 2 R` line in the score** (discussed earlier). Left out to keep
  this to one idea: the order belongs to the occasion, not the hymn. If typing
  the same order into many rows becomes tiring, a score-level default can be
  added later without changing anything here.
- **Verse/refrain types (OpenLP's `V1 C1`).** Nothing here would behave
  differently based on the type, so it would be vocabulary without consequences.
- **Stacked stanzas** (verse N = the ABC/Aretino music with only its Nth `w:` line
  under it). Not in scope. A hymn meant to be cut this way is written with one
  `w:` line per section. If wanted later, it could be a second form of the same
  marker, and the column's format wouldn't change.
- **Labels as the key.** They break on rename and are ambiguous when repeated,
  and the natural way to write a hymn repeats them. See *Section identity*.
- **Explicit ids.** Stable, but they make the author maintain keys.
- **Drift detection** (storing each chosen section's label and warning when it
  changes). Turned down: after a score edit, following the score is the desired
  behaviour, and a warning would be noise.

## Implementation

### 1. The cutting: one pure module

New `resources/js/score-sections.js`, a sibling of `score-editor-pages.js`. Like
that file, it does text work only and never touches SVG:

```js
export const SECTION_MARKER = /^\s*%section(?:\s+(.+?))?\s*$/;

/** @returns {{header: string, preamble: string, sections: Array<{n: number, label: string|null, body: string}>}} */
export function parseSections(content, format)

/** Sources with every marker line removed; used when a row chooses nothing. */
export function stripSectionMarkers(content)

/**
 * The source a row prints: header + preamble + chosen sections in order.
 * `separator` is joined between sections — '%pagebreak' for a slide deck, '' for a booklet.
 * @param {number[]|null} references
 * @returns {{source: string, missing: number[]}}
 */
export function arrangeSections(content, format, references, { separator })
```

`header` reuses `HEADER_END`, which is exported from `score-editor-pages.js`
rather than copied. A `null` or empty reference list returns
`stripSectionMarkers(content)`, so existing rows get the same source they get
today, minus marker lines that couldn't have existed yet.

### 2. Where it is applied

- `resources/js/projection-render.js`: `ratioPageSources()` calls
  `arrangeSections(..., { separator: '%pagebreak' })` first, before the ABC
  rewrites and `splitPages`, so the ABC header is inserted once and each section
  break is an ordinary hard break. `renderRatioPages` receives the row's
  `sections`.
- `resources/js/projection-deck.js`: `scoreSlides()` passes `entry.sections`
  through. It already puts the heading on the first slide only, which still holds.
- `resources/js/booklet-render.js`: `musicBlocks()` arranges `entry.content` with
  `separator: ''` before choosing an engine.
- `resources/js/score-editor-chordpro.js`: strip markers before the preview and
  before rendering ratio pages.
- `app/Services/BookletRenderPayload.php`, `app/Services/ProjectionRenderPayload.php`:
  add `'sections' => $entry->sections` beside `content`.

### 3. Storage

- One migration adding `$table->json('sections')->nullable()` to both tables.
- `BookletScore`, `ProjectionSlide`: fillable, `'sections' => 'array'` cast,
  `@property list<int>|null $sections`.
- `Booklet.php:293` and `Projection.php:334`: copy `sections` when a document is
  duplicated, the same way `settings_override` is.
- Factories: a `withSections(array $references)` state on both.

### 4. Listing a score's sections for the editor

New `app/Support/ScoreSections.php` with
`list(?string $content): list<array{n: int, label: string|null}>`. It uses the
same marker pattern, and it only **lists**. It never cuts, which stays the browser's job, as
`ProjectionRenderPayload` already assumes. It exists so the row editors can offer
chips from Blade and so the Livewire action can reject junk. Both implementations
are tested against the same fixture sources (see Verification).

### 5. The row editors

`resources/views/livewire/booklet/entry-row.blade.php` and
`resources/views/livewire/projection/slide-row.blade.php`, shown only when the
row's score has at least one `%section`:

- A **Sections** line with one Flux badge per chosen reference, in order, reading
  `2 · versszak`, with the label read from the score as it is now. Each badge has
  a remove ×, and a number the score no longer has is greyed.
- The `*` "score changed since" marker next to the score name (shown for every
  score row, whether or not it has sections).
- An **Add** dropdown listing the score's sections as `n · label`, plus **All**
  (clears to `null`). Adding the same section twice is allowed, since that is how
  a repeat is made.
- Order changes use the up/down arrows the rows already use for entries, not drag
  and drop.

Livewire actions on `BookletEditor` and `ProjectionEditor`:
`addSection(int $entryId, int $sectionNumber)`,
`removeSection(int $entryId, int $position)`,
`moveSection(int $entryId, int $position, int $direction)` and
`clearSections(int $entryId)`. Each one
authorizes like `toggleSlideExclusion` and checks the number against
`ScoreSections::list()`. On a slide deck,
**changing `sections` clears that row's `excluded_slides`**, because the positions
they refer to have just changed, so keeping them would hide the wrong slides.

### 6. Say so

- `docs/abc-cheatsheet.md`, `docs/aretino-cheatsheet.md`, `docs/gabc-cheatsheet.md`,
  `docs/chordpro-cheatsheet.md`: one table row each for `%section` and
  `%section Címke` ("szakasz kezdete; a füzetben és a vetítésben kiválasztható").
- The matching sections of the two user guides (`abc-felhasznaloi-utmutato.md`,
  `aretino-felhasznaloi-utmutato.md`), with the hymn example above.
- `lang/hu.json`, `lang/en.json`: the row editor's strings.

### Not in scope

- A score-level default order (`%order`).
- Selecting stacked `w:` stanzas.
- Sections in text-only rows or uploaded-file rows. Those have no score source to
  mark.
- Showing a section's label on the page or slide. The label is a handle for the
  author. If a printed "1." is wanted, it is already part of the stanza's text.

## Files touched

| File | Change |
| --- | --- |
| `resources/js/score-sections.js` (new) | parse, strip, arrange |
| `resources/js/score-editor-pages.js` | export `HEADER_END` / header lookup |
| `resources/js/projection-render.js`, `projection-deck.js` | arrange before split |
| `resources/js/booklet-render.js` | arrange before engraving |
| `resources/js/score-editor-chordpro.js` | strip markers |
| `database/migrations/…_add_sections_to_booklet_scores_and_projection_slides.php` (new) | `sections` JSON column |
| `app/Models/BookletScore.php`, `ProjectionSlide.php`, `Booklet.php`, `Projection.php` | cast, fillable, copy on duplicate |
| `app/Support/ScoreSections.php` (new) | list labels for the editor |
| `app/Services/BookletRenderPayload.php`, `ProjectionRenderPayload.php` | pass `sections` |
| `app/Livewire/Pages/BookletEditor.php`, `ProjectionEditor.php` | section actions |
| `resources/views/livewire/booklet/entry-row.blade.php`, `projection/slide-row.blade.php` | chip editor |
| `database/factories/BookletScoreFactory.php`, `ProjectionSlideFactory.php` | `withSections` |
| `docs/*-cheatsheet.md`, two user guides, `lang/*.json` | documentation |

## Verification

**Node**, where the logic lives:
`tests/Unit/score-sections.test.mjs` (new). The cases:
- no markers: the source comes back unchanged;
- sections are numbered by position, whether or not they have labels, and
  repeated labels are allowed;
- a preamble is printed once, ahead of the first chosen section;
- a repeated reference repeats the section;
- a missing reference is skipped and reported;
- the ABC header (up to `K:`) and the Aretino/GABC header (up to `%%`) are kept;
- ChordPro marker lines are removed;
- `separator: '%pagebreak'` gives one page per section through `splitPages`.

Extend `tests/Unit/projection-render.test.mjs` so that a row choosing sections
2, 4, 2 gives three pages.

`node --test tests/Unit/score-sections.test.mjs tests/Unit/projection-render.test.mjs tests/Unit/score-editor-page.test.mjs`

**Pest:**
- `tests/Unit/ScoreSectionsTest.php` (new): the same fixture sources as the JS
  test give the same list.
- `BookletEditor` / `ProjectionEditor` feature tests:
  - add, remove, move and clear sections;
  - the `*` marker appears when the score is newer than the row, and disappears
    after the row is changed;
  - a section number the score doesn't have is rejected;
  - on a slide deck, changing sections clears `excluded_slides`;
  - both render payloads include `sections` and pass the score's `content`
    through unchanged;
  - duplicating a booklet or slide deck copies `sections`.

`php artisan test --compact --filter='ScoreSections|BookletEditor|ProjectionEditor|BookletRenderPayload|ProjectionRenderPayload'`

**By hand:** `npm run build`. Write a three-stanza ABC hymn with a
`%section Refrén` as section 4. Add it to a slide deck choosing 1, 4, 3, 4 and
check the contact sheet shows four slides. Add it to a booklet choosing 1 and 3,
and check that stanza 2 and the refrain are gone. Then edit the score and check
that both rows show the `*`. Then run `vendor/bin/pint --dirty --format agent`.
