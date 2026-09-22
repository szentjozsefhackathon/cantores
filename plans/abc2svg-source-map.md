# Mapping an abc2svg score back to its ABC source

How to make a rendered score clickable — click a note, land on the character
that produced it; move the caret, highlight the symbol it belongs to.

This is the technique `abcreview.py` uses, taken from abc2svg's own
`edit-1.js`. The SVG is **not** post-processed: the hit boxes are written into
the image while abc2svg is drawing it, so they stay correct under any layout,
scale or page width.

## The one hook that matters

The abc2svg parser keeps the source offsets of every music symbol on the
symbol itself (`istart`, `iend`). If the user object passed to
`new abc2svg.Abc(user)` defines `anno_start` or `anno_stop`, the core calls it
around the drawing of each symbol:

```js
anno_stop(type, istart, iend, x, y, w, h, s)
```

| argument       | meaning                                                      |
| -------------- | ------------------------------------------------------------ |
| `type`         | `"note"`, `"bar"`, `"clef"`, `"lyrics"`, `"beam"`, `"slur"`, … |
| `istart`/`iend`| character offsets into the string given to `tosvg()`          |
| `x, y, w, h`   | the symbol's box, in abc2svg's **internal music coordinates** |
| `s`            | the symbol object itself                                      |

`anno_start` fires before the symbol is drawn, `anno_stop` after. Use
`anno_stop` — by then the symbol's final position is known.

Merely defining one of them changes the core's behaviour (it switches the
internal `anno_start`/`anno_stop` from no-ops to the real ones), so there is
nothing else to turn on.

## Emitting the hit boxes

Coordinates have to be converted into the SVG user space of the image being
generated right now. The `Abc` object exposes exactly that:

- `abc.out_svg(str)` — append raw text to the current image
- `abc.out_sxsy(x, mid, y)` — write `x`, then `mid`, then `y`, converted
- `abc.sh(h)` — convert a height; `abc.sx/sy/ax/ay/ah` are the pieces

```js
var user = {
    img_out: function (str) { svg += str },        // required: image sink
    anno_stop: function (type, start, stop, x, y, w, h) {
        if (type == 'beam' || type == 'slur' || type == 'tuplet')
            return                                 // too big; they eat clicks
        abc.out_svg('<rect class="abcsym" data-start="' + start +
                '" data-stop="' + stop + '" x="')
        abc.out_sxsy(x, '" y="', y)
        abc.out_svg('" width="' + w.toFixed(2) +
                '" height="' + abc.sh(h).toFixed(2) + '"/>\n')
    }
}
```

Because the rect is written through `out_svg`, it lands inside the same `<svg>`
element as the symbol and under the same transform. It moves with the symbol
when the staff scale, the page width or the line breaking change, and it works
unchanged when a tune renders as many `<svg>` blocks (one per music line).

Keep them invisible but hit-testable:

```css
.abcsym       { fill: #000; fill-opacity: 0; cursor: pointer; }
.abcsym:hover { fill: #6fb3e0; fill-opacity: .25; }
.abcsym.sel   { fill: #e08020; fill-opacity: .3; }
```

> If you need the boxes as a separate overlay rather than inline, use absolute
> coordinates instead — `abc.ax(x)`, `abc.ay(y)`, `abc.ah(h)` — and append the
> rects after the images. Inline is simpler and survives scrolling.

## Score → source

The map is now just DOM attributes; no lookup table is needed.

```js
score.addEventListener('click', function (ev) {
    var r = ev.target.closest('.abcsym')
    if (!r)
        return
    ta.focus()
    ta.setSelectionRange(+r.dataset.start, +r.dataset.stop + 1)
})
```

## Source → score

Take the **innermost** box containing the caret: symbols nest (a note sits
inside its bar's range), so among the matches pick the one with the largest
`start`.

```js
function mark(i) {                      // i = textarea.selectionStart
    var best = null
    score.querySelectorAll('.abcsym').forEach(function (b) {
        b.classList.remove('sel')
        if (+b.dataset.start <= i && i <= +b.dataset.stop + 1 &&
            (!best || +b.dataset.start > +best.dataset.start))
            best = b
    })
    if (best)
        best.classList.add('sel')
}
```

Call it from `keyup` and `click` on the textarea.

## Keeping the offsets honest

The offsets are indexes into the string passed to *that* `tosvg()` call. Two
rules follow.

1. **Never prepend anything to the source.** Formatting directives
   (`%%pagewidth`, `%%scale`, a house style sheet) go in a separate call, which
   resets the offset base for the source that follows:

   ```js
   abc.tosvg('page.fmt', '%%pagewidth 800px\n%%leftmargin 6px\n')
   abc.tosvg('edit.abc', source)         // offsets are relative to `source`
   ```

2. **Re-render from the same string the editor holds.** If you normalise the
   text (trim, add a final newline) before rendering, do it in the editor too,
   or every offset past the change is wrong.

## Rendering loop, in full

```js
abc2svg.loadjs = function (fn, ok, err) {        // how modules are fetched
    var s = document.createElement('script')
    s.src = /:\/\//.test(fn) ? fn : '/lib/' + fn
    s.onload = ok
    s.onerror = function () { (err || function () {})(fn) }
    document.head.appendChild(s)
}
if (!abc2svg.abc_end)
    abc2svg.abc_end = function () {}             // %%pageheight defines a real one

var abc, svg

function render() {
    var src = editor.value
    svg = ''
    abc = new abc2svg.Abc(user)

    // a directive in the source may need a module that is not loaded yet;
    // load() returns false and calls the relay when it has arrived
    if (!abc2svg.modules.load(src, render, console.error))
        return

    try {
        abc.tosvg('page.fmt', '%%pagewidth ' + width + 'px\n')
        abc.tosvg('edit.abc', src)
        abc2svg.abc_end()
    } catch (e) {
        /* report */
    }
    score.innerHTML = svg
}
```

`abc` must be the variable `anno_stop` writes through, so create it before
calling `tosvg` and keep it in scope (as above).

## Errors, with the same offsets

`user.errbld(sev, txt, fn, idx)` gives structured diagnostics; `idx` is a
source offset in the same coordinate system, so an error list can use the very
same jump-to-source code. Define `errbld` *or* the older
`errmsg(text, line, col)`, not both — `errbld` wins when present.

```js
errbld: function (sev, txt, fn, idx) {           // sev: 'warn'|'error'|'fatal'
    errors.push({ sev: sev, txt: txt, idx: idx })
}
```

Note that parse-time errors that belong to no symbol arrive without an `idx`.

## Other useful bits of the user object

| member                  | what it gives you                                        |
| ----------------------- | -------------------------------------------------------- |
| `img_out(str)`          | required — the image sink                                 |
| `read_file(fn)`         | resolves `%%abc-include`                                  |
| `get_abcmodel(ts, v, …)`| the whole symbol list before output (for playback, stats) |
| `page_format: true`     | emit non-page-breakable blocks; needed for `%%pageheight` |
| `anno_start`            | same signature as `anno_stop`, called before drawing      |

## Gotchas

- Skipping `beam`, `slur` and `tuplet` is not cosmetic: their boxes span whole
  groups and would cover the notes inside them.
- Lyric syllables come through as `type == 'lyrics'` with the offsets of the
  syllable in the `w:` line — that is what makes a lyric clickable.
- `anno_stop` runs inside the drawing code. Do no DOM work there; only build
  the string.
- `abc2svg.modules.load()` must be called on **every** render, not once: it is
  what notices a newly typed `%%` directive that needs a module.
- The core is LGPL; `edit-1.js` (abc2svg's full editor) is GPL. Using the core
  and writing your own `anno_stop`, as here, keeps you on the LGPL side.

## Reference

- Working use: `review/app.js` and `abcreview.py` in this repo.
- Upstream interface wiki:
  <https://chiselapp.com/user/moinejf/repository/abc2svg/wiki?name=interface-1>
- Upstream's own editor, the origin of this recipe:
  `node_modules/@cantoreshu/abc2svg/edit-1.js`
