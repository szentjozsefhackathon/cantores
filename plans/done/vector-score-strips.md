# Vector Score Strips

## Context

`RenderScoreFileJob` stores, for every uploaded score file: the engraved PDF, one 150 dpi
page PNG per page, a 200 dpi incipit crop, and one **300 dpi PNG per musical system**
(`ScoreFile::STRIP_DPI`, cut by `ScoreStripCutter`). A 10-page file lands as roughly 70
artifacts, and `ScoreImageCompressor`'s own docblock names the strips as the densest thing
stored per page.

Everything downstream of the PDF is therefore a picture, at a resolution chosen once and
forever. Two consequences:

1. **Storage.** The strips duplicate, at double resolution, pixels that already exist in
   the page PNGs, which in turn duplicate a PDF that is already stored. The
   `score-upload-limits-implementation.md` plan has to size per-user allowances around
   this: its own warning is that "one 2 MB, 200-page `.mscz` can still expand to hundreds
   of megabytes of strips in a single render".
2. **Quality.** An exported booklet prints its systems at 300 dpi however large the sheet
   is, while every other format in the same booklet — gabc, abc, ChordPro, Aretino — is
   re-engraved as vector at the page's own size.

Both follow from the same decision, and both are undone by the same change: keep the
engraving in vector form and let a strip be a *window* onto it rather than a crop of it.

## What cropping the PDF alone does not solve

A per-strip cropped PDF saves nothing. A `/CropBox` clips, it does not remove content: each
crop still drags the whole page content stream and its font subsets, so N strips cost
about N × the page. And `SvgToPdfConverter` pipes SVG documents to `rsvg-convert`, which
cannot embed a PDF at all — so a cropped PDF has no route into the booklet the browser
lays out.

What the crop rectangle *is* good for is metadata. `ScorePageBander` already returns the
window and the bands as **fractions of the page**, and `score_files.strips` already stores
one row per system. Storing the rectangle instead of a cropped image takes the strip
artifacts to zero bytes — provided something downstream can draw the rectangle.

## Decision

**Vectorise each PDF page once with poppler's `pdftocairo -svg`, store one gzipped SVG per
page, and make a strip a `viewBox` window onto it.** Rationale:

- `poppler-utils` is already installed in `Dockerfile.musescore:30`, so `pdftocairo` is the
  same package that already provides `pdftoppm`. No new dependency in either image.
- The booklet already composes SVG and already hands SVG to `rsvg-convert`. A vector strip
  is a nested `<svg viewBox="x y w h">` in place of today's `<image href="data:…">` — the
  layout arithmetic in `stripPlacements()` is untouched.
- One page SVG **replaces both** that page's reading PNG and every strip PNG cut from it.
- The exported booklet becomes resolution-independent; `STRIP_DPI` disappears.

Band detection stays raster. The 150 dpi rasterisation is cheap, is already performed, and
rewriting `ScorePageBander` against PDF content streams would buy nothing — but the pages
it analyses become **throwaway**, held in memory for the length of the job rather than
stored.

### Expected saving

Estimated, not measured — nothing in the dev container can run poppler on a real
engraving, and the prototype in step 0 is what turns these into numbers.

A four-system A4 hymn page stores today roughly 4 × 40–80 KB of strip + 80–150 KB of page
PNG ≈ 300–450 KB. The same page as one gzipped cairo SVG is plausibly 40–90 KB: call it a
4–8× reduction. The more important property is that the figure now scales with *engraved
content* rather than with dpi × page area, which is what defuses the upload-limits plan's
200-page worst case.

### The exception: scans

An image-only PDF — most uploaded scans — has nothing to vectorise. `pdftocairo -svg`
base64s the page bitmap into the SVG, which is about 33% *larger* than the PNG it would
replace. So the raster path is not deleted, it is demoted to a fallback, and the choice is
made per file by measurement (step 3), not by guessing from the extension: `.pdf` uploads
are frequently vector engravings too.

## 0. Prototype first (blocking)

Before any of the below is written, run one real MuseScore export through the whole chain
in the renderer image and open the result:

```
mscore --export-to /tmp/p.pdf sample.mscz
pdftocairo -svg -f 1 -l 1 /tmp/p.pdf /tmp/p1.svg
rsvg-convert --format=pdf --output /tmp/out.pdf /tmp/p1.svg
```

Three things must hold, and the plan changes shape if any does not:

- **librsvg renders cairo's SVG faithfully.** Cairo emits glyphs as `<symbol>`/`<use>`
  outlines and uses `clip-path`; librsvg supports both, but slurs, beams and hairpins are
  what to look at.
- **No `<text>` with a bare `font-family` survives.** If any does, the face has to exist in
  the app image (which has only `fontconfig` + the committed web fonts) or be inlined the
  way `svg-fonts.js` already inlines faces — decide which before building on it.
- **The gzipped page SVG is smaller than today's page PNG + strips** for an ordinary
  engraving. If it is not, stop: the plan's premise is wrong.

## 1. `app/Services/PdfPageVectorizer.php` (new)

A sibling of `PdfPageRasterizer`, with the same shape it documents: isolated 0700 work
directory, argv array so nothing reaches a shell, hard timeout, output validated by its
own bytes rather than by exit code.

```php
public static function fromConfig(): self;              // services.pdftocairo.*
public function vectorizePage(string $pdf, int $page): string;   // one page, SVG bytes
```

`pdftocairo -svg -f N -l N in.pdf out.svg`, one invocation per page — poppler writes a
single page per SVG file, and this is the same per-page process cost as the 300 dpi
rasterisation it replaces. Input is checked for `%PDF` as `PdfPageRasterizer::run()` does;
output must parse as XML with an `<svg` root before it is accepted.

`config/services.php` gains a `pdftocairo` block beside the existing `pdftoppm` one
(`PDFTOCAIRO_BIN`, `PDFTOCAIRO_TIMEOUT`), and `AppServiceProvider` a singleton beside the
other `fromConfig()` bindings.

## 2. Storage shape

`app/Models/ScoreFile.php`:

```php
public function pageVectorPath(int $page): string   // directory()/page-{n}.svgz
```

Stored **gzip-compressed** (`gzencode(..., 9)`) inside the existing encryption envelope:
`ScoreFileStorage` encrypts whatever it is handed, and ciphertext does not compress, so the
compression has to happen before it. Roughly 5–8× on SVG text, and it is the same bytes the
browser wants (step 6 serves them with `Content-Encoding: gzip`).

`strips` entries gain the rectangle and keep everything they carry today:

```php
/** @property list<array{page:int,index:int,width:int,height:int,rect?:array{float,float,float,float}}> */
```

`width`/`height` stay the numbers `stripPlacements()` scales by — for a vector strip they
are the rectangle's size in PDF points instead of pixels, which changes nothing, because
that code only ever uses their *ratios*. The presence of `rect` is what marks an entry
vector; `stripList()`'s validation accepts both shapes and `hasStrip()` is untouched. That
is deliberate: **already-rendered files keep working, unchanged, until they are
re-rendered.**

The rectangle is `[left·W, top·H, (right−left)·W, (bottom−top)·H]` in the page's own units,
straight from the fractions `ScorePageBander` already returns. Poppler applies `/CropBox`
and `/Rotate` identically in `pdftoppm` and `pdftocairo`, so the fractions measured on the
raster land on the same place in the SVG.

## 3. `app/Jobs/RenderScoreFileJob.php`

The job keeps its overall shape — render, analyse, store — and changes what it stores.

1. `$pages = $rasterizer->rasterize($pdf)` as today, **not stored**: these exist only to be
   banded and to be measured against in step 3c.
2. Band every page (`cutStrips()`'s existing analysis half, unchanged), union the window.
3. Vectorise every page. Compare `sum(strlen(gzencode(svg)))` against
   `sum(strlen($compressor->compress($page)))` **over the whole file**:
   - vector smaller → store `page-{n}.svgz`, write `strips` with `rect`, store no page
     PNGs and no strip PNGs;
   - otherwise → today's behaviour exactly: compressed page PNGs, a 300 dpi pass per
     banded page, `ScoreStripCutter`, strip PNGs, `strips` without `rect`.

   Per file rather than per page, so one document never mixes representations and the
   booklet never has to reason about which it got.
4. The incipit is unchanged — `rasterizePage($pdf, 1, 200)` into `ScoreFileIncipitCropper`.
   Thumbnails are PNGs everywhere they are used and stay PNGs.

`page_count` still comes from the rasterisation, so nothing about the reading view's paging
changes. The existing "a failure here is logged and swallowed" contract around banding is
kept, and extended: a vectorisation failure falls back to the raster path rather than
failing the render.

The `ini_set('memory_limit', '512M')` comment needs revisiting in the same pass — the
resident set is now the PDF plus one page SVG plus the discarded rasterisations.

## 4. Serving: one route per page, not per system

`app/Services/ScoreFileResponder.php` gains:

```php
public function pageVector(ScoreFile $scoreFile, int $page, bool $public): Response
```

`image/svg+xml`, `Content-Encoding: gzip`, and the same checksum ETag / `Last-Modified` /
public-vs-private cache policy the other artifacts get — the 304 is still decided before
anything is decrypted.

Because this body is *derived from a user-supplied PDF*, it carries what the PNG routes
never needed: `X-Content-Type-Options: nosniff` and `Content-Security-Policy: default-src
'none'; style-src 'unsafe-inline'; sandbox`. The reading view draws it through
`<img x-bind:src>` (`score-file-pages.blade.php:50`), where SVG is already script-sandboxed
by the browser — but the URL is also reachable directly, and the route must not depend on
cairo never emitting script.

Routes: `/booklets/{booklet}/score-page/{scoreFile}/{page}` →
`BookletScorePageController`, an exact copy of `BookletStripController`'s authorisation
(`authorize('update', $booklet)`, not superseded, `MusicPlanScoreListService::sourcesFor()`)
with the strip check replaced by "this file has a vector page N".

`booklets.strip` and `BookletStripController` **stay**: they serve the raster fallback and
every file rendered before this change.

## 5. `resources/js/booklet-render.js`

`buildFileBlocks()` branches on the shape it is handed, and the raster branch is exactly
today's code:

- **raster** (`strip.url`) → unchanged: fetch, data-URI, `imageSvg()`.
- **vector** (`strip.pageUrl` + `strip.rect`) → fetch the *page* SVG once per (file, page),
  through the same `stripCache` keyed by URL — a four-system page is one request instead of
  four — then wrap it:

  ```
  <svg viewBox="x y w h" width="w" height="h" overflow="hidden">…page children…</svg>
  ```

  A nested `<svg>` clips to its viewport by default in both librsvg and every browser;
  `overflow="hidden"` states it rather than relying on it. `svg-stack.js` already rewrites
  colliding ids between fragments, which is what makes repeating one page's `<symbol>`
  glyph definitions across several strips safe.

`BookletEditor::bookletPayload()` (around line 196) emits `pageUrl` + `rect` for vector
files and today's `url` for raster ones. The payload gets *smaller*: one URL per page
rather than one per system.

## 6. Export payload: substitute on the server

Today `serializeBookletPages()` inlines every strip as base64 PNG into the SVG that is
POSTed to `BookletPdfExportController`. Inlining page SVGs the same way would repeat one
page's glyph table once per system on it.

So: `serializeBookletPages()` replaces each **vector** file-strip block with a placeholder

```html
<g data-score-page="{scoreFile}" data-page="N" data-rect="x y w h"></g>
```

and a new `app/Services/BookletScorePageInliner.php`, called from the controller before
`SvgToPdfConverter::convert()`, swaps each placeholder for the stored page SVG windowed to
that rectangle.

The placeholder is client-supplied, so the inliner re-authorises exactly as
`BookletScorePageController` does — same `MusicPlanScoreListService` question, same 404 —
and an unresolvable placeholder is dropped rather than failing the export. A side effect
worth having: the export POST loses the megabytes of base64 PNG it carries today.

`ExportBookletPdfRequest`'s size limits should be revisited downward once this lands.

## 7. Removals

- `app/Services/ScoreStripCutter.php` — only after step 3's fallback is proven, since the
  raster branch still calls it. It survives as fallback-only; the `strip-{page}-{index}.png`
  artifacts and `ScoreFile::STRIP_DPI` survive with it, but only for scans and legacy files.
- Stale prose to correct in the same pass: `RenderScoreFileJob`'s class docblock and its
  `cutStrips()` "two passes over the document" comment, `ScoreFile::STRIP_DPI`'s "not
  denser still because a booklet is a service sheet" note, `booklet-render.js`'s "this is
  the one format that cannot be re-engraved at the booklet's size — a PDF is a picture by
  the time it gets here", `Dockerfile.musescore`'s header line, and
  `CutScoreFileSystems`'s docblock (which explains that strips come off a rasterisation
  nothing keeps around).

## Files touched

| File | Change |
|---|---|
| `app/Services/PdfPageVectorizer.php` | new |
| `app/Services/BookletScorePageInliner.php` | new |
| `app/Http/Controllers/BookletScorePageController.php` | new |
| `config/services.php`, `app/Providers/AppServiceProvider.php` | `pdftocairo` config + binding |
| `app/Models/ScoreFile.php` | `pageVectorPath()`, `strips` shape, `stripList()` validation, docblock |
| `app/Jobs/RenderScoreFileJob.php` | vector-first with raster fallback; stop storing page PNGs in vector mode |
| `app/Services/ScoreFileResponder.php` | `pageVector()` + SVG hardening headers |
| `routes/web.php` | `booklets.score-page` |
| `app/Livewire/Pages/BookletEditor.php` | emit `pageUrl` + `rect` |
| `resources/js/booklet-render.js` | vector branch in `buildFileBlocks()`, placeholder in `serializeBookletPages()` |
| `app/Http/Controllers/BookletPdfExportController.php` | inline before converting |
| `app/Console/Commands/CutScoreFileSystems.php` | docblock; it is already the backfill |
| `resources/views/components/score-file-pages.blade.php` | serve the SVG page in the reading view |

No migration: `strips` is already a JSON column and the new key is additive.

## Verification

`PdfPageVectorizer` is mocked in tests exactly as `PdfPageRasterizer` already is
(`BookletFileScoreTest::renderWithBanding()`), so the suite still needs neither poppler nor
MuseScore.

New tests:

1. `tests/Unit/PdfPageVectorizerTest.php` — non-PDF input rejected; non-SVG output rejected;
   the work directory is removed on both paths. There is no existing unit test for
   `PdfPageRasterizer` to copy, so this one drives the guards with `PDFTOCAIRO_BIN` pointed
   at a shell stub in the scratch directory rather than at poppler.
2. `tests/Feature/BookletFileScoreTest.php` — a vector render stores `page-{n}.svgz` and no
   `strip-*.png`; each `strips` entry carries a `rect` whose fractions match the bands the
   fake page was drawn with; every strip of a file shares one horizontal window (the
   existing `array_unique(array_column(..., 'width'))` assertion, now over `rect` widths).
3. Same file — a page whose vector form is larger falls back: strip PNGs exist, no `rect`,
   and the booklet payload carries `url` rather than `pageUrl`.
4. Route tests mirroring the four existing `booklets.strip` access tests (owner, borrower,
   recalled loan, superseded file) against `booklets.score-page`, plus the SVG response
   headers.
5. `tests/Feature/BookletExportTest.php` — a placeholder is substituted for a file the
   viewer may see, and dropped for one they may not; the POST body contains no base64 image
   for a vector file.
6. `tests/Unit/booklet-strips.test.mjs` — `stripPlacements()` unchanged under point-valued
   `width`/`height`, and the vector wrapper produces the expected `viewBox`.

Must stay green: the whole of `BookletFileScoreTest`, `BookletExportTest`,
`CutScoreFileSystemsTest`, `ScorePageBanderTest` and `ScoreImageCompressorTest` — the
banding and compression contracts are unchanged by this and are what prove it.

```
php artisan test --compact --filter=Booklet
php artisan test --compact --filter=Score
node --test tests/Unit/booklet-strips.test.mjs
vendor/bin/pint --dirty --format agent
```

Then manually, in dev: upload one `.mscz` and one scanned PDF, confirm the first stores
`.svgz` and the second falls back, put both in one booklet, and compare the exported PDF
against today's at 400% zoom — the point of the change is visible there and nowhere else.

## Deployment

Additive and reversible in stages:

1. Deploy. Nothing changes for existing files: they keep their strip PNGs, their `strips`
   rows have no `rect`, and `booklets.strip` still serves them.
2. `php artisan scores:cut-systems --all --limit=N` re-renders in batches on the single
   `musescore` worker. Each re-render replaces that file's artifacts with the vector set;
   `ScoreFileStorage::deleteAll()` is already what the job's storage rewrite implies for
   the paths it no longer writes — confirm the old `strip-*.png` and `page-*.png` are
   removed rather than orphaned, or the saving is only realised for new uploads.
3. Once the library is converted, `stored_bytes` (from the upload-limits plan, if that has
   landed) needs `scores:storage --measure` to see the drop; the allowance tiers in
   `config/scores.php` can then be reconsidered against real numbers rather than against
   the 300 dpi worst case they were sized for.

Rollback is a revert plus `scores:cut-systems --all`: the raster path is never removed, so
the previous representation can always be rebuilt from the stored PDF.
