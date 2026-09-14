# A Chord Sheet Is Not an Engraving

## Context

Four formats reach a projector slide and three of them are music: a staff is the
width it is, a neume sits where the engraver put it, and a page that ran over
ran over because the author asked for more than the screen holds. `renderRatioPage`
(`projection-render.js:63`) says as much in its own docblock — "nothing here
shrinks an engraving to make it fit: that answer belongs to the author, who gives
it with a smaller size or another `%pagebreak`."

ChordPro is in that list and does not belong to it. A chord sheet is words with
chords standing over them; it has no engraved extent to preserve, and the one
thing that must not happen to it — a chord parting company with its syllable —
is already prevented, by hand, two levels down. So it inherits an engraving's
manners without an engraving's reasons:

- `renderChordproSlide` (`score-editor-chordpro.js:397`) lays the whole page out
  at the slide's width, however tall that comes to, and `frameSlide` restates the
  canvas as the viewBox — **everything past the bottom of the screen is cut off**,
  with `overflows` raising a clip warning to say so.
- `%pagebreak?` is stripped before it is ever seen (`score-editor-pages.js:120`).
  The reason given is honest and does not apply here: "deciding whether a staff
  overflowed is an answer each of the four engines gives differently." ChordPro
  has no engine. Its rows are laid out in this repository, and every one of them
  has a known height before anything is drawn.

What is *already* right is worth stating, because it is most of the work:

- **Lines already wrap, and chords already stay put.** `wrapColumns`
  (`booklet-chordpro.js:215`) breaks a line into rows no wider than the page,
  keeping each chord-and-syllable column whole; where a chordless tail is wider
  than the page on its own, `splitColumn` (:287) cuts it between words and leaves
  the chord with the first piece, "which is where it was already standing."
- **Chord sheets already flow across pages — in the booklet.**
  `chordproBookletBlocks` (:40) hands rows to `packPages`, and `chordproRows`
  (:90) already marks a verse `keepWithNext` when it is short enough to be kept
  whole (:129).
- **Screens of words already break at two strengths.** `packTextPages`
  (`projection-text-pages.js:48`) spends hard breaks, then the author's `?`
  suggestions, then paragraph boundaries — see `plans/projection-text-breaks.md`,
  which put the soft form for scores out of scope for exactly the engine reason
  above.

So every piece exists. The slide path is the only place a chord sheet is asked to
be a picture, and it is the only place one gets truncated.

### What the reference implementation does

Worth knowing, and it settles one question by not answering it. ChordPro's own
implementation has **`{new_page}`/`{np}`**, **`{new_physical_page}`/`{npp}`** and
**`{column_break}`/`{colb}`** — all unconditional, all PDF-only ("no effect in the
web display"), and `{column_break}` in the last column degrades to a page break.
There is no soft or conditional page break: the nearest thing is the general
directive-selector syntax, `{comment-alto: …}`, which selects on instrument or
user rather than on how much room is left. Long lines are handled by a global
config setting, `settings.wraplines`, with `wrapindent` for the continuation —
wrapping as a property of the document, not a decision at a break point.

Two things follow. Our `%pagebreak169` already goes past the reference on
conditionality, and our `%pagebreak?` has no counterpart there to copy or clash
with — it stays what `plans/projection-text-breaks.md` made it, the projection
trade's optional split. And automatic wrapping is not exotic: the reference wraps
too, less carefully. Nothing here needs to move toward `{np}`; `%pagebreak` is
this application's vocabulary across four formats, and a fifth spelling that
works in only one of them would be the confusing option.

## Decision

**A ChordPro slide flows; it does not clip.** The same four tiers a screen of
words already uses, in the same order, spent by the same packer:

1. **Hard `%pagebreak`** — and `%pagebreak169` and its two siblings — cuts, as it
   does today, before anything is parsed.
2. **A page that fits is one slide**, and its suggestions go unused.
3. **A page that does not fit is cut at its own `%pagebreak?`**, at as few of them
   as it takes.
4. **A piece that still does not fit is cut at its own rows**, with `keepWithNext`
   holding a verse together where the verse is short enough to hold.

Below all four, a single row taller than the screen — one wrapped line set very
large — is left over-tall and `overflows` says so, which is today's clip warning
narrowed to the one case that deserves it. Nothing is ever shrunk, and nothing is
ever cut off.

The consequence to state plainly, as before: **a chord sheet that silently lost
its last verse off the bottom of a slide now comes to two or three slides.** That
is the point of the change, and it moves slide indices in decks already built —
the same staleness `ProjectionEditor.php:671` already documents for a break that
moves.

`Paper` is untouched. It has no pages, strips every break hard and soft, and is
still the HTML preview.

## Implementation

### 1. Let a soft break survive into a ChordPro page

`resources/js/score-editor-pages.js`

`splitPages` (:98) currently drops every soft break. For ChordPro alone it keeps
the ones addressed to this ratio, normalised to a bare `%pagebreak?` line so
nothing downstream has to know about suffixes again; a soft break numbered for
another ratio is dropped like a hard one. Everything else — three formats, and
ChordPro on paper — is unchanged, and the docblock at :37 and :85 is rewritten to
say ChordPro is now the exception and why (it is laid out here, so its overflow
is knowable).

This is the whole of the ratio arithmetic. The renderer below sees one marker or
none.

### 2. The packer, under a name that fits its second caller

`resources/js/projection-text-pages.js` → `resources/js/soft-pages.js`

`packTextPages` reads exactly four fields off a row — `height`, `spaceBefore`,
`keepWithNext`, `breakBefore` — which is `booklet-flow.js`'s `Block` plus a
strength. It needs no change to serve chord sheets; it needs a name and a home
that do not say `text`, since the score editor importing `projection-text-pages`
would be the wrong shape of dependency.

- rename the module, `packTextPages` → `packSoftPages`, `stackHeight` unchanged.
- widen the typedef from `MarkdownRow` to the `Block` it actually consumes, and
  the docblock to name both callers.
- update `projection-deck.js:6` and rename `tests/Unit/projection-text-pages.test.mjs`
  → `tests/Unit/soft-pages.test.mjs`.

No behaviour moves. If the rename is not wanted, everything below works against
`packTextPages` where it stands; this is the only optional step in the plan.

### 3. Lay a page out as slides instead of as one tall picture

`resources/js/score-editor-chordpro.js`

Two functions where there was one, so the arithmetic can be tested without a DOM
the way `chordproPageLayout` already is:

```js
/**
 * The slides one ChordPro page comes to, as rows and heights.
 * @returns {Promise<Array<{rows: Array<object>, height: number}>>}
 */
export async function chordproSlidePages(pageSource, options)
```

- cut `pageSource` at its `%pagebreak?` lines into segments;
- parse and lay out **each segment on its own** — `parseChordproSong` then
  `chordproRows` at `canvas.width`, with `contentHeight: canvas.height` so
  `keepWhole` (:129) judges a verse against the screen it must fit;
- concatenate the rows, marking the first row of every segment after the first
  `breakBefore: 'soft'` and giving it the paragraph gap `chordproRows` would have
  given it mid-sheet;
- `packSoftPages(rows, canvas.height)`.

Laying each segment out separately is what makes a suggestion inside a verse
work: parsed apart, the two halves are two paragraphs, so `keepWithNext` does not
glue across the cut. The cost is that a `{start_of_verse}` label or a `{title}`
above the marker belongs to the first piece only, which is where it was written.

```js
export async function renderChordproSlides(pageSource, settings, canvas)
```

Returns `Array<{svg: SVGElement, overflows: boolean}>` — one per page from above,
rows stacked from the top through `stackSvgs` exactly as `textSlide`
(`projection-deck.js:243`) stacks its own, then `frameSlide`. Top-aligned rather
than centred, for the reason `fitSlide` already gives: two consecutive slides of
one hymn must not start at different heights. `overflows` becomes
`page.height > canvas.height + SLIDE_FIT_TOLERANCE`, true now only for an
unbreakable row.

`renderChordproSlide` (singular) goes; `renderChordproPageSvg` stays exactly as
it is, since the export, the incipit and the booklet all still want one picture.
The mixin method `renderChordproSlides` (:503) is renamed `renderChordproPreviewSlides`
to free the name, and builds its flat list of slides first so `addPageControls`
can label "3 of 7" with the count the reader will actually page through.

### 4. The dispatcher

`resources/js/projection-render.js`

`renderRatioPage` stays what it is for the three engraved formats. Beside it:

```js
export async function renderRatioPageSlides(format, pageSource, settings, ratio)
```

— ChordPro answers with `renderChordproSlides`, everything else with
`[await renderRatioPage(...)]`. `renderRatioPages` (:83) maps through it and
flattens. `projection-deck.js:148` changes not at all: `scoreSlides` already
takes a list and already puts the heading on the first slide only, which stays
right when the list is longer than the author's break count.

### 5. Say so

- `docs/chordpro-cheatsheet.md:33` — add `%pagebreak?` to the projection table
  ("új dia, de csak ha nem fér ki"), and replace the sentence promising that a
  fixed ratio shows the sheet as one slide with what now happens: the sheet is
  cut where it was asked to be cut, and where it must be.
- `lang/hu.json` / `lang/en.json` — the clip warning's wording, if it still reads
  as "cut off" rather than "set too large to break".
- `plans/projection-text-breaks.md` — its "Not in scope" note says the soft form
  for scores waits on four engines. One sentence recording that ChordPro, having
  no engine, went first.

### Not in scope

- **`%pagebreak?` for ABC, GABC and Aretino.** The original reason stands
  unchanged: each engine reports overflow differently, and each would need its
  layout re-run against the answer.
- **Multi-column chord sheets on a slide.** `CHORDPRO_RATIO_DEFAULTS` already
  pins `chordproColumns: 1` for every projector ratio — "a second column on a
  projector is a second thing to find" — so a page's rows are packed down one
  column. A setting saved from before that default is honoured as one column on a
  slide rather than refused.
- **Automatic *shrinking* to avoid a break.** Deliberately not: the whole point
  of the tier order is that a screen gets cut rather than set smaller.

## Since: the screen a verse leaves empty

Three things the first cut of this got wrong, found by projecting it:

1. **The size ceiling was a page's.** The chord sheet toolbars capped the size at
   24 pt while `CHORDPRO_RATIO_DEFAULTS` sets 62 pt at 16:9 — the spinner argued
   with the number the editor itself had chosen. The ceiling now follows the
   ratio: 144 pt on a projector, 24 pt on paper.
2. **The column control did nothing on a slide.** `chordproSlidePages` lays one
   column out whatever the setting says, so the control is hidden at a fixed
   ratio rather than left there to be believed.
3. **A verse was kept whole at any price.** `keepWithNext` moved a whole verse to
   the next slide rather than cutting it, leaving the room above it empty, and a
   verse too tall to keep was then free to be cut *anywhere*, including in the
   middle of a line the screen had wrapped.

(3) is the substance. `chordproRows` now marks every row with `splitBefore` — may
a cut fall above this row? — which is true above a line the author wrote and
false above the continuation of one the screen wrapped, or above the line a
section label was written over. `byBlocks` (`soft-pages.js`) then tries three
packings and takes the first that holds: `keepWithNext` as asked; cut at the
`splitBefore` boundaries, which is taken when keeping a verse whole would cost a
slide; and, only for a single written line taller than the whole screen, cut
anywhere, because hiding the end of a sentence is worse than cutting it.

Rows that carry no `splitBefore` — every row `markdownRows` writes — never leave
the first packing, so a projected text row behaves exactly as it did.

## Verification

**Node unit tests**, which is where the substance is — `chordproSlidePages` is
pure given an injected `measure`, exactly as `tests/Unit/score-editor-chordpro.test.mjs`
already injects one:

- `tests/Unit/score-editor-chordpro.test.mjs` — a sheet shorter than the screen
  is one slide and its `%pagebreak?` goes unused; the same sheet on a screen half
  as tall comes to two, cut at the marker; a sheet with two suggestions where one
  cut suffices comes to two slides, not three; a sheet with no markers at all
  that runs over is cut at a verse boundary rather than truncated, with no verse
  split that `keepWithNext` said to keep; a verse cut at its own newline where
  keeping it whole would have cost a slide; a wrapped line whose pieces stay
  together; one written line taller than the screen cut inside itself rather than
  hidden. The toolbar's ceiling and its hidden column control are read off the
  three Blade files, as the point-over-px test beside them already does.
- `tests/Unit/score-editor-page.test.mjs` — `splitPages` keeps `%pagebreak?` for
  ChordPro at a fixed ratio, normalises `%pagebreak169?` to it at 16:9, drops it
  at 4:3, drops it for `abc` at every ratio, and drops it for ChordPro on paper.
- `tests/Unit/soft-pages.test.mjs` — renamed only; every existing case must pass
  untouched, which is the guard that the packer's behaviour did not move.
- `tests/Unit/projection-render.test.mjs`, `tests/Unit/projection-slides.test.mjs`
  — a ChordPro row that overflows now contributes more than one slide to the
  deck, and `slideCounts` says so.

`node --test tests/Unit/score-editor-chordpro.test.mjs tests/Unit/score-editor-page.test.mjs tests/Unit/soft-pages.test.mjs tests/Unit/projection-render.test.mjs tests/Unit/projection-slides.test.mjs`

**Pest** — `php artisan test --compact --filter=ProjectionEditor`, as a regression
guard: the cutting is the browser's job and `ProjectionRenderPayload` must still
hand the source over verbatim.

**By hand** — `npm run build`, open a long chord sheet in the score editor, switch
to 16:9, and watch it become the slides it needs instead of one slide with its
last verse missing. Then `vendor/bin/pint --dirty --format agent`.
