# Repositioning as a Workshop

## Objective

Bring what the application says about itself into line with what it does. It
had grown a score editor, folders, lending, printable booklets, projection, a
remote and a projection screen, while the front page still described a
searchable hymn database with a suggestion engine.

The point of the repositioning is not to list the new features. It is that
**none of them is the product on its own** — not a hymn database, not a
collection of service orders, not a score editor, not a presenter. What
distinguishes this from any of those is that one piece of material carries
through all of them: the variant a community actually sings is prepared once,
and from then on the same material goes into the service order, the booklet,
the projection and the players' parts without being retyped.

The claim the copy is built on:

> Ami nincs készen, azt itt megcsinálod. Amit egyszer megcsináltál, azt többé
> nem kell újra megcsinálnod.

## Current State (before)

- `resources/views/welcome.blade.php` was 30 lines: the liturgical calendar
  card, a hymn search card, and the external links block. Its page title was
  `Liturgikus énekrendek és énekek egységes adatbázisa` — the position we were
  moving away from.
- The front page's actual `<h1>` was not in the welcome view at all but hidden
  in `resources/views/components/⚡liturgical-info.blade.php`, in the
  `@if($welcome)` branch.
- `resources/markdown/about.md` still advertised a seven-item feature list
  naming a "Napló", with no mention of booklets, projection, the score editor
  or folders.
- `resources/markdown/guide.md` was the only surface that was current.
- `resources/views/pages/about.blade.php` and `pages/suggestions.blade.php` had
  no title or meta description at all.
- `README.md`, `.env.example` and `composer.json` still carried starter-kit
  values (`APP_NAME=Laravel`, `laravel/livewire-starter-kit`).

## What Changed

### 1. The front page — `resources/views/welcome.blade.php`

Rebuilt around the positioning, then compacted so that a guest who came to look
something up is not made to scroll past an argument.

```
A saját liturgikus zenei műhelyed            ← h1
Énekrend, kotta, füzet és vetítés — ugyanabból az anyagból, egy helyen.
› Hogyan működik?                            ← <details>, closed

[ Énektár ] [ Énekrendek ] [ Kottaszerkesztő ]   ← one row of link buttons

[ Énekrendtervező card ]
[ Gyorskeresés card ]
[ Más hasznos oldalak ]
```

Behind the disclosure: the lead, the promise, the four-step chain
(`Énekrend → Kotta → Füzet → Vetítés`) with arrows that turn downward on a
phone, both calls to action, and three paragraphs of reasoning.

**Why `<details>` and not a modal.** The pitch stays in the DOM, so search
engines and link previews see it; it needs no JavaScript; and it does not
interrupt. A modal would hide from crawlers exactly the text we want indexed.
The pattern already exists in `resources/views/livewire/pages/score-editor.blade.php`.

**The tool row** repeats the public menu as full-width link buttons rather than
cards, so the three fit one line at every width. Captions are `hidden sm:block`,
and a tool may carry an optional `short` key for a phone-width label —
`Kottaszerkesztő` becomes `Kottázás` below `sm`. `Kottaszerkesztő` points at
`score.preview`, which genuinely works without an account and is the strongest
door a guest has.

### 2. The calendar card — `resources/views/components/⚡liturgical-info.blade.php`

It is now one named tool rather than the title of the site, and it is called
**Énekrendtervező** in both places it appears.

- Icon reduced from `h-10 w-10` to `h-6 w-6` and set on one line with the title.
- Below it, left aligned: a short next step — *Válassz egy napot, és másold át,
  ami tetszik.* — then the genre selector.
- The outer flex lost `justify-between`, which had pushed the date controls to
  the far edge; they now sit directly beside the title block on desktop.
- The three rows of date controls are centred on a phone
  (`items-center md:items-start`).
- On the front page the heading is `lg`; on the dashboard it stays `xl`. Only
  the prominence differs, not the name.

### 3. Narrative pages

- **`resources/markdown/about.md`** rewritten. The flat feature list is gone;
  the capabilities are grouped along the chain instead, because a list of
  services is precisely the impression the repositioning is moving away from.
  Audience narrowed deliberately to cantors, schola leaders, choir directors
  and guitar-band leaders — people who do musical work of their own. The
  privacy, copyright, development and contact sections were left untouched:
  they are legally load-bearing and were accurate.
- **`resources/markdown/guide.md`** — one paragraph added to the introduction
  framing the workshop idea. Chapter headings deliberately untouched, since
  `tests/Feature/LegalPagesTest.php` asserts against this file's contents.

### 4. Metadata

- Front page title is hybrid: `A saját liturgikus zenei műhelyed: énekrendek,
  kották, füzetek, vetítés`. The visible `<h1>` is pure positioning; the browser
  title keeps the keywords the site is currently found by. A colon rather than a
  dash, because `partials/head.blade.php` already prefixes `Cantores.hu – `.
- Added the missing title and description to `resources/views/pages/about.blade.php`
  and `resources/views/pages/suggestions.blade.php` (the latter also had its
  indentation corrected).
- `README.md` tagline, `.env.example` `APP_NAME`, and `composer.json`
  name/description updated off their starter-kit values.

## Incidental Fix: Two Flaky Factories

Verifying the above kept failing intermittently in
`tests/Feature/Livewire/PageWidthConsistencyTest.php`. The cause was not in that
test:

```php
// CollectionFactory and AuthorFactory
'user_id' => User::factory(),                      // a *different* user
'is_private' => $this->faker->boolean(20),         // 20% private
```

A private record owned by an unrelated user makes `Gate::allows('view', …)`
fail, so the component renders `abort(403)` instead of the page, and any
assertion about the rendered markup fails at random. Both factories are used
across 32 test files, so this was a mine under any test that renders a
policy-guarded view.

`MusicFactory` already had the right shape, and both factories were brought in
line with it: `is_private => false` by default, plus a `private()` state so a
test that wants the private case says so. The previously flaky test then passed
six runs out of six, and the full suite passed — confirming nothing had been
relying on the randomness.

## Tests

- **`tests/Feature/WelcomeHeroTest.php`** (new, 13 cases): the page leads with
  what the application is; the pitch stays folded; the three tools are present
  and linked; the long tool name is shortened for a phone; captions are
  desktop-only; the planner header is not spread to the far edge; the next-step
  sentence is shown; the chain reads in working order; both calls to action are
  offered; ordering of positioning → tools → calendar; a signed-in visitor is
  redirected to the dashboard.
- **`tests/Feature/LiturgicalInfoTest.php`**: one case added asserting the card
  answers to `Énekrendtervező` in both the welcome and the dashboard variant.

Full suite after the change: 1816 passed, 4 skipped.

## Deliberately Not Done

- **No registration button in the collapsed strip.** The calls to action live
  inside the disclosure so the strip stays one line. The public header offers
  only login, not registration, so this is the one conversion path that got
  quieter; a button beside the heading would cost no height if that turns out to
  matter.
- **No dedicated feature page.** The chain lives on the front page and in
  `/about`; a separate `/muhely` route would be one more surface to keep current,
  and `/guide` already does the long-form job.
