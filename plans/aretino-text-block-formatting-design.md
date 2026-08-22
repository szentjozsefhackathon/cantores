# Aretino Text Block Formatting — Review and Design

## Scope

This document reviews the automatic formatting of `W:` lines (psalm verses) and
proposes how to support other kinds of text — flowing prose, hymn stanzas,
rubrics — in the same score source.

The feature does **not** live in Cantores. It is implemented in
`@aretino-chant/core` (currently `0.21.1`, MPL-2.0):

| Concern | File |
|---|---|
| `W:` line classification, block continuation | `packages/core/src/parser.js:187` |
| Block → section attachment | `packages/core/src/items.js:35` |
| Wrapping, indentation, leading, SVG output | `packages/core/src/verse.js` |
| Call site (after the last staff row of a section) | `packages/core/src/renderer.js:1190` |

Cantores consumes it through `resources/js/score-editor-aretino.js`
(`renderAretino`), the incipit path in `resources/js/score-editor.js:620-640`,
and the server-side PDF pipeline (`app/Services/SvgToPdfConverter.php`,
librsvg).

## 1. How it behaves today

**Parsing.** A line starting with `W:` opens a text block. Every following line
without a prefix is appended to that block as an *explicit line break*. A blank
line closes the enclosing section. Several `W:` lines in a row are several
blocks.

**Layout** (`verse.js:99` `renderVerseLines`), all values derived from
`ctx.lyricSize` (`fs`):

| Property | Value | Hardcoded? |
|---|---|---|
| Column | `leftMargin` → `width - rightMargin` | yes |
| Indent for continuation lines | `leftMargin + 2·fs` | yes |
| Applied to | explicit breaks **and** auto-wrapped lines | yes |
| Line leading inside a block | `1.1·fs` | yes |
| Leading before a new block | `1.3·fs` | yes |
| Leading below the staff | `1.1·fs` from section content bottom | yes |
| Alignment | left, ragged right, no hyphenation | yes |
| Font | `ctx.textFont` at `ctx.lyricSize`, `fill="#000"` | yes |

So exactly one typographic shape is available: **hanging indent from the second
line**. That is correct for psalm verses and responsories, and wrong for
everything else.

## 2. Where it falls short

| Text kind | What is wanted | What happens now |
|---|---|---|
| Psalm verse / responsory | 1st line flush, rest indented | correct |
| Flowing prose (rubric, instruction, prayer) | every line flush left; source line breaks are *soft* (reflow) | source line breaks are honoured **and** indented; the paragraph looks like a psalm |
| Hymn stanza | every verse line flush left; only *overflow* (auto-wrapped) lines indented; stanza number hanging | every verse line after the first is indented, so real verse lines and overflow lines look identical |
| Rubric | usually smaller / italic / red, tighter block | only via inline `\red{…}` `<…>` markup on every line |

There is also no way to change the numbers (indent width, leading, column
width, block gap) per document or per host — a projector slide and a printed
booklet get identical spacing.

## 3. Defects found while reviewing

All four are confirmed against `0.21.1` (`verse.js`); the first two are visible
to users today.

### 3.1 `~` is not unbreakable in `W:` text

`verse.js:36` does `lineText.replace(/~/g, ' ')` *before* word splitting, so the
tilde becomes an ordinary breakable space. The syntax reference documents the
opposite (§14: "`~` — renders as a literal (non-breaking) space", with a `W:`
example `(unbreakable~space)`).

Repro — `renderVerseLines(ctx, [['xxxxxxxxxx yyyy~zzzz']], 0, 100, 0)` breaks
between `yyyy` and `zzzz`.

Fix: replace `~` with U+00A0 (NBSP) instead of a plain space. The word
splitter already only breaks on ASCII space ("NBSP stays within words", `verse.js:52`), so nothing else changes.

### 3.2 Inline glyphs are silently dropped in `W:` text

`\'` (stress/ictus), `\b`, `\n`, `\#` produce segments with `text: ''` and a
`glyph` field (`text.js:196`). `wrapVerseText` builds its character array by
iterating `seg.text` (empty → no characters), `charsToSegments` does not carry
the `glyph` field, and `verse.js` renders with `renderSegments`, which ignores
glyphs anyway.

Repro — `W: \'accent \b flat \R resp` renders as `accent flat ℟ resp`: the
stress mark and the flat are gone without warning. The stress mark is exactly
the glyph psalm pointing needs.

Fix: treat a glyph segment as an unbreakable one-character "word" atom, keep the
`glyph` field through `charsToSegments`, and render each display line with
`renderMixedLabel` (which already positions glyph paths and text runs) instead
of `renderSegments`. `renderUnderlines` already accounts for glyph advances.

### 3.3 Verse `<text>` elements carry no class and no source span

Lyrics go through `wrapSrc(...)` with `aretino-lyric aretino-lyric-line` classes
and source offsets; verse lines get a bare `<text>`. Two consequences downstream:

- The editor cannot map a caret position to rendered verse text — the toolbar
  scans backwards for a `W:` line instead (`packages/editor/src/toolbar.js:749`,
  "Verse nodes have no source span").
- Cantores identifies verse lines by `querySelectorAll('text')` in
  `cropAretinoVerseToFirstLines` (`resources/js/score-editor.js:100`), which will
  match any future text element.

Fix: emit `class="aretino-verse aretino-verse-line"` and, when `sourceMap` is
on, the same `data-src-*` attributes lyrics use. This needs source offsets on
the parser's verse item (see §4.4).

### 3.4 Text-only content produces no row markers

`splitRowSVGs` slices the SVG at `<!-- aretino-row … -->` comments, which are
emitted per staff row only. A score consisting only of `W:` blocks yields no
markers, so `renderFirstRow` returns `null` and Cantores falls back to rendering
the whole SVG and cropping it by hand (`score-editor.js:628-639`). The same gap
means a long text block cannot be split across projector pages.

Fix: emit a row marker per text block (or per *n* display lines), so text blocks
participate in row splitting and pagination like staff rows.

## 4. Proposal

### 4.1 One concept, several styles

Keep `W:` as **the** text-block tag and make the *typography* a named style. The
knobs behind a style:

| Knob | Meaning | Unit |
|---|---|---|
| `continuation` | `break` = honour source line breaks, `flow` = join lines and reflow | — |
| `firstIndent` | indent of the block's first line | em of `lyricSize` |
| `breakIndent` | indent of an honoured explicit break | em |
| `wrapIndent` | indent of an auto-wrapped line | em |
| `lineHeight` | baseline distance inside a block | em |
| `blockGap` | baseline distance to the next block | em |
| `align` | `left` \| `justify` \| `center` | — |
| `hangMarker` | pull a leading `1.`/`℣`/`℟` into the margin, align text after it | bool |
| `fontScale`, `italic`, `color` | inline style of the whole block | — |

Presets:

```js
const TEXT_STYLES = {
    // current behaviour, unchanged default
    psalm:  { continuation: 'break', firstIndent: 0, breakIndent: 2, wrapIndent: 2,
              lineHeight: 1.1, blockGap: 1.3, align: 'left' },
    // paragraph: source line breaks are soft, nothing is indented
    prose:  { continuation: 'flow',  firstIndent: 0, breakIndent: 0, wrapIndent: 0,
              lineHeight: 1.25, blockGap: 1.5, align: 'left' },
    // hymn: verse lines flush, only overflow is indented, number hangs
    stanza: { continuation: 'break', firstIndent: 0, breakIndent: 0, wrapIndent: 1.5,
              lineHeight: 1.15, blockGap: 1.6, align: 'left', hangMarker: true },
    // rubric: prose, smaller and red
    rubric: { ...prose, fontScale: 0.85, color: 'red' },
};
```

`psalm` is the default, so every existing score renders byte-identically.

### 4.2 Syntax

Per block, using the format's existing "directives live in parentheses" idiom:

```aretino
W(prose): Az áldozás alatt a nép énekelhet, vagy a kántor
zsoltárt énekelhet — a sorok itt folyószövegként tördelődnek.

W(stanza): 1. Ez itt az első verssor,
ez a második verssor, amely elég hosszú ahhoz, hogy tördelődjön.

W: Dicsőség az Atyának és Fiúnak *
és Szentlélek Istennek.
```

Parser change: `/^\s*W(?:\(([A-Za-z][\w-]*)\))?:\s?/`. An unknown style name
falls back to the default (and, once the renderer has a warning channel, reports
it). No existing source can contain `W(` before the colon, so this is additive.

Document default and host override:

```aretino
%option: textStyle=prose
```

plus a `textStyle` renderer option, registered in
`HEADER_RENDERER_OPTION_TYPES` (`options.js:6`) like every other option.
Precedence, most specific first:

1. the block's `W(style):` marker
2. `renderAretino(source, { textStyle })` from the host
3. `%option: textStyle=…` in the header
4. `psalm`

(2 before 3 matches the documented rule that explicit options beat header
options; the per-block marker beats both because it is per-block, not a
default.)

For hosts that want to tune a preset without a new style name, expose the knobs
as flat renderer options — `textIndent`, `textWrapIndent`, `textLineHeight`,
`textBlockGap`, `textAlign`, `textDistance` (gap between staff and block) —
layered over the resolved preset.

### 4.3 Rendering changes

```js
export function renderVerseLines(ctx, blocks, leftX, rightX, startY, defaultStyle = 'psalm') {
    // blocks: Array<{ style?: string, lines: string[] }>  (also accept string[] for one minor version)
    for (const block of blocks) {
        const st = resolveStyle(block.style ?? defaultStyle, ctx.textStyleOverrides);
        const inputLines = st.continuation === 'flow'
            ? [block.lines.join(' ')]          // paragraph: reflow
            : block.lines;                     // psalm / stanza: honour breaks
        const marker = st.hangMarker ? takeLeadingMarker(inputLines[0]) : null;
        // firstX / breakX / wrapX from st.*Indent · fs (+ marker column when hanging)
        // leading: st.lineHeight · fs inside the block, st.blockGap · fs before it
    }
}
```

Three details worth deciding up front:

- **Justification.** The PDF path is librsvg (`SvgToPdfConverter`), which does
  not implement `textLength`/`lengthAdjust` and is unreliable for
  `word-spacing`. Justify by emitting one `<text>` per word at a computed `x` —
  the converter already relies on per-node coordinates, so this is the safe
  construction. Never justify the last line of a block.
- **Marker hanging.** Measure the leading marker of every block in the section
  first, then set one shared text column at `max(markerWidth) + 0.5 em` so all
  stanza numbers and ℣/℟ signs line up.
- **Metrics.** Wrap points are computed with the browser canvas
  (`measureTextWidth`) or, headless, with a `length · 0.55 · fs` estimate, but
  the PDF is shaped by Pango. Wrap positions are baked into the SVG, so a
  mismatch only shows as a slightly ragged right edge — except under `justify`,
  where it shows as uneven spacing. Keep `justify` opt-in.

### 4.4 AST change

```js
{ type: 'verse', style: 'prose' | null, lines: string[], srcStart, srcEnd }
```

and `groupSections` pushes the item itself instead of `item.lines`
(`items.js:35`). Adding `srcStart`/`srcEnd` at the same time fixes §3.3 and lets
the editor drop its backward scan. `renderVerseLines` should accept both the old
(`string[][]`) and new shapes while `0.21.x` consumers exist.

## 5. Work in Cantores

Nothing here is blocking — Cantores renders whatever core produces — but three
follow-ups make the feature usable:

1. **Editor default.** Add `aretinoTextStyle` to the `aretinoFields` list in
   `resources/js/score-editor-aretino.js:39` and a Flux select in the Aretino
   settings panel; it maps to the `textStyle` renderer option and is persisted
   per score with the other Aretino settings.
2. **Per-block control.** The Aretino toolbar already resolves the caret's block
   type as `verse` (`@aretino-chant/editor` `toolbar.js:738`); once core accepts
   `W(style):` the toolbar can cycle the marker on the current block. This is
   the control that matters for mixed documents (chant + rubric + psalm).
3. **Incipit path.** Once §3.4 lands, `renderFirstRow` works for text-only
   scores and `cropAretinoVerseToFirstLines` (`score-editor.js:100`) can be
   deleted; until then it should at least select `.aretino-verse-line` rather
   than every `<text>`.

Documentation: `docs/aretino-cheatsheet.md` and
`docs/aretino-felhasznaloi-utmutato.md` both describe `W:` as
"folyó zsoltárvers"; both need the style variants once shipped.

## 6. Compatibility and rollout

Additive throughout — a core `0.22.0`:

| Change | Risk |
|---|---|
| `psalm` default preset | none: identical output to `0.21.1` |
| `W(style):` syntax | none: not previously valid |
| `textStyle` + knob options | none: unset → preset values |
| AST `verse` item gains `style`, `srcStart`, `srcEnd` | none for readers; `sec.verses` shape change is internal, guarded by dual-shape support |
| §3.1 `~` fix | changes output where `~` is used in `W:` — the documented behaviour |
| §3.2 glyph fix | changes output where `\'`/`\b`/`\n`/`\#` are used in `W:` — currently these disappear |
| §3.3 classes/source spans | none (additive attributes) |
| §3.4 row markers for text blocks | `splitRowSVGs` starts returning rows for text-only scores; Cantores' fallback branch becomes dead code, not wrong code |

## 7. Test plan

`packages/core/test/` currently has exactly one assertion touching `W:`
(`renderer.test.js:753`). Minimum coverage for this change:

- parser: `W(prose):` / `W(stanza):` / `W:` / unknown style / no style; block
  continuation unchanged; source spans present.
- wrapping: explicit break vs auto-wrap x-position per preset; `flow` joins
  lines; a word wider than the column does not loop.
- `~` stays on one line; `\'` and `\b` survive into the SVG.
- leading: `lineHeight` inside a block, `blockGap` between blocks, per preset.
- `hangMarker`: two stanzas with `1.`/`10.` share one text column.
- options precedence: block marker > render option > `%option:` > default.
- Cantores: a Pest test asserting the editor persists `aretinoTextStyle` with
  the other Aretino settings.

## 8. Open questions

1. Should `prose` reflow (`continuation: 'flow'`) or honour source breaks
   un-indented? Reflow matches how people hard-wrap prose in an editor, but it
   makes a deliberate break impossible without a `stanza`-style block.
2. Should stanza blocks be separated by blank lines? A blank line currently ends
   the whole section, so consecutive `W(stanza):` blocks are the mechanism —
   acceptable, but it must be documented.
3. Is `rubric` a style or just `prose` plus inline markup? A style is more
   convenient; inline markup is more composable.
4. Do we want automatic breaking at `*` / `†` in `psalm` style (half-verse per
   line when the column allows), or keep it explicit?
