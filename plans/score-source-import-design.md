# Score Source Import Design

## Overview

A cantor preparing Sunday's service opens the score editor, types a title, and gets nothing but an empty document. The text of the hymn exists — in Diatár's DTX collection and in the Ászáf OpenLyrics database, both on GitHub, both covering nearly every collection in our database — but getting it here means leaving the site, finding the right file among 72, locating the song among several hundred, and pasting by hand. Every cantor who needs it does that work again.

This document designs an **import panel in the score editor**: given the music being edited, the site searches the external catalogues, proposes the matching entries, and the text arrives in the editor ready to be corrected. What the cantor then builds — fixed typos, re-broken lines, a reordered verse, a prelude chord — is their own private collection, accumulated as a by-product of preparing services they were preparing anyway.

Two facts about the current codebase shape everything below:

1. **`cantores:dtx-convert` already downloads the verses and throws them away.** It fetches a `.dtx` from `raw.githubusercontent.com`, parses it, keeps the song number and the first lyric line as a title, and drops every remaining verse on the floor. The parser exists; what it retains is the only thing that has to change.
2. **Nothing records where a score's content came from.** `ScoreFileRights` makes an uploader declare what they may do with a *file*, and `ScorePublication` records a source once a score is nominated. A score's `content` column — the ABC, GABC, ChordPro or Aretino source someone typed — carries nothing, because until now it was assumed to be typed by hand. That assumption was already wrong for the DTX imports and the uploads sitting here today; importing makes it plainly wrong.

## The posture

The site is a storage and display service. Content a user brings in is theirs, held on their behalf, and the legality of holding and lending it is theirs to answer for. This is not a new position taken for this feature — `terms.md` §5 already states it, in the same words and with the same comparison:

> **A Cantores.hu a titkos linken megosztott tartalmakat nem nézi át, nem ellenőrzi előzetesen, nem moderálja és nem befolyásolja** – ugyanúgy, ahogy a Google Drive vagy a OneDrive sem vizsgálja, ki mit oszt meg a saját meghajtójáról. A platform ebben pusztán technikai tárhelyet és megjelenítést biztosít.

So the design does not need to establish the posture. It needs to **not undermine one that already exists**, and that is a sharper constraint than it sounds. A storage service is protected in respect of what its users put there. It is not automatically protected in respect of what it does itself, and searching, fetching and converting are things the site does itself.

Everything below follows from one invariant:

> **The site knows the catalogue. The user makes the copy.**

A catalogue of titles, song numbers and incipits is bibliographic reference — the same facts a library index holds, and the same facts `bulk_imports` already holds today. The hymn text is the work. Keep the first on the server; never let the second come to rest there.

## Catalogue and content are different things

| | Catalogue | Content |
|---|---|---|
| What | title, incipit, collection, song number, page, tag, source URL | the verses, the chords |
| Volume | ~15 000 rows across both sources | 22 MB across both sources |
| Held | on our server, indexed, searchable | never; passes through the browser into one user's score |
| Refreshed | by a console command, on our schedule | never — each import is one user's one-off fetch |
| Legal character | facts and short phrases | the protected work |

The catalogue is what makes the feature possible and it is the part that is safe to hold. Building it is mostly widening what `DtxConvert` already writes: the same `bulk_imports` shape, extended with which source a row came from, how to reach that one song inside it, and what licence the source carries.

For Ászáf the catalogue is cheaper still — one song per file, so the GitHub tree listing plus each file's `<title>` and `<songbook entry>` is the whole index, and the tree comes back in a single API call.

## Where the fetch happens

This is the design's one real decision, and it should be made before anything is built.

`raw.githubusercontent.com` responds with `access-control-allow-origin: *`. Verified on both repositories. **The browser can fetch the source files directly.** That makes a client-side import genuinely available, not merely theoretically nicer.

| | Server fetches | Browser fetches |
|---|---|---|
| Who makes the copy | the site, on the user's behalf | the user |
| Source bytes on our disk | in transit, and in any cache we add | never |
| Matches the stated posture | by assertion in the T&C | at the network level |
| Rate-limit exposure to GitHub | our IP, all users | each user's own |
| Where the DTX parser lives | PHP, beside `DtxConvert` | JS, in the editor |
| Build cost | lower | one parser written in JS instead of PHP |

The cost line is smaller than it looks. The verse-retaining DTX parser **does not exist in PHP either** — `DtxConvert::parseDtx()` deliberately keeps only the incipit, and a handout needs every verse, the `/N` liturgical labels, and `\.` treated as a line break rather than a space. So this is not duplicating a parser; it is choosing which language to write a new one in. OpenLyrics needs no parser at all: it is XML, and `DOMParser` reads it natively.

**Recommendation: the browser fetches.** The server hands over a URL and an offset into the file; the browser fetches, parses, converts, and pushes the result into the editor's `content`, where autosave picks it up like any other typing. The site then holds exactly what it claims to hold, and the sentence in the terms describes the architecture rather than papering over it.

The consequence to accept: a source that ever stops sending CORS headers stops being importable, and the parser runs on the client where we cannot fix a bad parse by deploying. Both are acceptable. Neither is worth trading the posture for.

## Sources are not equivalent

Treating both corpora as "external text of unknown status" is the easy path and it is wrong, because it throws away the one source that is actually free.

**Ászáf** ([github.com/gyuris/aszaf](https://github.com/gyuris/aszaf)) is CC BY-SA 4.0. Content imported from it may be lent, published and redistributed, provided the attribution and the share-alike obligation travel with it. `ScoreLicense::CcBySa` already exists and `ScoreAttributionBuilder` already emits the credit line it requires. An Ászáf import should arrive with its licence and attribution prefilled and its route to `/ingyenes-kottak` open.

**Diatár** ([github.com/diatar/diatar-dtxs](https://github.com/diatar/diatar-dtxs)) carries no licence file of any kind, and its own headers name the source books — *"Forrás: SzVU! (Szt. István kiadó, 1983)"*. Content imported from it arrives with no licence, cannot be nominated for publication, and says so at the moment of import.

So the importer is **source-aware**, and the policy is a small table rather than logic:

| Source | Format | Licence on import | May publish | Attribution |
|---|---|---|---|---|
| Ászáf — chorded folders | OpenLyrics + `<chord>` | `CcBySa` | yes | prefilled, mandatory |
| Ászáf — plain folders | OpenLyrics | `CcBySa` | yes | prefilled, mandatory |
| Diatár | DTX | none | no | source noted, not a licence |

Adding a third source later is a row in this table plus a parser, which is the right shape for something that will happen.

## `scores.source` — one free-text field

The missing model concept, and it is smaller than it first appears.

`ScoreFileRights` answers *"may cantores.hu hold this file?"* and `ScoreLicense` answers *"may a visitor take it away?"*. Neither reaches a score's `content`. Add the third question — *where did this come from* — as **one nullable free-text column**:

```
scores.source  text, nullable    "Diatár · szvu.dtx 41" · "Ászáf · Sárga könyv 44"
                                 "Emmánuel énektár, 2019-es kiadás, 112." · "saját gépelés"
```

Free text rather than a typed provenance record, for three reasons that all point the same way:

1. **It applies retroactively.** Scores already here came from DTX imports, file uploads and typing, and many carry copyrighted material. A free-text field can be filled in for any of them, by hand, at any time. A five-column typed record cannot — it would be null for everything that already exists, which is most of the value.
2. **The typed half already exists on the published axis.** `ScorePublication` carries `source_url`, `source_title`, `license`, `outbound_license`, `license_version`, `attribution_line`, `rights_note` and `permission_evidence`, and `ScorePublicationRules` already enforces them at nomination. A second typed system on `scores` would duplicate a gate that works.
3. **A storage provider records, it does not adjudicate.** Free text is a note about where something came from. A typed enum reads as the site's own classification of a work's status, which is a claim we have chosen not to make.

So the split is clean and needs no new gate: **`scores.source` is the private note; `ScorePublication.source_*` is the public claim.** Nothing on the private axis is blocked by what `source` says, because nothing on the private axis is published — and the moment a user nominates, the existing rules ask them the harder questions properly.

The import writes it automatically, in a form a person can read two years later. The user can edit it, because it is their note. `ScoreDuplicator` copies it forward, since a variation of an imported score has the same origin; it already declines to copy the publication and the share link, which is the right instinct for the same reason.

Displayed on the score page as a quiet line — *Forrás: Diatár · szvu.dtx 41* — it also does the posture's work in the place it matters most: on the score itself, it is visible that this text came from somewhere else and was not authored here.

## The source list is the interface

The search should name the places it is looking, in order, as it looks:

> *Keresés: Diatár → Ászáf → Emmánuel közösség énektára → Népénektár → Dúr…*

This is worth building deliberately rather than treating as a spinner. It is the clearest possible statement of what the site is doing — looking things up elsewhere on the user's behalf — and it puts the sources' names in front of the cantor, which is both honest and useful, since they know these books by name.

But the list should name **books, not repositories.** A cantor knows *Emmánuel közösség énektára*; nobody knows `diatar-dtxs`. This matters more than presentation, because it dissolves most of the integration work:

**Emmánuel is already inside Diatár.** `emmanuel.dtx` holds 357 songs, headed *"Az Emmánuel közösség énekei — www.emmanuel.hu/enekek_mobilra"*. **Dúr is too** — `dur.dtx` and `dicserjetek.dtx`, with a further 601 in Ászáf's *Dicsérjétek az Urat!* folder. Diatár is not one source; it is **72 named books**, each declaring its own long name (`N`), short name (`R`) and group (`C`) in its header — the same fields `_list.php` reads. Ászáf is 28 named folders.

So the catalogue should record the **book** an entry came from as well as the corpus, and the search display names books. Five sources in the UI, two integrations underneath, and no dishonesty in the gap — the entries really are from those books.

That leaves two genuine classes of source:

| Class | Search | Import | Examples |
|---|---|---|---|
| **Indexed** | our catalogue, instant, offline | browser fetches the one song | Diatár's 72 books, Ászáf's 28 folders |
| **Linked** | we hold a URL per music, or none | we hand over the link; the user goes there | nepenektar.hu, durkonyv.hu |

A linked source is not a lesser result — for nepenektar.hu it is the *better* one, because the melody, the accompaniments and the audio are all there and none of them belong here. And we already have those links: `NepenektarScrapeCommand` mapped them onto `MusicUrl` rows with the `sheet_music` label, so for a music that has one, the panel can show it immediately with no request at all.

**On durkonyv.hu specifically:** its WordPress REST API is open and, checked against a `cantores.hu` origin, it reflects CORS — so a browser query would work architecturally. But the site has 5 pages and 0 posts, and a search for a known incipit returns nothing: the songbook is not in the WordPress content. Whatever serves the DÚR book there has to be looked at before it can be a search target. Until then it is a linked source, and Diatár and Ászáf already cover DÚR between them.

## Finding the candidates

Every piece of matching machinery this needs already exists and has been exercised on real data.

**By song number** — the strongest signal, and exact. `NepenektarScrapeCommand` already joins external references onto `MusicCollection` by `collection_id` + `order_number`, with a `page_number` fallback for page-only references. Ászáf's `<songbook name="…" entry="N"/>` and Diatár's `>N` are the same key in different clothes. The collection abbreviations line up almost one-to-one: `eneklo_egyhaz.dtx` → ÉE, `szvu.dtx` → Ho, `dicserjetek.dtx` → DÚR, `graduale.dtx` → GH, `sargakonyv.dtx` → SK, `kekkonyv.dtx` → KÉK, `zoldkonyv.dtx` → ZK, `taize.dtx` → TZ, `szentandras.dtx` → SZTA, `barnakonyv.dtx` → BK.

**By title** — the fallback, for musics with no collection reference and for cross-collection matches. `Music` already denormalises every title variant into `titles` and searches it with `#[SearchUsingFullText(['titles'], ['language' => 'hungarian'])]`, and the catalogue's `piece` column holds incipits in the same shape.

Ranking those hits needs no new work either. `TitleSimilarity` was written for this exact problem, and its docblock states it: *"Titles for the same song vary wildly between songbooks (a verse's first line, the chorus' first line, the official title), so a similarity score below the import threshold is treated as 'probably different songs' and surfaced for a manual merge/separate decision rather than merged automatically."* That is the Diatár/Ászáf matching problem described before it was encountered, and its behaviour on a weak match — surface it, do not decide it — is the behaviour this panel wants.

Candidates are ranked exact-number first, then title score, and every candidate shows which source, which collection, which number, and its first line — so the cantor confirms the match rather than trusting it. **The site proposes; the user chooses.** That is not only better UX, it is the posture again: a proposal the user accepts is the user's decision.

## The editor flow

1. The cantor opens a score attached to a music and presses **Szöveg keresése** — prominent in the empty state, always present in the toolbar. It lists candidates from the catalogue, resolved from the music's collection references and titles.
2. Each candidate shows source, collection, number, incipit, and its licence state — plainly: *"CC BY-SA 4.0 — közzétehető"* or *"Nincs megadott licenc — csak magánhasználatra"*.
3. The cantor picks one. A dialog states what will happen and asks them to confirm the same kind of declaration an upload asks for.
4. The **browser** fetches the source file, extracts that one song, and emits it in the format the editor is already in.
5. The emitted text is **appended** to whatever is there. `scores.source` is written; `variation_name` names the book it came from — *"Diatár szerint"* — so two imports of the same hymn are distinguishable in a list.
6. The cantor edits normally. Autosave, variations, lending, folders and PDF export all work already and need no changes.

DTX converts to ChordPro as verse blocks, with `/N Kezdetre` becoming a comment directive so the liturgical label survives into the printed sheet. Ászáf's `<chord name="Em"/>` becomes `[Em]` inline — a mechanical transform, and the reason the 208 chorded Sárga könyv songs are the right first slice.

## The format keeps its name; the import finds the cantor

**The ChordPro button stays ChordPro.** Renaming it to *Szöveg* was a way to make the words findable, and it is the wrong lever: it would rename a format after one of its uses, leave the other three formats no better off, and still not be where a cantor looks. The discovery problem is real, but it is solved by putting the import where the cantor already is — and then every format benefits, not just one.

### Nobody will find this by picking a format

The real risk is not the name. It is that *choosing a notation format* is not a thing a cantor does when what they want is the words. Whatever the fourth button is called, "I need the text of this hymn" does not lead anyone to press it. **Import must not sit behind a format choice.**

It does not have to. Two things in the editor already solve this:

**1. The empty state is already an affordance, and it is format-independent.** The editor renders a ghost button under the text area:

```blade
<div x-show="localContent.trim() === '' || localContent === minimalExamples[$wire.format]" class="flex">
    <flux:button size="sm" variant="ghost" icon="light-bulb" x-on:click="fillExample()">
        {{ __('Show me an example') }}
    </flux:button>
</div>
```

It is shown exactly when the editor is empty — which is exactly when "get me the words" is the live intent — and, unlike almost everything else in that column, it is **not** wrapped in an `x-show="$wire.format === '…'"` guard. A *Szöveg keresése* button beside *Mutass egy példát* is therefore discoverable no matter which format the cantor landed on, and it reads as the other answer to the same question the empty editor is already asking.

**But it cannot live only there.** Because an import appends, the highest-value case is a score that is *not* empty: verse 1 engraved, verses 2…n fetched. If the entry point disappears the moment the editor has content, that case is unreachable. So the empty state gets the prominent version and the toolbar keeps a quiet one — the same action, always available, worded the same way.

**2. The import adapts to the format; the cantor does not have to.** A cantor sitting on an ABC score searches, picks a Diatár result, and gets `w:` lines — not a format switch and not a ChordPro document. The format the cantor chose is the one they get, and the emitter is what varies. Nobody has to know what ChordPro is to ask for the words, and nobody has their notation format changed underneath them for asking.

The format picker itself stays a format picker. It already squeezes five buttons onto the narrowest phone — the blade comment says so — and import is an action, not a format.

### The variation name, checked against the data

`Score::variationLabel()` documents `"Csak szöveg"` as a variation name beside *"Fuvola"* and *"Kórus"*, which raised the question of whether imports should default it and to what. Against the actual rows, the field is barely used at all:

| Count | Format | Variation name |
|---:|---|---|
| 193 | abc | *(empty)* |
| 116 | aretino | *(empty)* |
| 6 | gabc | *(empty)* |
| 3 | chordpro | *(empty)* |
| 1 | abc | Egy szólamú verzió |
| 1 | abc | Csak szöveg |
| 1 | chordpro | Fuvola |
| 1 | gabc | Kórus |

**319 of 323 scores carry no variation name at all**, and three of the four that do are the docblock's own examples typed in verbatim — test rows, not usage. (Dev database; production may read differently, but the shape is unlikely to invert.)

That is the argument for defaulting it. **The import will be the first thing to create sibling scores at volume.** A cantor who takes the same hymn from Diatár and from Ászáf gets two scores with the same title, the same format, and — on 98.8% precedent — two empty variation names and nothing to tell them apart in a list.

So an import writes *"Diatár szerint"* or *"Ászáf szerint"*. It is the first time the field has had a real job, and the same hymn as two books print it is exactly the axis it was added for.

## Importing into any format

Once the import emits for the format the cantor is already in, the target stops having to be ChordPro. All four formats hold lyrics, and each gets its own design rather than being routed through one:

| Format | Where the text lands | Alignment needed |
|---|---|---|
| **ChordPro** | verse lines directly, `{comment:}` for the liturgical label | none |
| **Aretino** | `W:` for flowing text; `w:` for aligned, `~~` to bind the verse number | none for `W:` |
| **ABC** | `w:` lines, one per verse | syllables to notes |
| **Gregorio** (GABC) | `syl(note)` pairs, `<alt>` for a second text line | every syllable |

Two of these are free, and one of them is the case that matters most.

**Strophic hymns make verses 2…n nearly automatic.** A népének is one melody sung to N verses with the same syllable structure — that is what strophic means. Both ABC and Aretino take **multiple `w:` lines as multiple verses**, aligned to the same notes. So for a score that already has verse 1 engraved with its `w:` line, importing the remaining verses is *append more `w:` lines*, and the alignment is inherited by construction rather than computed. A cantor who has typed the first verse of an eight-verse ÉE hymn gets the other seven for the price of one click, correctly aligned, with no syllabifier involved.

**Aretino's `W:` needs no alignment at all** — the cheatsheet calls it *"folyó zsoltárvers / responzórium szövegsor"*, a flowing text line. And `~~` exists to bind a verse number to its text (`1.~~Ky-ri-e`), which is exactly the shape a numbered hymn verse arrives in.

**Syllabification is the one genuinely hard piece**, and it is needed only in the narrow case: an ABC or GABC score with notes but no existing `w:` line to copy a hyphenation pattern from. Neither source stores syllable boundaries — Diatár's `\.` is a projection line-wrap hint, not a syllable mark. Three honest answers, in order of preference:

1. **Copy the pattern from verse 1** where one exists and the syllable counts match. This covers the strophic case, which is most of the repertoire.
2. **Import unhyphenated** and let the cantor insert the hyphens. `*` skips a note and `_` extends a syllable, so the tools to fix it by hand are already in the format and already documented.
3. **Hungarian syllabification** as a later refinement. Regular enough to automate, not worth blocking anything on.

GABC is the weakest target and should be last: every syllable must be bound to a neume, so an import without alignment produces a skeleton rather than a score. Worth doing eventually for the `<alt>` secondary text line — a Hungarian translation under a Latin chant is a real use — but not in the first pass.

### Never replace

**An import appends. It never overwrites what is already in the editor.** One rule, no modes, no confirmation dialog about losing work — because there is no case in which work is lost.

This is worth stating as an invariant rather than a default, because the tempting shortcut is exactly the wrong one: an empty editor *looks* like a safe place to just assign `content`, and then the one time it is not empty — a cantor who typed two verses from memory before thinking to search — the import eats them. Append handles both, so there is no reason to have two paths.

It also removes a decision from the interface. One button, one word, one behaviour, whatever state the editor is in. *Szöveg beillesztése* always means the same thing.

The mechanics are already there. `setEditorContent()` in `resources/js/score-editor.js` is the single write path — it sets `localContent` and `$wire.content`, syncs the Aretino editor and schedules a re-render — so appending is `setEditorContent(localContent + emitted)` and every downstream concern is handled. For an empty document in a format that needs a frame, `minimalExamples` already holds a valid skeleton per format:

```js
minimalExamples: {
    abc:      'K:C\nL:1/4\nC D E|]\nw: Glo-ri-a',
    gabc:     '(c3) Glo(f)ri(g)a.(h.) (::)\n',
    aretino:  '(g2) g a b ||\nw: Glo-ri-a\n',
    chordpro: '{title: }\n[C]Glo-ri-[G]a [C]Deo\n',
}
```

An emitter that needs a header seeds from there and appends into it, which is the same append rule applied to a document the import itself created.

## Sources as strategies

Each source gets its own code. The question worth settling first is *which* code, because sources vary along more than one axis and a single interface spanning all of them would be the wrong shape.

### The split falls out of where the fetch happens

Because the browser fetches the content and the server holds the catalogue, the varying work lands in **two languages**:

| | Runs | Language | Varies by |
|---|---|---|---|
| **Cataloguing** | console command, periodically | PHP | corpus layout |
| **Fetch + parse** | browser, on one import | JS | source file format |
| **Emit** | browser, on one import | JS | target score format |
| **Searching** | server, per keystroke | PHP | **nothing** |

Two strategy families, not one. Trying to make a single interface span both would force either the parser onto the server — undoing the posture the fetch decision bought — or the cataloguer into the browser, which is nonsense for a periodic job.

**`SourceCatalog` (PHP).** Walks a corpus and emits `bulk_imports` rows. Diatár and Ászáf share nothing here: one fetches 72 flat text files and parses a bespoke line format keyed on the first character; the other lists a git tree in one API call and reads XML. Genuine strategy.

**`SourceImporter` (JS).** Fetches one song by its `source_ref` and parses it. DTX line-format versus OpenLyrics XML. Genuine strategy, dispatched on the catalogue row's `source`.

**`FormatEmitter` (JS).** Renders a parsed song as ABC, Aretino, GABC or ChordPro. Genuine strategy, dispatched on the score's `format`.

Splitting these two apart is what keeps importing-into-any-format from turning into a matrix. Both meet at a plain intermediate — an `ImportedSong` carrying the title, the book, the number, and verses of labelled lines with optional chords:

```
DTX ─┐                    ┌─ ChordPro
     ├─▶  ImportedSong  ──┼─ Aretino
XML ─┘                    ├─ ABC
                          └─ GABC
```

Two sources plus four formats is **six small classes, not eight combinations**, and adding either a source or a format is one class rather than N. The intermediate is also where a verse's liturgical label from DTX survives, to be emitted as `{comment:}` in ChordPro or dropped where a format has nowhere to put it.

**Search is not a strategy.** Once catalogued, every indexed source is the same query over `bulk_imports` — number match, then `TitleSimilarity`. A `search()` method per source would produce N identical implementations, which is the usual way a Strategy goes wrong. The catalogue exists precisely so that search stops caring where a row came from.

### Linked sources are not strategies either

nepenektar.hu and durkonyv.hu have no corpus to walk and no file to fetch. Putting them behind `SourceCatalog` means three no-op methods, which is the smell that says the abstraction is being stretched to cover a different thing.

They *are* a different thing. The panel's polymorphism belongs over **results**, not over sources: an import result offers a button that fills the editor; a link result offers a link that leaves the site. For nepenektar.hu the link result needs no source integration at all — the `MusicUrl` rows are already there with the `sheet_music` label.

### Identity and policy go on an enum, not the interface

This codebase has essentially no interfaces — `Http/Controllers/Controller.php` and nothing else. The established pattern is concrete services in `app/Services` plus enums carrying `match`-based behaviour: `ScoreLicense` has ten such methods, `ScoreFileRights::mayBePublished()` and `ScoreLicense::isRedistributable()` are policy questions answered by the enum itself.

Follow it. A `MusicSource` enum holds what a source *is*; the interface holds only the algorithm:

```php
enum MusicSource: string
{
    case Diatar = 'diatar';
    case Aszaf  = 'aszaf';

    public function label(): string;            // 'Diatár'
    public function license(): ?ScoreLicense;   // null | CcBySa
    public function isIndexed(): bool;          // false for linked sources
    public function catalog(): SourceCatalog;   // the strategy
}
```

**The enum is the registry; the interface is the behaviour.** Adding a source is one enum case, one catalog class, one JS importer, and one `WhitelistRule` row — and `license()` answering `null` is what makes Diatár imports unpublishable without a second gate anywhere.

### This pays off a debt that is already visible

The Strategy is not speculative generality here. `DtxConvert` currently carries four options — `--title`, `--csv`, `--special`, `--skip-unknown-tags` — dispatching between `parseDtx()`, `parseSpecialDtx()` and a CSV path, with `$isTaize = strtolower($collection) === 'taize'` hardcoded *inside* the parser to give one collection different title logic. That flag soup is the missing abstraction, showing up as conditionals. Splitting it is cleanup, not new architecture.

### Granularity: the format, not the book

The trap this invites is one strategy per book — 72 Diatár files plus 28 Ászáf folders, a hundred classes. Wrong axis. **What varies is the format; the books are data.** Two catalog strategies cover all hundred.

Per-book quirks — Taizé titling from the second verse, a collection that numbers its entries differently, a tag vocabulary to validate — belong on the catalogue row or in a small per-book options struct passed to the strategy, never in a subclass. `$isTaize` inside `parseDtx()` is exactly this mistake in miniature, and it is worth naming so the rewrite does not reproduce it one level up.

## Takedown has to reach lent scores

The posture's obligation. A storage service keeps its protection by acting on notice, and the gap here is specific and worth closing in the same pass.

Today the report button lives on **public** score pages only — `kotta-jogok.md` says *"Minden nyilvános kotta alján"* — and `ScoreRightsReportService::file()` is written around a publication. For lent content the terms route complaints to `/contact` and promise the link can be killed without warning, which works but is a second, weaker path.

This feature will grow the private-and-lent surface substantially. The report path should be the same one:

- The report button appears on the lent-score view as well as the published one.
- `ScoreRightsReport.score_publication_id` is **already nullable**, so a report against an unpublished score needs no migration.
- The action available to an editor differs by axis: a published score is withdrawn from the library; a lent score has its loans revoked. `Loan` is already the single revocation point for a whole downstream chain, so this is one call.
- Provenance appears on the report when present, which is often the fastest way to settle one.

## Two frictions with the current terms

Both are wording, both are cheap, and both would be awkward to discover later.

**1. The prohibited-use list rules out what this feature encourages.** §7 forbids *"a szolgáltatás általános fájltárként, felhőmeghajtóként vagy biztonsági mentésként való használata"* — while §5 explains the lending model by comparison to Google Drive. The intent is clearly "don't park unrelated files here", but a private library of imported lyrics sits close enough to the line to want the clause narrowed to content unrelated to church music service.

**2. The same list forbids placing unlawfully copied text.** §7 forbids *"jogsértő, jogosulatlanul másolt vagy engedély nélkül megosztott kotta, felvétel vagy szöveg elhelyezése"*. The site cannot both prohibit an act and provide a button for it. The resolution is the design itself: the browser fetches, the user declares, the site records the declaration and never asserts the copy is lawful. The clause should be reconciled so that the user's declaration at import is what carries the obligation — exactly how uploads already work through `ScoreFileRights`.

Neither changes the posture. Both make the written terms describe the software that will actually exist.

## Rejected alternatives

**Bulk server-side import of both corpora.** The obvious approach and the one that quietly destroys the posture: a server holding all 22 MB of hymn text has reproduced two corpora, whatever the UI says about private use. The catalogue/content split exists to avoid precisely this, and it costs almost nothing because the catalogue is what `bulk_imports` already stores.

**A server-side cache of fetched source files.** Tempting for performance — `szvu.dtx` is 382 KB and would otherwise be re-fetched per import. Rejected because a cache of source files is a library, and the distinction between "a library" and "a fetch that happened to be fast twice" is exactly the one being protected. If import latency ever becomes a real complaint, the answer is a smaller per-song locator in the catalogue, not a copy on our disk.

**A lyrics table with one row per verse.** Better data: it would let a handout print only the verses actually sung and let `MusicPlanSlot` suggest by *Felajánlásra*. Rejected for now because publication, rights, versioning, lending and export would all have to be built again alongside the `Score` ones, to serve a per-verse selection nobody has asked for yet. A lyrics-only ChordPro score inherits all of it today. Revisit if per-verse selection turns out to matter.

**Publishing imported Diatár text under a claim of private copying.** Not attempted. Private use is the user's defence for their own copy; it is not a basis on which the site publishes anything to an open-ended public. The public library keeps the review queue and the licence requirements it already has.

## Schema changes

```
bulk_imports
  + source          string    'diatar' | 'aszaf'
  + source_book     string    'Emmánuel közösség' | 'Sárga könyv' — the name shown to the cantor
  + source_ref      string    'emmanuel.dtx#112' | 'other/Sárga könyv (akkordos)/A föld….xml'
  + source_license  string?   ScoreLicense value, null for Diatár
  + index (source, collection, reference)

scores
  + source          text?     one free-text note, e.g. 'Diatár · szvu.dtx 41'

whitelist_rules
  + row: raw.githubusercontent.com /diatar/diatar-dtxs/
  + row: raw.githubusercontent.com /gyuris/aszaf/
```

`source_book` is what makes a five-source search display honest over two integrations, and it comes free: every DTX declares its own name in the `N` header line, and every Ászáf folder is named on disk.

The whitelist rows are data, not code — `UrlWhitelistValidator` already matches on hostname, scheme and path prefix, and this is what it is for.

## Build order

Each step is useful on its own, and the legally clearest material moves first.

1. **Catalogue the sources.** The `MusicSource` enum, the `SourceCatalog` interface, and two implementations: `DiatarCatalog` walking all 72 DTX files, `AszafCatalog` reading the GitHub tree. One console command iterates the enum's indexed cases. `DtxConvert`'s option flags and its `$isTaize` branch are absorbed here rather than carried forward. No content stored, no `Music` rows written. Ends with a searchable catalogue of roughly 15 000 entries across 100 named books.
2. **`scores.source`.** One column, shown on the score page, carried forward by `ScoreDuplicator`, editable by the owner. Ship it before the import UI: it is worth having on the scores that are already here, and backfilling it for the existing DTX imports and uploads is a separate, useful piece of work that does not wait on anything.
3. **Import from Ászáf's chorded Sárga könyv, into ChordPro.** The first `SourceImporter` and the first `FormatEmitter`: 208 songs, CC BY-SA, matched on `entry` → `order_number`, fetched and emitted in the browser, **appended**, landing as a *"Ászáf szerint"* score with `source` written. OpenLyrics first because `DOMParser` reads it and no parser has to be written; ChordPro first because it needs no alignment. The first end-to-end slice, and the one where every gate is open.
4. **The source list display.** The named, ordered search across books, with linked sources — nepenektar.hu from the `MusicUrl` rows we already hold — sitting beside the indexed ones.
5. **Extend to the rest of Ászáf**, then to Diatár once the verse-retaining DTX parser is written in JS. Diatár imports arrive with no licence and cannot be nominated, which the existing `ScorePublicationRules` already handles by asking for one.
6. **Emitters beyond ChordPro**, one design per format. Aretino next — `W:` needs no alignment and `~~` already carries verse numbers — then ABC, where appending `w:` lines to a score that has verse 1 covers the strophic case without a syllabifier. GABC last, or not at all until the `<alt>` translation use makes it worth the alignment work.
7. **Takedown parity.** The report button on lent scores, revocation as the editor's action, `source` shown on the report.
8. **Terms reconciliation.** The two §7 clauses above, alongside a description of the import feature in §5.

Steps 7 and 8 are not optional extras at the end. They are what the posture in §5 of the terms costs, and they should ship no later than the first Diatár import.
