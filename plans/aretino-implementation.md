# Aretino formátum — implementációs terv

Új kottaformátum a `score-editor`-hoz, az ABC / GABC / ChordPro mellé.
A formátum specifikációja: [`docs/aretino-format.md`](../docs/aretino-format.md).

## 1. Áttekintés

Az **Aretino** GABC-szerű szöveges forrásnyelv, ami magyar gregorián
notációt jelenít meg ötvonalas kottarendszeren, hagyományos kerek
kottafejekkel. A renderer **közvetlenül SVG-t generál** (nem TTF), a
glyph-eket részegységként építi fel (kottafej, szár, ligatúravonal,
episzéma stb.).

A régi *Guido HU/EN* TTF betűkészlet szellemi utódja, de:

- szemantikus forrásnyelv (nem ASCII-pozíciós trükk);
- a glyph-ek hangmagasság-érzékenyek (pl. episzéma a hang fölött, nem a
  fix maximális magasságban);
- SVG-natív, így skálázható, exportálható, copy-paste-elhető a meglévő
  score-editor `exportPng` / `copyImage` pipeline-ján keresztül.

A meglévő Guido-Word-dokumentumok átfordítása **külön (későbbi) terv** —
itt nem foglalkozunk vele.

## 2. Backend változások

### 2.1 Enum
[`app/Enums/ScoreFormat.php`](../app/Enums/ScoreFormat.php) — új eset:

```php
case Aretino = 'aretino';
```

És a `label()` matchben:

```php
self::Aretino => __('Aretino (magyar gregorián)'),
```

### 2.2 Lokalizáció
- [`lang/hu.json`](../lang/hu.json) és [`lang/en.json`](../lang/en.json):
  az új feliratok (formátum-név, beállítások panel címkéi, példa-leírás).

### 2.3 Score-modell és mentés
- Külön mező nem kell — a `content` szöveges marad, a `format` az enum értéke.
- A `score_settings` JSON oszlopba új `aretino` kulcs kerül, alkulcsai az
  oldalarány szerinti beállításokkal (`auto`, `16/9`, `4/3`, `1/1`).
- Migráció: nem szükséges (JSON oszlop, dinamikus kulcs).

### 2.4 Példa-tartalom
- [`resources/js/score-editor.js`](../resources/js/score-editor.js) `fillExample()`
  blokkban új `aretino: \`...\`` minta-kotta (Kyrie I első sora az
  `aretino-format.md` példából).

## 3. Frontend változások

### 3.1 Új mixin
Új fájl: [`resources/js/score-editor-aretino.js`](../resources/js/score-editor-aretino.js)
— `aretinoMixin()`-t exportál a többi mixin mintájára.

State (Alpine adat):
- `aretinoLyricFont` — alapértelmezett `'Palatino Linotype', serif`
- `aretinoLyricSize` — 10 (pt)
- `aretinoStaffSize` — 7 (milliméter)
- `aretinoZoom` — 100
- `aretinoNoteSpacing` — finom hangolás
- `aretinoPageRatio` — `'auto' | '16/9' | '4/3' | '1/1'`
- `aretinoFields` — a fenti kulcsok listája (a `gabcFields` mintájára)

Metódus: `renderAretinoPreview()` — a `$refs.preview`-be SVG-t renderel a
`getVirtualCanvasSize('aretino')` által adott vászonra. A `splitPages`
fejléc-felismerője a `%%` markerre épül (azonos a GABC-vel).

### 3.2 A főkomponens bekötése
[`resources/js/score-editor.js`](../resources/js/score-editor.js):

- import + spread: `...aretinoMixin()` (a 27–29. sor mintájára).
- [`score-editor.js:128-133`](../resources/js/score-editor.js#L128-L133)
  `getVirtualCanvasSize`: új `'aretino'` ág (ugyanaz az 1920×{1080|1440|1920}
  séma, mint a GABC).
- [`score-editor.js:82-126`](../resources/js/score-editor.js#L82-L126)
  `collectSettings`: új `'aretino'` ág.
- [`score-editor.js:256-266`](../resources/js/score-editor.js#L256-L266)
  `renderPreview`: új `'aretino'` ág a `renderAretinoPreview()`-ra.
- `$watch` blokk az új Alpine state-ekre.
- `applyInitialSettings()`: `applyRatioSettings('aretino', this.aretinoPageRatio)`.

### 3.3 Blade nézet
[`resources/views/livewire/pages/score-editor.blade.php`](../resources/views/livewire/pages/score-editor.blade.php):

- Formátum-választó `flux:select`-be új `<option>` az Aretino formátumra.
- Új beállítások panel az Aretino-specifikus mezőkkel (lyric font, méret,
  staff méret, zoom, oldalarány). A GABC panel struktúráját másoljuk.
- `x-ref="aretinoPreview"` (vagy maradhat a közös `preview` ref — érdemes
  ellenőrizni hogy a `copyImage` és `exportPng` ne keveredjen).

## 4. Renderelő architektúra (`resources/js/aretino/`)

Új könyvtár a JS modulok számára:

```
resources/js/aretino/
  index.js          — aretinoMixin() és renderAretinoPreview() publikus API
  parser.js         — Aretino forrás → token AST
  layout.js         — AST → glyph + (x,y) pozíció + sortörés
  staff.js          — ötvonalas kottarendszer SVG-rajz
  text.js           — szillabikus szöveg pozicionálás, sortörés
  svg.js            — SVG dokumentum összeállítás (a glyph komponensekből)
  glyphs/
    punctum.js      — töltött kerek kottafej
    virga.js        — punctum + szár
    quilisma.js     — csíkozott kontúrú kottafej
    tenor.js        — üres + függőleges keret
    oriscus.js
    episema.js      — hangmagasság-érzékeny vízszintes vonal
    mora.js         — nyújtópont
    liquescens.js   — kis és nagy variáns
    ligature.js     — podatus / clivis / torculus / porrectus / climacus
    clef.js         — G, F, C kulcsok
    accidental.js   — flat / natural / sharp
    barline.js      — , ; : :: vonalak
    custos.js
```

Minden glyph modul exportál egy függvényt:

```js
export function drawPunctum(ctx, { x, y, scale }) {
    return { svgElement, advanceX, bbox };
}
```

A `ctx` tartalmazza a font-méretet, staff-méretet, szín-stílust. A
visszaadott `svgElement` egy `<g>` csoport, az `advanceX` pedig a következő
glyph kezdő x-pozíciója.

## 5. Layout és sortörés

A layout teljesen manuális jelenleg, hasonlóan a guido-hoz. Tehát üres vonalrendszert is lehet beírni, vagy másképp mondva zenei szünetet. Ami a forrásszövegben egy sor, az az outputban is egy sor. Az automatikus layout későbbi iteráció.

## 6. Tesztelés

A projekt Pest 4-et használ. Tesztstratégia:

egyelőre nem írunk automatikus teszteket, manuálisan tesztelünk

## 7. Iterációk

1. **Vázkeret** — enum, blade-opció, üres `aretinoMixin`, üres preview;
   formátum kiválasztható de még nem rajzol semmit. Egy `ScoreFormat` test.
2. **Vonalrendszer + violinkulcs + punctum** — `(g2) d e f g` jellegű
   sorok rajzolása, sortörés még nélkül.
3. **Virga, mora, episzéma, likveszcencia** — egyhangú szótagok jelekkel.
4. **Kvilizma, oriscus, tenor-hang.**
5. **Ligatúrák** (podatus / clivis / torculus / porrectus / climacus).
6. **Vonalak (`,;:` `::`), sorvég-kiegyenlítő, automatikus sortörés, custos.**
7. **Előjegyzések, kulcsváltás soron belül.**
8. **Beállítások panel finomhangolása** (note spacing, staff size, oldalarány).
9. **Snapshot-szerű regressziós tesztek** néhány referencia-darabbal.

## 8. Függőségek és kockázatok

- **Nincs új npm-csomag** — a renderer önálló (a doku szerint a megközelítés
  egyszerű SVG-rajzolás). Ha kiderül hogy bonyolódik, érdemes mérlegelni
  `chant.js` vagy `verovio` integrációt — de v1-re nem terv.
- **Pint formatálás** — minden módosítás után `vendor/bin/pint --dirty --format agent`.
- **Vite build** — új JS modulok hozzáadása esetén `npm run build` szükséges
  (vagy `composer run dev` fejlesztés közben).
- **Tesztek** — `php artisan test --compact` futtatása minden mérföldkő után.

## 9. További döntések

1. Az `exportPng` és `copyImage` logika majd később kialakul, egyelőre készüljön el az SVG preview hasonlóan a többihez.
2. A `condensingTolerance` és a többi GABC-paraméter analóg NEM készül el első verzióban.
3. Nem készítünk seederrel tesztkottát (csak egy példát, a többi példához hasonlóan.)
4. A `w:` szöveg-sor pozicionálása a dallam alatt: fix offset van, a szóközöket használjuk.
