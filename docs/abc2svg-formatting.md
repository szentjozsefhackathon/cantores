# abc2svg Formatting Reference

Source: `public/js/abc2svg-1.js`

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

**Parameter:** `%%vocalspace <pt>`  
**Default:** `10`  
**Source:** line 9975–9979

Controls the minimum vertical gap between the bottom of the staff and the
first lyric baseline. The value is enforced as a floor (`if y > -vocalspace`),
so `0` is the practical minimum. Negative values have no effect without a
source patch.

```
%%vocalspace 0
```

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
