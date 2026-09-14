# Screens of Words That Cut Themselves

## Context

A projection row that holds words rather than music is one row and, today, always
exactly one slide. `projection-deck.js:131` says so outright:

```js
if (entry.kind === 'text') { return [textSlide(entry, ratio, palette, geometry)]; }
```

When the words do not fit, `textSlide()` answers by shrinking: the whole stack of
laid-out rows is scaled by `Math.min(1, box / total)` (`projection-deck.js:232`)
and set smaller. Nothing is lost off the bottom, but a long rubric ends up
unreadable from the back of the church — and the `overflows` flag at
`projection-deck.js:252`, computed *after* the clamp, can effectively never fire
to say so.

Music does not have this problem. A score's author writes `%pagebreak169` into the
source and the browser cuts the score into screens every time the deck is drawn
(`score-editor-pages.js:82`). `ProjectionSlide`'s own docblock is built around
that asymmetry — "a score cut by `%pagebreak169` into three screens is still one
row" — and `excluded_slides` already stores per-row, per-ratio, zero-based slide
positions, so the schema is already shaped for a text row that comes to more than
one slide.

What is wanted is for words to break the way music already does, with three
things settled:

1. Text that does not fit is **cut into more slides**, not shrunk.
2. The author can say **where**, because an automatic cut lands in the wrong place
   often enough to matter.
3. Where the cut belongs **depends on the screen shape** — a stanza that fits 4:3
   in one screen wants two on a 16:9.

## Decision

**Reuse the `%pagebreak` vocabulary the application already speaks, and add a
soft form.** A marker on a line of its own inside a text row's Markdown:

| marker | ratio | force |
| --- | --- | --- |
| `%pagebreak` | every shape | always cuts |
| `%pagebreak169` / `%pagebreak43` / `%pagebreak11` | that shape only | always cuts |
| `%pagebreak?` | every shape | cuts only if what it sits in overflows |
| `%pagebreak169?` etc. | that shape only | cuts only if what it sits in overflows |

The ratio suffixes are not invented here; they are documented in three user guides
already (`docs/chordpro-cheatsheet.md:33`, `docs/abc-felhasznaloi-utmutato.md:347`,
`docs/aretino-felhasznaloi-utmutato.md:1076`) and parsed by one regex in
`score-editor-pages.js:28`. They answer requirement 3 exactly.

The soft form is the one new idea, and it is not a new idea in the world: it is
the projection trade's own convention — OpenLP's optional split — and the same
shape as TeX's discretionary break and the soft hyphen, a break point that
materialises only when the line it sits in cannot hold. `?` is chosen because it
reads as the suggestion it is, and because it composes with the suffixes without
a second token.

### What decides a cut

Four tiers, tried in order. Each is a strictly weaker reason to cut than the one
before it, which is the whole of the mental model an author needs:

1. **Hard markers always cut.** The row becomes chunks.
2. **A chunk that fits is one slide.** Its soft markers are not used at all.
3. **A chunk that does not fit is cut at its own soft markers** — and only at as
   many of them as it takes, since the pieces are then packed greedily. A chunk
   with three suggestions that only needs one cut gets one.
4. **A piece that still does not fit is cut at its paragraph and heading
   boundaries**, greedily, the way the booklet already flows prose across pages.

Below all four, a single paragraph taller than the screen on its own is **shrunk
to hold, and flagged** — never cut mid-sentence. That is today's behaviour,
demoted from first answer to last resort.

The consequence worth stating plainly: an existing deck whose long text row shrank
to one slide will now come to two or three. That is the point of the change, but
it is a change to decks already built.

## Implementation

### 1. Teach the Markdown parser the marker

`resources/js/booklet-markdown.js`

- `parseBlocks()` (:184) gains one case beside the `rule` case: a line matching
  `/^\s*%pagebreak(\d*)(\?)?\s*$/` becomes `{ type: 'break', ratio: <suffix>, soft: <bool> }`.
  Without this the marker prints as a paragraph — which is also a live bug for
  booklet text entries, which share this parser through `booklet-render.js:373`.
- `markdownRows()` (:95) gains one option, `ratio`. It filters `break` blocks out
  of the block list before laying anything out, carrying each one forward as a
  `breakBefore: 'hard' | 'soft'` field on the **first row of the next block**.
  A break whose suffix names another shape is dropped. **No `ratio` option means
  every break is dropped** — so a booklet keeps printing one continuous rubric,
  which is what `splitPages()` already means by paper mode
  (`score-editor-pages.js:71`).
- Filtering before `blockFontSize`/`gapBefore` keeps `gapBefore()` (:161) correct:
  it indexes `blocks`/`sizes` in step, and index 0 already returns 0.

The returned `MarkdownRow` typedef (:69) grows one optional field. Nothing that
ignores it changes behaviour.

### 2. The packer

New `resources/js/projection-text-pages.js`, sibling of `score-editor-pages.js`
and, like `booklet-flow.js`, knowing nothing about SVG — a row is a height and
some flags:

```js
/**
 * @param {MarkdownRow[]} rows  from markdownRows(), with breakBefore set
 * @param {number} boxHeight
 * @returns {Array<{rows: MarkdownRow[], height: number}>}
 */
export function packTextPages(rows, boxHeight)
```

Each tier is one call to **`packPages(blocks, contentHeight)` from
`resources/js/booklet-flow.js:29`**, which already does everything needed:
`breakBefore` forces a page, `keepWithNext` keeps a heading with what follows it,
`spaceBefore` is dropped at a page top, and a group taller than the page is placed
anyway rather than refused (:109-115). Reusing it is what makes tiers 3 and 4
three lines each rather than a second packer:

- tier 1: cut `rows` where `breakBefore === 'hard'`.
- tier 3: each soft segment becomes one atomic block whose height is the segment's
  own stack height; `packPages` over those picks only the cuts it needs.
- tier 4: `packPages` over the rows themselves, `keepWithNext` intact.
- a page that comes back taller than `boxHeight` is left as it is; the caller
  shrinks it.

### 3. Draw the slides

`resources/js/projection-deck.js`

- `textSlide()` (:214) becomes `textSlides()` returning a list. It lays the whole
  row out once with `markdownRows(..., { ratio })`, calls `packTextPages(rows, canvas.height * (1 - 2 * TEXT_MARGIN))`,
  and runs the existing stack-and-centre block (:234-246) once per page — the
  `total`/`scale`/`y` arithmetic is unchanged, just scoped to one page's rows.
- `slidesOf()` (:129) loses its single-element array for text.
- `overflows` becomes truthful: `scale < 1` for that page, rather than the
  post-clamp comparison at :252 that can never be true.

A text row gains nothing from `withHeading()` — text rows carry no score heading
today and should not start.

### 4. The editor

- `resources/views/livewire/projection/slide-row.blade.php:137` — drop the
  `@if(! $entry->isText())` guard so a text row shows its slide count and its
  skipped count from `slidesOf()`/`skippedOf()` like every other row. The comment
  above it ("A row does not choose where it breaks: the score does") needs
  rewriting to say the row's own Markdown does, for a text row.
- `slide-row.blade.php:217` — extend the Markdown help line with the markers, and
  translate it in `lang/hu.json:1364`.
- `slide-row.blade.php:238` and `projection-editor.blade.php:25` already tell the
  author to "put a %pagebreak in the score to split it instead"; for a text row
  that advice now points at the row's own text.
- Nothing else moves. `toggleSlideExclusion()` (`ProjectionEditor.php`) and
  `excludedFor()`/`excludedToggled()` (`ProjectionSlide.php:129,152`) are already
  index-based and ratio-keyed, and `ProjectionEditor.php:671` already documents
  that an index goes stale when a break moves. Text rows inherit that unchanged.

### Not in scope

- `%[169 … %]` conditional *content* blocks in text rows. `applyConditionalBlocks()`
  (`score-editor-pages.js:49`) would extend to Markdown the way it already does to
  ChordPro — inactive blocks deleted outright rather than left as comments — but
  it is a separate feature from breaking.
- The soft `?` form for scores. Worth having later; it is a one-character change
  to `PAGE_BREAK` plus a two-tier pass in `splitPages()`, but scores are engraved
  by four different engines and the overflow signal from each would have to drive
  the retry.

## Verification

**Node unit tests** — the packing is pure, so it is tested the way
`tests/Unit/booklet-flow.test.mjs` tests `packPages`, with heights and no DOM:

- `tests/Unit/projection-text-pages.test.mjs` (new) — the four tiers:
  content that fits stays one slide with its soft markers unused; a hard marker
  cuts regardless of fit; a soft marker cuts only on overflow; two soft markers
  where one cut suffices produce two slides, not three; a paragraph taller than
  the box comes back as one over-tall page rather than being cut.
- `tests/Unit/booklet-markdown.test.mjs` (existing) — a `%pagebreak` line yields a
  `break` block; `markdownRows` with no `ratio` drops it entirely; with
  `ratio: '4/3'` a `%pagebreak169` is dropped and a `%pagebreak43` sets
  `breakBefore` on the next row.

Run with `node --test tests/Unit/projection-text-pages.test.mjs tests/Unit/booklet-markdown.test.mjs`.

**Pest** — `php artisan test --compact --filter=ProjectionEditor`. The server side
barely changes, so this is a regression guard: `ProjectionSlideFactory::text()`
already exists, and the cases at `tests/Feature/ProjectionEditorTest.php:147,595,629,657`
must still pass. Add one asserting a text row carrying a `%pagebreak` round-trips
through `ProjectionRenderPayload::entries()` verbatim — the cutting is the
browser's job and the payload must not touch it.

**By hand** — `npm run build`, open a projection editor, paste a long rubric with
`%pagebreak?` in the middle into a text row and watch the contact sheet: one slide
at 4:3, two at 16:9. Then `vendor/bin/pint --dirty --format agent`.
