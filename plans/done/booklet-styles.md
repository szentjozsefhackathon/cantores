# Booklet Styles

## Context

A booklet already unifies the face every score is set in. `unifiedFont()` writes
`geometry.textFont` into all four renderers, and the reason is written down in
`booklet-settings.js`:

> Two faces have different widths and different weights, and the balance between
> a staff and the lyrics under it that looks right in one is wrong in the other —
> so a booklet in which each score keeps the face its author happened to pick
> cannot be balanced at all, however carefully its sizes are unified.

The same docblock then says the staff-to-lyrics gap is *not* unified: it is the
author's `abcLyricFirstSkip`, travelling with the score. Those two paragraphs
contradict each other, and the second one is the one that is wrong. A booklet
that imposes a face while leaving the spacing that face needs on each score is
imposing half a decision.

The `lyricfirstskipfac` patch took out most of the drift — the gap is anchored on
the bottom staff line now, and counted in the face's own ascent, so it no longer
wanders from system to system or from face to face by accident. What is left is
real: three faces genuinely want three different balances, judged by eye.

| face         | staff → lyrics | lyric line spacing |
|--------------|----------------|--------------------|
| Alegreya     | 1.4            | 0.9                |
| Merriweather | 1.5            | 1.0                |
| EB Garamond  | 1.4            | 0.8                |

Read in ems, the first column is nearly one number — 1.42, 1.48, 1.41 em of
ascent — which is the anchor fix doing its work. The second is not: 1.23, 1.26
and 1.04 em of line box. EB Garamond wants its stanzas a sixth tighter than
Alegreya does, and no unit conversion makes that go away, because it is taste
rather than arithmetic. So the numbers have to be stored per face somewhere, and
the only place that knows which face is in play is the booklet.

## Decision

**A booklet is set in one of three named styles. A style is the booklet's whole
typography — the face and every number that has to agree with it — applied in one
press.** Choosing a style overwrites the booklet's global typographic settings
and touches nothing else: not the paper, and not one per-score override.

| style      | Hungarian    | face         | for                                          |
|------------|--------------|--------------|----------------------------------------------|
| `hymnal`   | Énekeskönyv  | Alegreya     | the default — a parish songbook, at home with a chant and a modern hymn alike |
| `modern`   | Modern       | Merriweather | drawn for screens: heavier, widest apertures, best on a photocopy or in bad light |
| `graduale` | Graduále     | EB Garamond  | anything meant to read as a chant book        |

Three, not a palette, and named after the book each one is rather than after its
face. A cantor choosing between *Énekeskönyv* and *Graduále* is choosing what the
booklet should feel like; a cantor choosing between *Alegreya* and *EB Garamond*
is being asked a question about typefaces they did not come here to answer.

### What a style owns

Everything typographic, nothing physical:

```
owned by the style   text_font              the face
                     lyric_size_pt          how big the type is
                     staff_height_mm        how big the staff is beside it
                     heading_scale          how big a heading is beside the lyrics
                     abc_staff_sep          how tightly the systems stack
                     abc_lyric_first_skip   staff → first lyric line     (new)
                     abc_lyric_skip         between stacked lyric lines  (new)
                     the same gap in the other three engines — see below

left alone           page_size  orientation  margin_mm
                     every booklet_scores.settings_override
```

The page is not part of a style because it is not part of the look. A cantor
picks A5 once, because that is the paper in the printer, and then tries all three
styles on it. A style that reflowed A5 into A4 to show a different face would be
unusable for the one thing styles are for: seeing which one suits this booklet.

Per-score overrides survive for the same reason they exist. "This hymn is sung a
third lower", "widen this one so the line stops breaking" and "take this staff
down so the page holds" are answers to a particular score, and none of them
becomes wrong because the booklet changed face. A cantor who has spent ten
minutes fitting a service onto four sides can try all three styles without losing
that work — which is what makes the feature worth having rather than merely
tidy.

### The table today is mostly one row

Honestly stated, because it shapes the implementation:

| style      | face         | lyric pt | staff mm | heading × | staff sep | staff → lyrics | line spacing |
|------------|--------------|----------|----------|-----------|-----------|----------------|--------------|
| `hymnal`   | Alegreya     | 10.5     | 5        | 0.9       | 25        | 1.4            | 0.9          |
| `modern`   | Merriweather | 10.5     | 5        | 0.9       | 25        | 1.5            | 1.0          |
| `graduale` | EB Garamond  | 10.5     | 5        | 0.9       | 25        | 1.4            | 0.8          |

Only two columns actually differ, because `opticalLyricSizePt()` already makes
one lyric size read the same in every face — the sizes were unified two features
ago and do not need unifying again. The other five columns are in the table
anyway, because the table is where a number goes once somebody has judged it by
eye, and a style that owned only the two known columns would have to be widened
the first time anyone decides Graduále wants a taller staff.

The other two engines have the same gap under another name, and both keep their
engine's own number in all three styles for now:

| key                                    | engine  | all three styles          |
|----------------------------------------|---------|---------------------------|
| `minSpaceBelowStaff`                   | exsurge | 0                         |
| `aretinoLyricDistance`                 | Aretino | 0.2 staff spaces          |
| `aretinoLyricMinStaffDistance`         | Aretino | 0.75 staff spaces         |

GABC's is already a score setting and moves to the style at the zero it has
today. Aretino's two are not settings at all yet — `aretinoBlocks()` never passes
them, so the renderer falls through to `METRICS` in `@aretino-chant/core`. They
are plumbed through with the rest, so that the table is where those numbers are
written rather than a default in a dependency.

Worth knowing before anyone tunes them: **Aretino places lyrics the way abc2svg
used to**, and its own comment says so — "lyrics normally sit `lyricDistance`
below the lowest note", with `lyricMinStaffDistance` as a floor under the staff
line. That is ink-anchored with a staff-line floor, the exact mirror of what ABC
now does, so the same per-system wobble is there to be found in an Aretino chant
with a low note in one line and not the next. Nothing needs doing about it today
— chant sits high in the staff and the effect is small — but if a style ever
wants a distinctly tighter Aretino gap, that is the thing that will fight it, and
the fix is the one already in `docs/vendor-patches.md`.

### The face picker becomes the style picker

The booklet's toolbar loses its font select and gains a style select. Two
consequences, both wanted:

- **The style is never ambiguous.** Each style has a face of its own, so the
  booklet's face says which style it is and no `style` column has to be stored
  and kept from going stale. A cantor cannot put the booklet in a state where the
  face says one thing and the spacing another.
- **Screen faces leave the booklet.** Inter and Barlow Condensed are projector
  faces — `SELECTABLE_FONTS` says as much: "which exist for projector slides
  rather than for pages". They stay in the score editor, where a score in
  responsive or 16/9 mode is drawn for a screen and should have them. A booklet
  is paper, and now cannot be set in one by accident.

The two new spacing knobs go on the toolbar beside `abcStaffSep`, which set the
precedent for an ABC-specific knob on a format-blind bar. That bar is getting
full, and if it comes to a choice, these two are better hidden behind the style
than the staff separation is: the style is meant to have got them right.

### On a screen

The loan view keeps the booklet's typography, as it does today, and its font
select becomes a style select for the same reason the editor's does. Without
that, a reader picking Merriweather on a phone gets Merriweather's lyrics over
Alegreya's gaps — reintroducing on the reader's screen exactly the fault the
`lyricfirstskipfac` work removed from paper.

This is what responsive rendering already means here and it does not change: the
booklet's typography, laid out for the width of the screen in hand, at the size
that reader's eyes want. A score's own settings still reach the page through
layer 2 — its note spacing, its transposition, whether it hides its clef — and
only the typography is overruled, on screen exactly as on paper. A score's own
face is what it is drawn in when it is read *as a score*; inside a booklet it is
drawn as one page of one book.

The spacing a reader sees is therefore the style's, never the cantor's
page-fitting nudge and never the author's own number. The nudge is dropped on the
way to the screen — see §3, which is where that follows from. The author's number
is not consulted for the same reason the author's face is not: it was judged
against the face they engraved in, and the booklet is not in that face. Nor is
there a screen-specific version of it to reach for instead — `effectiveRatioKey()`
maps both `responsive` and `auto` onto `paper`, so a score has one authored
bucket, tuned for a sheet of paper the reader is not holding.

## Implementation

### 1. The style table

`app/Support/BookletStyles.php`, next to `BookletSettingFields` and modelled on
it: one private constant holding the table above, keyed by style, plus
`all()` for the picker, `defaults(string $style): array` for the columns to
write, `keys()` for validation and `forFont(string $font): string` for the
migration and the reader. Labels go through `__()`; the Hungarian names go in the
translation file.

`BookletSettingFields::SELECTABLE_FONTS` stays as it is — it is the score
editor's list and the score editor still wants all five. The booklet and the
reader stop calling it; they call `BookletStyles::all()`. `EMBEDDABLE_FONTS` is
untouched: a booklet saved in Inter before this exists still has to validate and
still has to print.

### 2. Two columns

Migration adding `abc_lyric_first_skip` (float, default 1.4) and
`abc_lyric_skip` (float, default 0.9) to `booklets`, fillable and cast on the
model, and included in `Booklet::geometry()`.

Backfill: every existing booklet gets the pair from
`BookletStyles::forFont($booklet->text_font)`, falling back to `hymnal` for a
booklet set in a face no style claims (Inter or Barlow Condensed). Those
booklets keep their face — nothing rewrites `text_font` — and simply get sensible
spacing under it; the picker will show them the nearest style, and choosing any
style moves them onto a booklet face.

**This changes how existing booklets print.** The spacing was each author's until
now, and becomes the booklet's. That is the whole point, but it means a cantor
who tuned one score's gap in the score editor and saw it in their booklet will
see the booklet's number instead — until they set it on the score's own row,
where layer 4 has always been able to say it.

### 3. Layer 3 gains the pair

In `booklet-settings.js`, `unifiedSettings('abc', geometry)` gains
`abcLyricFirstSkip` and `abcLyricSkip` from the geometry, and the docblock's
paragraph about the gap travelling with the score is replaced by the reason it no
longer does.

**`travellingOverride()` needs no change, and that is the interesting part.** It
derives its page-bound set from `Object.keys(unifiedSettings(...))`, so the two
keys join it by themselves and a cantor's per-score spacing override stops at the
paper. That is right, and it is right for a reason worth writing down, because it
looks at first like an accident.

An override means different things depending on which layer it is overriding.
While the gap is the author's — layer 2 — overriding it in a booklet means "this
author's gap is wrong under the face my booklet imposes", which is a statement
about the music and would deserve to travel to a screen. Once the style owns the
gap, the style has already got the face right, and the only reason left to depart
from it is the page: this hymn runs two lines over, tighten it. Moving the number
from layer 2 to layer 3 is what turns its override into a page-fitting act.

So the rule that function already applies is self-maintaining rather than
coincidental: whatever the booklet computes for itself, an override of it is by
definition a departure made for the booklet's own page. The existing test for
what survives the trip to a screen gets a case for the pair anyway, to pin the
behaviour against someone later reading the derivation as a bug.

### 4. The two pickers

- `booklet-editor.blade.php`: the font select becomes a style select bound to a
  `style` property on `BookletEditor`. Setting it writes all seven columns
  through `BookletStyles::defaults()` and saves once, so one press is one render.
  The property is validated against `BookletStyles::keys()`; `textFont` stops
  being a public property and `rules()` loses its `Rule::in(fontOptions())` case.
  Two number fields join `abcStaffSep`, with the score editor's own icons —
  `align-vertical-space-around` and `align-vertical-space-between`.
- `booklet-loan-view.blade.php`: the same swap. `readerGeometry()` inherits the
  pair from the booklet and replaces all three values together when the reader
  picks a style, in place of today's lone `textFont`. Per-device stored settings
  keep a `style` where they kept a `textFont`; a stored face maps through
  `forFont()` on read, so nobody's phone forgets what they set.

### 5. Tests

- `BookletStylesTest` — every style names an embeddable face; `forFont()` is the
  inverse of the table and falls back to `hymnal`; `defaults()` covers exactly
  the seven owned columns and no others.
- `BookletEditorTest` — choosing a style writes all seven columns; leaves
  `page_size`, `orientation` and `margin_mm` where they were; leaves every
  `settings_override` byte for byte. That last assertion is the promise this
  whole feature makes.
- `booklet-settings.test.mjs` — `unifiedSettings('abc', …)` carries the pair, and
  `travellingOverride()` therefore drops an override of either one while still
  keeping a transposition. Named for what it is protecting: a spacing override is
  a page-fitting nudge, and a screen is not that page.
- `booklet-reading.test.mjs` — a reader's style swaps face and both spacings
  together, and inherits all three when they have chosen nothing.

## Open

- **Nobody has judged the other engines by eye.** GABC's and Aretino's gaps go
  into the table at the numbers they already draw at, which is the right place
  for them and not yet the right value for any of them — they are as
  face-dependent as ABC's pair was. ChordPro's leading is a third case and has no
  knob at all today. All three are a column each when somebody has looked at
  them, and nothing in this design changes to add one.
- **A fourth style** costs one row. The three faces left in the app are all
  claimed by one, so a fourth means a new face — and the last retirement
  (`2026_09_10_115930_retire_lora`) is what that costs when it goes the other
  way.
