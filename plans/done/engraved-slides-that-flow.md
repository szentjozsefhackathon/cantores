# A Score That Runs Over Becomes Another Slide

## Context

Editing a projection, or offering a score into one from the plan, the same thing
keeps happening: the score is one slide too long. The last system is cut off at the
bottom edge, the clip warning lights up, and the only way out is to open the score,
find the right place, and write a `%pagebreak169` there by hand — for every ratio
the score will ever be shown at.

Text rows and chord sheets stopped doing this. `plans/done/projection-text-breaks.md`
and `plans/done/chordpro-slides-that-flow.md` gave them the four-tier cut in
`soft-pages.js`, and both left the three engraved formats out for one reason, stated
in `score-editor-pages.js:38` and repeated in `renderRatioPage`'s docblock
(`projection-render.js:63`): *each engine reports overflow differently, and each
would need its layout re-run against the answer.*

That reason is weaker than it looks, because **the booklet already solved it**. To
flow a score across printed pages, `musicBlocks` (`booklet-render.js:713`) turns
every one of the three into a list of staff systems, each with its own height:

| format | how the booklet gets systems | where |
| --- | --- | --- |
| ABC | abc2svg emits one `<svg>` per music line | `abcBlocks`, `booklet-render.js:794` |
| GABC | exsurge marks each line `.chantLine`; lifted out by measuring | `gabcBlocks` → `sliceRenderedSvg`, :856 / :932 |
| Aretino | the engine's own `splitRowSVGs` | `aretinoBlocks`, :747 |

A list of systems with heights is exactly what `packSoftPages` consumes. The engines
do not have to agree about overflow; they only have to hand over systems, and they
already do.

The slide renderers are one step from this already. `renderAbcSlide`
(`score-editor-abc.js:383`) *has* the per-line fragments and throws the split away
with `stackSvgs`. `renderGabcSlide` (`score-editor-gabc.js:110`) draws one document
that `sliceRenderedSvg` knows how to cut.

One finding on the way, which this plan fixes as a side effect: **an Aretino slide
that runs long is clipped and never says so.** `renderAretinoSlide`
(`score-editor-aretino.js:68`) passes `canvasHeight`, which makes the engine fix the
SVG's height at the canvas (`renderer.js:1378` in `@aretino-chant/core`), so the
content below is cut off by the viewBox. The `overflows` test there only looks at
*width*, which grows only when a long word runs past the right edge. The docblock
says the engine "holds that height and widens the viewBox instead". That is true of
the width, and not of a chant that is simply too tall.

## Decision

**An engraved page that does not fit is cut between its systems into as many
slides as it needs.** Nothing is shrunk, and a page that fits is left exactly as it
is drawn today.

The same tiers as words and chord sheets, spent by the same packer:

1. **Hard `%pagebreak`** (and `%pagebreak169` etc.) cuts, as today, in `splitPages`.
2. **A page that fits is one slide**, drawn by today's code path, byte for byte.
   This is deliberate. Every deck that looks right today keeps looking the same,
   including Aretino's canvas-height layout and GABC's single document.
3. **A page that does not fit is cut at its own `%pagebreak?`**, at as few as it
   takes (step 4 below; it can ship separately).
4. **A piece that still does not fit is cut between systems**, greedily, filling
   each slide before starting the next.

Below all four, **a single system taller than the screen** stays over-tall and
clipped, and `overflows` says so. That is the one case left where the author has to
act, by making the staff smaller, and it is the only case where the warning is
still true.

### Rules for where a cut may fall

- Every system boundary may be cut (`splitBefore: true`). A system is the smallest
  unit that makes sense on a screen.
- ABC's title/header fragment (the `T:` block abc2svg emits before the first line)
  is `keepWithNext`, so a title never sits alone at the bottom of a slide.
- Tie-breaking is `byBlocks`'s existing rule: take the packing that fills. No verse
  concept applies here, so it reduces to plain greedy filling.

### How a cut slide is drawn

The systems are stacked from the top with `stackSvgs` and framed at the canvas with
`frameSlide`. They are top-aligned, for the reason `fitSlide` already gives
(`slide-frame.js:97`): two consecutive slides of one hymn must start their first
staff at the same height. The gap between systems is whatever the engine put
between them, so a slide cut from a page looks like the top of that page.

### Consequences to state plainly

- **Decks change.** A score that silently lost its last system now comes to two or
  three slides. That is the point of the change, but it moves slide indices, so
  `excluded_slides` entries after it go stale. This is the same staleness
  `ProjectionEditor.php:671` already documents for a moved `%pagebreak`.
- **The heading** still rides on the first slide only (`scoreSlides`,
  `projection-deck.js:153`). `withHeading` still scales the music a little to make
  room for it. That is not new; every first slide pays it today.
- **Authors can still decide.** A `%pagebreak169` placed by hand is spent first and
  is never overridden. The automatic cut only covers what the author left
  unanswered.

## Implementation

### 1. One function per engine that returns systems for a slide

Each engraved format gains a sibling of its `render*Slide` that returns
`Array<{svg, height, keepWithNext?}>` at the slide's canvas width. The booklet's
extraction logic is lifted into these rather than copied, so the booklet and the
slide share one cutter per engine:

- **ABC**, `score-editor-abc.js`: `abcSlideSystems(pageSource, settings, canvas, report)`.
  This is `renderAbcSlide` up to `stackSvgs` (:405), returning the fragments with
  `ensureAbcSvgViewBox` already applied. `applyAbcSvgStyle` moves to the per-slide
  stack, and the `report` source map passes through unchanged, so click-to-edit
  keeps working in the score editor.
- **GABC**, `score-editor-gabc.js`: `gabcSlideSystems(pageSource, settings, canvas)`.
  It calls `renderGabcToSvgMarkup` and then `sliceRenderedSvg(markup, '.chantLine', measuringHost())`.
  `sliceRenderedSvg` and `scopeLiftedMarkup` move out of `booklet-render.js` into a
  small `svg-slice.js` that both callers import. `measuring-room.js` already
  provides the laid-out host it needs.
- **Aretino**, `score-editor-aretino.js`: `aretinoSlideSystems(pageSource, settings, canvas, ratio)`.
  It renders with `aretinoProjectorOptions(ratio)` **minus `canvasHeight`**, so the
  height comes from the content, and then calls `splitRowSVGs`.

### 2. Tell the truth about overflow first

Each `render*Slide` keeps its current fast path. The overflow check it already
makes decides whether to take the slow path:

- ABC, `height > canvas.height + tol` (:409). Unchanged.
- GABC, `contentHeight > canvas.height + tol` (:124). Unchanged.
- Aretino gains a height test. Rendering without `canvasHeight` and reading the
  content height from the viewBox (or from the `aretino-rows-end` marker) replaces
  today's blind spot. The width test stays, since a word that runs past the edge is
  a separate problem that splitting systems cannot fix.

### 3. The dispatcher: every format may answer with several slides

`projection-render.js`: `renderRatioPageSlides` (:105) already returns a list for
ChordPro. For the other three it becomes:

```js
const first = await renderRatioPage(format, pageSource, settings, ratio);
if (!first.overflows) { return [first]; }

const systems = await slideSystems(format, pageSource, settings, ratio);
return packSoftPages(systems.map(asRow), canvas.height)
    .map((page) => stackSystemsSlide(page, canvas, format, settings));
```

`stackSystemsSlide` is new in `slide-frame.js`. Its `overflows` is
`page.height > canvas.height + SLIDE_FIT_TOLERANCE`, which is now true only for a
lone over-tall system. The docblocks of `renderRatioPage` and `renderRatioPageSlides`
are rewritten: "nothing here shrinks an engraving" stays true, and "that answer
belongs to the author" becomes "the author answers first; what they leave
unanswered is cut between systems".

`projection-deck.js` does not change. `scoreSlides` already takes a list.

### 4. `%pagebreak?` for engraved formats (can ship second)

`splitPages` (`score-editor-pages.js:113`) keeps soft breaks for all four formats,
not just ChordPro, and the `PAGE_BREAK` docblock is rewritten to match. Each soft
segment is rendered separately with the header re-prefixed, as `splitPages` already
does for hard pages. The first system of each later segment gets
`breakBefore: 'soft'` before packing.

Open question for this step: the re-prefixed ABC header includes `T:`, so every
segment draws the title again. Hard pages do this today as well. The options are
(a) keep that consistency, or (b) drop the title fragment from every segment after
the first. **(b) is recommended**, because a suggested break that the packer does
not spend should not leave a duplicate title behind.

### 5. The score editors show the same cut

The three editor previews call `render*Slide` directly
(`score-editor-abc.js:591`, `score-editor-gabc.js:217`, `score-editor-aretino.js:189`).
They switch to `renderRatioPageSlides`, and their page controls count the flattened
list, as `renderChordproPreviewSlides` already does. The goal is that the author
sees in the editor exactly the slides the deck will show.

### 6. Say so

- `docs/abc-felhasznaloi-utmutato.md:347`, `docs/aretino-felhasznaloi-utmutato.md:1076`
  and the GABC equivalent: a score that does not fit is now cut between systems,
  and `%pagebreak?` is listed as a suggested break (after step 4).
- `lang/hu.json` / `lang/en.json`: the clip warning's text says "a single system is
  taller than the screen" rather than "does not fit".

## As built

What changed on the way from this plan to the code:

- **An automatic cut is pointed at.** A slide that begins at a cut the packer
  made on its own, rather than at a `%pagebreak` or a spent `%pagebreak?`,
  carries `autoSplit` (`startsAtAutomaticCut` in `soft-pages.js`). The score
  editor puts a blue note above that slide, and the projection editor's contact
  sheet shows an "Auto split" badge beside its number. Both name the break for
  this ratio (`%pagebreak169` etc.), because the reason to show the note is that
  a break placed by hand usually cuts better. ChordPro gets the same flag, since
  its cuts at verse boundaries are just as automatic. Text rows do not: breaking
  a rubric wherever it has to is their normal case.
- **Names.** Each engine exposes `render{Abc,Gabc,Aretino}Slides`, and the
  packing and stacking shared by all three live in the new `slide-systems.js`.
  `sliceRenderedSvg`, `scopeLiftedMarkup` and `svgHeight` moved to
  `svg-slice.js`, and the booklet imports them from there.
- **Suggestions are opt-in for engraved formats.** `splitPages` and
  `splitPagesMapped` take a `keepSoft` argument, which defaults to ChordPro only.
  The incipit and the export read `splitPages` too, and an engine handed a
  `%pagebreak?` line would draw it. `softSegmentRanges` gives the cut as ranges,
  so the ABC editor's mapped source is cut the same way and click-to-edit still
  works on every slide.
- **Aretino shows every clef on a page it cuts.** Projector defaults hide the
  repeated clef, which leaves slide 2 onwards starting on a staff with no clef.
  A page that has to be cut is therefore engraved with `hideRepeatClef` off. A
  page that fits is unchanged.
- **Aretino's height blind spot** is fixed in both paths: `renderAretinoSlide`
  measures an unconstrained engraving too, so a single-slide caller also sees
  `overflows`.
- **Verified in headless Chromium** with a harness around `renderRatioPages`.
  Tall ABC, GABC and Aretino each came to several slides with nothing lost, the
  title stayed with the music under it, a spent `%pagebreak169?` was not flagged
  as automatic, and a page that fits came back unchanged.

## Rejected alternatives

- **Shrink to fit.** Rejected for the same reason as for words: a score shrunk to
  60 % is unreadable from the back pew, and neither of the earlier plans did it.
- **Re-engrave at a smaller staff until it fits.** Same objection, and it would also
  override a size the author tuned for that ratio.
- **Always draw through the systems path, even when the page fits.** That is
  simpler, but it would change every Aretino slide (canvas-height layout versus a
  top-packed stack) and every GABC slide (one document versus sliced lines),
  including in decks nobody is complaining about. The cost of the fast path is one
  `if`.
- **Store the cut positions.** Rejected: the slide count is always read back off
  the source (`projection-deck.js:19`), and a stored cut would go stale the moment
  a staff size changed.

## Files touched

| file | change |
| --- | --- |
| `resources/js/score-editor-abc.js` | `abcSlideSystems`; `renderAbcSlide` built on it |
| `resources/js/score-editor-gabc.js` | `gabcSlideSystems` |
| `resources/js/score-editor-aretino.js` | `aretinoSlideSystems`; height-based overflow |
| `resources/js/svg-slice.js` (new) | `sliceRenderedSvg`, `scopeLiftedMarkup`, moved from the booklet |
| `resources/js/booklet-render.js` | imports the moved functions; no behaviour change |
| `resources/js/projection-render.js` | engraved formats may return several slides |
| `resources/js/slide-frame.js` | `stackSystemsSlide` |
| `resources/js/score-editor-pages.js` | soft breaks kept for all formats (step 4) |
| editor previews (3 files above) | use `renderRatioPageSlides` |
| `docs/*-felhasznaloi-utmutato.md`, `lang/*.json` | wording |

## Verification

**Node unit tests**:

- `tests/Unit/projection-render.test.mjs`: an ABC page that fits comes back as one
  slide, identical to `renderRatioPage`; the same page at a staff size twice as
  large comes back as two, cut between systems, and neither has `overflows`; a
  single system taller than the canvas comes back as one slide with `overflows`.
  The same three cases apply to Aretino. GABC needs a laid-out host, so it is
  covered in the browser check below.
- `tests/Unit/score-editor-aretino.test.mjs`: a chant taller than the canvas now
  reports `overflows` (the regression test for the blind spot).
- `tests/Unit/soft-pages.test.mjs`: rows with `splitBefore: true` everywhere pack
  greedily, and a `keepWithNext` title row is never left alone at the bottom.
- `tests/Unit/booklet-blocks.test.mjs`, `tests/Unit/booklet-abc-blocks.test.mjs`:
  must pass untouched, as the guard that moving the slicer did not change the
  booklet.
- `tests/Unit/score-editor-page.test.mjs` (step 4): `splitPages` keeps
  `%pagebreak?` for `abc` at a fixed ratio and drops it on paper.

`node --test tests/Unit/projection-render.test.mjs tests/Unit/score-editor-aretino.test.mjs tests/Unit/soft-pages.test.mjs tests/Unit/booklet-blocks.test.mjs tests/Unit/booklet-abc-blocks.test.mjs tests/Unit/score-editor-page.test.mjs tests/Unit/projection-slides.test.mjs`

**Pest**, as a regression guard: `php artisan test --compact --filter=ProjectionEditor`.
The payload must still hand the source over verbatim.

**By hand**: `npm run build`, then offer a long hymn into a 16:9 projection for each
of ABC, GABC and Aretino. Each should come to as many slides as it needs, with no
clip warning, and the score editor at 16:9 should show the same slides. Then
`vendor/bin/pint --dirty --format agent`.
