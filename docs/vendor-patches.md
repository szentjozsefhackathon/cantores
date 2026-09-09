# Vendor Patches

Local modifications to third-party files that are checked into this repo rather
than pulled from a package manager. When upgrading one of these libraries,
re-apply every patch listed here.

Each in-place code change is tagged with a `VENDOR PATCH <slug>` comment so it
can be found with:

```sh
grep -n "VENDOR PATCH" public/js/abc2svg-1.js
```

## Browser caching

A vendored file keeps one URL for its whole life, unlike Vite's content-hashed
output, and Apache serves `public/js` with no `Cache-Control` at all — so a
browser falls back to heuristic freshness and may reuse its copy for hours
without ever revalidating. A patch that adds a *format directive* fails
invisibly in that browser: abc2svg ignores a `%%` directive it does not know
without a word to the console, so the knob simply does nothing.

The views therefore load the file through `App\Support\VendorAsset::url()`,
which appends the file's mtime, giving every edit a URL of its own. Load a
vendored asset that way rather than with a bare `asset()`; `VendorAssetTest`
guards it. When testing a fresh patch by hand, hard-reload
(<kbd>Ctrl</kbd>+<kbd>Shift</kbd>+<kbd>R</kbd>) anyway — the old URL may still
be in the cache from before.

---

## abc2svg — `public/js/abc2svg-1.js`

- Upstream: <https://chiselapp.com/user/moinejf/repository/abc2svg> (LGPL3+)
- Vendored version: `v1.23.1-14-f6aabdbce0` (`abc2svg.vdate` 2026-05-13)
- License: LGPL3+ — modifications must stay under the same terms.

### Patch: `huchords` — Hungarian chord-symbol spelling (appended)

**What:** A small self-registering module concatenated at the end of the file,
between the `--- VENDOR PATCH huchords ---` / `--- end VENDOR PATCH huchords ---`
comment markers. It is not upstream code — it is ours, kept in the vendored file
because it has to run inside the engraver.

It registers `abc2svg.mhooks.huchords`, which the core invokes once per render.
The hook wraps `gch_build` and, for every guitar chord, rewrites the root note
and the slashed bass note onto the fixed palette

```
C  Db  D  Eb  E  F  Gb  G  Ab  A  Bb  H
```

by pitch class. It runs *after* abc2svg's own chord transposition
(`gch_tr1`), so it also normalises whatever spelling the transposition produced
(`B#`, `Cb`, `E#`, double accidentals, …) down to that palette.

**Why:** The app writes chord symbols in Hungarian (`H` = B natural, `B` = B
flat) and the score editor transposes with `%%transpose`. abc2svg parses and
transposes chord roots as English note names and spells the result relative to
the destination key, so a semitone up comes out as `B#` or `Cb` — not how a
chord chart reads, and never the plain "next name" the user expects. Neither
`%%transpose n` (sharps) nor `%%transpose nb` (flats) gets every case right, and
`gch_tr1` is a closure-local function that no hook can replace, so the spelling
has to be fixed *after* it runs. The `%%chordnames` directive (and the upstream
`chordnames-1.js` module) only remaps display names — it cannot help transposed
chords — so it is not used.

The `H` ↔ English `B` rewrite on the way *in* (so abc2svg can parse and
transpose Hungarian roots at all) is `hungarianChordsToAbc()` in
`resources/js/score-editor-abc.js`; that function is also called from
`booklet-render.js` and `abc-mini-editor.js`.

**Re-apply:** Re-paste the block between the markers. It is version-independent
as long as the core still calls `abc2svg.mhooks.*` and passes chord symbols
through `Abc.prototype.gch_build` with `s.a_gch[i].text`.

**Consumers:** `resources/js/score-editor-abc.js` (`hungarianChordsToAbc`),
`resources/js/booklet-render.js`, `resources/js/abc-mini-editor.js`.
Covered by `tests/Unit/abc-hungarian-chords.test.mjs`.

### Patch: `lyricskipfac` — configurable distance between lyric lines

**What:** Adds a `%%lyricskipfac <factor>` format parameter that controls the
vertical advance between successive `w:` lyric lines. Upstream hardcodes the
factor as the literal `1.1` inside the closure-local `draw_lyrics()`, which is
not on `Abc.prototype` and not reachable through any module hook, so it cannot
be monkey-patched from the appended block — it has to be an in-place edit.

Three edit sites, each tagged `/*VENDOR PATCH lyricskipfac*/`:

1. **`cfmt` defaults object** (search `lineskipfac:1.1,`) — added
   `lyricskipfac:1.1,` so the default matches upstream's former literal.
2. **`Abc.prototype.set_format`**, numeric-parameter `switch` (search
   `case"lineskipfac":`) — added `case"lyricskipfac":` so the value is parsed as
   a non-negative float like `lineskipfac`.
3. **`draw_lyrics()`** — hoisted `var lsf=tsfirst.fmt.lyricskipfac||1.1` at the
   top of the function and replaced both `a_h[j]*1.1` occurrences (the
   below-staff and above-staff loops) with `a_h[j]*lsf`.

**Why:** `%%lineskipfac` applies only to `%%text`/`%%words`/history blocks,
never to `w:` lyrics, and `%%vocalspace` is a floor under the staff → first-lyric
gap, not a setting for the stanzas. Multi-stanza scores needed tighter/looser
inter-line spacing without changing the vocal font size.

**Re-apply:** If upstream still hardcodes `1.1` in `draw_lyrics`, repeat the
three edits above. If upstream has since added its own parameter for this, drop
this patch and switch the preambles to the upstream name.

**Consumers:** `%%lyricskipfac` is emitted in the ABC preambles built by
`resources/js/score-editor-abc.js` and `resources/js/booklet-render.js`, from
the `abcLyricSkip` setting (default `1.1`, floor `0.5`).
Covered by `tests/Unit/abc-lyricskipfac.test.mjs`.

### Patch: `lyricfirstskipfac` — configurable staff → first-lyric distance

**What:** Adds a `%%lyricfirstskipfac <factor>` format parameter for the advance
from the music down to the *first* `w:` line, as a multiple of that line's own
height. It splits the below-staff loop of `draw_lyrics()` in two: the first line
answers to this factor, every line after it to `%%lyricskipfac`.

Four edit sites, each tagged `/*VENDOR PATCH lyricfirstskipfac*/`:

1. **`cfmt` defaults object** (search `lyricskipfac:1.1,`) — added
   `lyricfirstskipfac:1.1,` in front of it, so an unset factor is the same
   `1.1` upstream used for every lyric line.
2. **`Abc.prototype.set_format`**, numeric-parameter `switch` — added
   `case"lyricfirstskipfac":` beside `case"lyricskipfac":`.
3. **`draw_lyrics()`** — hoisted `var lff=tsfirst.fmt.lyricfirstskipfac||1.1`
   next to `lsf`.
4. **`draw_lyrics()`, below-staff loop** — `a_h[j]*lsf` became
   `a_h[j]*(j?lsf:lff)`.

The above-staff loop is untouched: there the line nearest the staff is drawn
first, at `topbar + vocalspace`, with no advance in front of it, so nothing
there depends on either factor.

**Why:** the two distances were one knob. `%%lyricskipfac` moved the first line
away from the staff as well as the stanzas apart, and the parameter that is
supposed to own the staff gap, `%%vocalspace`, is only a floor
(`if (y > -vocalspace) y = -vocalspace`) — it can push the lyrics further down
but never pull them closer than the lowest stem hangs, which in practice made
anything under ~15pt do nothing at all. This factor counts from that lowest ink
instead, so a value under `1` is how you get the lyrics in tight.

**Re-apply:** repeat the four edits above; site 4 is the one that carries the
behaviour. If upstream has since split the first line off itself, drop this
patch and switch the preambles to the upstream name.

**Consumers:** `%%lyricfirstskipfac` is emitted in the ABC preambles built by
`resources/js/score-editor-abc.js` and `resources/js/booklet-render.js`, from
the `abcLyricFirstSkip` setting (default `1.1`, floor `0.5`), which replaced the
`abcVocalSpace` knob — the preambles now pin `%%vocalspace 0` and leave the
staff gap to this one. Covered by `tests/Unit/abc-lyricfirstskipfac.test.mjs`.
