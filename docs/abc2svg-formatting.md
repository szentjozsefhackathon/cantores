# abc2svg Formatting Reference

Source: `@cantoreshu/abc2svg/abc2svg-1.js`

## Line Thickness

abc2svg emits SVG elements with CSS class names to control stroke widths.
Override these classes in a `<style>` block or stylesheet to change thickness.

### Stems

Stems are emitted as `<path class="sW" ...>` (see `glout()`, line 8409).

Default:
```css
.sW { stroke: currentColor; fill: none; stroke-width: .7 }
```

Override example (thicker stems):
```css
.sW { stroke-width: 1.2 }
```

### Staff Lines

`draw_staff()` assigns a class per staffline character type (line 2463):

| Character | Class   | Default stroke-width | Use         |
|-----------|---------|---------------------|-------------|
| `\|`      | `slW`   | `.7`                | Normal line |
| `[`       | `slthW` | `1.5`               | Thick line  |
| `'`       | `sltnW` | `.25`               | Thin line   |
| `:`       | `sldW`  | `.7` + dash         | Dashed line |

Override example (thinner normal staff lines):
```css
.slW { stroke-width: .5 }
```

### Bar Lines

Bar lines use class `bW` (stroke-width `1`). Override with `.bW { stroke-width: ... }`.

---

## Spacing Parameters (`%%` directives)

These are set in the ABC preamble as `%%paramname value`.

### Staff → Lyrics Distance

**Parameter:** `%%lyricfirstskipfac <factor>`
**Default:** `1.1`
**Source:** `draw_lyrics()` — **local vendor patch**, see `docs/vendor-patches.md`

Where the first `w:` line's baseline sits under the **bottom staff line**, as a
multiple of the lyric face's own ascent — measured from the face in a browser,
approximated as `.78` of the line height elsewhere. At `1` the ascender line
lands on the staff line; under `1` the lyrics come up into the staff, which is
the only way to get them really tight.

Anchoring on the staff line rather than on the music means one setting reads the
same on every system of a piece and in every face. The music is still a floor
under it: when the ink hangs low enough that the lyrics would be written over,
the baseline is pushed down to clear the lowest ink by `.35` of an ascent.

```
%%lyricfirstskipfac 0.8
```

In the app this is the `abcLyricFirstSkip` setting: it defaults to `1` and stops
at `0.5` (`ABC_LYRIC_FIRST_SKIP_MIN`), below which anything is treated as unset
and the engine keeps its own `1.1`. Emitted by `buildAbcPreamble`
(`resources/js/score-editor-abc.js`) and the booklet's `abcBlocks`
(`resources/js/booklet-render.js`).

> **Note:** the stock parameter for this gap, `%%vocalspace <pt>` (default
> `10`), is only a floor (`if y > -vocalspace`): it can push the lyrics further
> down, never closer than the lowest stem already reaches, so lowering it stops
> having any effect well before `0`. Both preambles pin it to `0` and leave the
> gap to `%%lyricfirstskipfac`.

### Distance Between Lyric Lines

**Parameter:** `%%lyricskipfac <factor>`
**Default:** `1.1`
**Source:** `draw_lyrics()` — **local vendor patch**, see `docs/vendor-patches.md`

The vertical advance from one `w:` lyric line to the next, as a multiple of the
line's own measured height. Upstream abc2svg hardcodes this as `1.1`; the patch
exposes it as a format parameter. It leaves the first line where it is — that
gap is `%%lyricfirstskipfac` — and `%%lineskipfac` does not apply to lyrics at
all.

```
%%lyricskipfac 1.4
```

In the app this is the `abcLyricSkip` setting: it defaults to `1.1` and stops
at `0.5` (`ABC_LYRIC_SKIP_MIN`), below which the stanzas collide. Anything under
that floor — an older score stored as `0`, a blank, junk — emits nothing and so
leaves the engine on its own `1.1`. Emitted by `buildAbcPreamble`
(`resources/js/score-editor-abc.js`) and the booklet's `abcBlocks`
(`resources/js/booklet-render.js`).

### System Distance (gap between systems)

**Parameters:** `%%staffsep <pt>` and `%%maxstaffsep <pt>`  
**Defaults:** `46` / `2000`  
**Source:** line 2425–2427

`staffsep` is the minimum vertical gap between successive systems (rows of
music). `maxstaffsep` is the maximum. Both are halved internally before use
as the effective floor/ceiling. Set both to `0` to collapse the inter-system
gap:

```
%%staffsep 0
%%maxstaffsep 0
```

> **Note:** `%%sysstaffsep` (default `34`) and `%%maxsysstaffsep` (default
> `2000`) control the gap between *staves within a single system* (e.g. in
> a choir score with soprano+alto on separate staves), not between systems.

---

## Applying CSS Overrides via JavaScript

abc2svg renders into an SVG string. To inject style overrides, prepend a
`<style>` element to the first `<svg>` after rendering:

```js
const svg = pageEl.querySelector('svg');
if (svg) {
    const style = document.createElementNS('http://www.w3.org/2000/svg', 'style');
    style.textContent = `
        .sW  { stroke-width: ${stemWidth} }
        .slW { stroke-width: ${staffLineWidth} }
    `;
    svg.prepend(style);
}
```

This works for both on-screen display and `html2canvas`/`toBlob` export,
because the style is embedded inside the SVG element.
