# Linked Scores in Booklets, and Not Rendering the Same Bytes Twice

## Context

Two problems that turn out to share one answer.

**1. A linked score cannot go into a booklet.** A great deal of the repertoire people
actually print is already on the web — durkonyv.hu serves direct PDFs and is already
mapped onto our musics, and cantors keep their own engravings on Drive. Today those
arrive as a `ScoreUrl` (encrypted, per-score) or a `MusicUrl` with the `sheet_music`
label, and a score that holds only links has `format = null` and no files. That is
exactly the case `MusicPlanScoreListService::sourcesFor()` drops on the floor:

```php
// app/Services/MusicPlanScoreListService.php:150-155
$files = $score->format === null ? $this->drawableFiles($score) : [];
$default = reset($files) ?: null;

if ($score->format === null && $default === null) {
    return [];   // ← the linked score silently vanishes from the booklet
}
```

A booklet row draws systems, systems come from `score_files.strips`, and strips come
from `RenderScoreFileJob`. No file, no strips, no row. The cantor sees the score in the
plan and cannot put it in the handout.

**2. The same PDF is rendered, and stored, once per upload.** `score_files.checksum` is
a sha256 of the plaintext bytes with a **non-unique** index, used only for ETags, the
publication fingerprint, and re-review triggering. Ten people uploading the same
`Éneklő Egyház 213` PDF get ten directories, ten MuseScore/poppler runs on the
`musescore` worker, and ten sets of page SVGs. Nothing anywhere notices. This gets
worse the moment linked fetching exists, because a public link is by construction the
same bytes for everybody who follows it.

### The intended outcome

A cantor presses one button beside a sheet-music link, declares what they declare for
an upload, and the score is in their library and printable in a booklet — with the
second and every later person to press it paying nothing for the engraving.

### The posture, and why this does not break it

`score-source-import-design.md` argues a position the site depends on, and explicitly
rejects "a server-side cache of fetched source files" because *"a cache of source files
is a library, and the distinction between 'a library' and 'a fetch that happened to be
fast twice' is exactly the one being protected."*

That rejection stands, and this feature does not contradict it, because the thing it
was protecting against is not what is being built. What was rejected was **the site**
assembling a corpus on its own initiative, keyed to nothing anyone asked for. What is
built here is **one person**, by one deliberate act, putting one file into their own
private library, under the same `ScoreFileRights` declaration an upload demands, inside
the same quota, reachable by the same takedown. The site does not decide to hold
anything; it does what it is told, once, and records who told it.

So the invariant the import doc states — *"the site knows the catalogue, the user makes
the copy"* — survives intact, and the design turns on one rule that keeps it true:

> **The fetch is an explicit act by a named user, or it does not happen.**
> No lazy fetch, no fetch-on-attach, no background warming, no system-owned copy.

This is the single thing that must not be traded away for convenience later. A link
"helpfully" fetched when it is pasted, or when a booklet first needs it, is the site
downloading on its own initiative, and it is that — not the bytes on the disk — that
would undo the position.

Two consequences to accept, and state to the user at the button:

- A fetched file is an upload. It counts against storage the same way, it is subject to
  a rights report the same way, and it may be published only if its declared rights
  permit it.
- Nothing is refreshed. The copy is the copy taken that day. If the source changes, the
  user fetches again; `source_url` is stored so that later becomes a one-liner.

---

## Phase 1 — Bring a linked score into the library

The whole of this phase exists to turn a URL into an ordinary `ScoreFile`. Once it is
one, **nothing downstream changes at all**: `RenderScoreFileJob` cuts it into strips,
`booklet_scores.score_file_id` already names a chosen file, `BookletEditor::renderPayload()`
already emits `pageUrl` + `rect`, `BookletScorePageInliner` already inlines it into the
export. No booklet code is touched in this plan.

### 1.1 Eligibility is data, not code

`whitelist_rules` already gates `MusicUrl` through `UrlWhitelistValidator`, is
operator-managed at `resources/views/pages/admin/url-whitelist.blade.php`, and already
refuses userinfo, non-http(s) schemes and redirector query parameters. Reuse it, but do
not conflate *may be linked* with *may be fetched*: a blog post may be worth linking and
must never be fetched.

Migration on `whitelist_rules`:

```
+ allows_link   boolean default true    backfilled true — preserves MusicUrl behaviour exactly
+ allows_fetch  boolean default false   opt-in, per rule
```

`UrlWhitelistValidator` gains one method beside `validate()`, sharing
`parseAndValidateUrl()` and `ruleMatches()` unchanged:

```php
public function mayFetch(string $url): bool;   // matching rule AND allows_fetch
```

and `validate()` gains `->where('allows_link', true)` so adding a fetchable Drive rule
does not quietly widen what may be pasted onto a music.

Seed rows (a migration, so a deploy has them; editable in the admin screen afterwards):

| hostname | path_prefix | link | fetch |
|---|---|---|---|
| `durkonyv.hu` | `/` | ✓ | ✓ |
| `drive.google.com` | `/file/d/` | ✗ | ✓ |
| `drive.google.com` | `/uc` | ✗ | ✓ |
| `www.dropbox.com` | `/s/` and `/scl/` | ✗ | ✓ |
| `1drv.ms` | `/` | ✗ | ✓ |

The personal-cloud hosts are `allows_fetch` only: a private Drive link belongs on a
score, not in the public music catalogue.

**Deliberately excluded: fetching an arbitrary URL.** Every entry URL matches a rule an
operator put there. This is what keeps the feature from becoming a general-purpose
downloader, and it is the cheapest possible answer to most of the abuse surface.

### 1.2 `app/Services/ScoreLinkResolver.php` (new)

A share URL is not a file URL. One `match` on host turns the former into the latter:

- `drive.google.com/file/d/{ID}/…` → `https://drive.google.com/uc?export=download&id={ID}`
- `www.dropbox.com/…?dl=0` → same URL with `dl=1`
- `1drv.ms/…` → append `?download=1`
- anything else → unchanged

One arm per provider, no interface, matching the codebase's concrete-service style.
Returns the URL to fetch; the *original* is what gets stored as provenance.

### 1.3 `app/Services/ScoreLinkFetcher.php` (new)

The one place that reaches the open internet for user-named content. There is no
existing SSRF-guarded fetcher to copy — the three `Http::` call sites
(`DtxConvert`, `LiturgicalInfoService`, `ScriptureReferenceService`) are all fixed,
trusted endpoints.

```php
/** @return array{bytes: string, filename: string, mime: string} */
public function fetch(string $url): array;   // throws ScoreLinkNotFetchable
```

Guards, in order, each with its own translated failure message:

1. `UrlWhitelistValidator::mayFetch()` on the entry URL. Reject otherwise.
2. `https` only, on every hop. Max 3 redirects, `referer => false`, no credentials.
3. **Every hop's host is resolved and rejected if any A/AAAA record falls in a private,
   loopback, link-local, CGNAT or reserved range.** Applied in Guzzle's `on_redirect`
   as well as before the first request — Drive redirects to `drive.usercontent.google.com`,
   so hops cannot be required to be whitelisted, and the IP check is what stands in for
   it. This narrows DNS rebinding rather than closing it; the host whitelist on the
   entry URL is what makes that proportionate, and it is worth writing down rather than
   implying the guard is airtight.
4. Stream the body, aborting past `config('scores.link.max_bytes')`. Never buffer an
   unbounded response.
5. The bytes must begin `%PDF` — the same test `PdfPageRasterizer::run()` already
   applies to its input. A Drive link to a file that is not shared returns an HTML
   sign-in page, and this is what catches it; the message says so in as many words,
   and offers the two fixes (share the link publicly, or upload the file).
6. Filename from `Content-Disposition`, falling back to the URL's basename, then to
   `linked-score.pdf`. Passed through the same character scrub
   `ScoreFileUploader::safeExtension()` uses.

PDF only in this slice. `.mscz` from a link is possible later — the guard is one more
magic-byte arm — but a PDF is what durkonyv.hu and a shared Drive folder actually hold.

### 1.4 `config/scores.php` (new)

```php
'link' => [
    'max_bytes' => env('SCORES_LINK_MAX_BYTES', 10 * 1024 * 1024),
    'timeout'   => env('SCORES_LINK_TIMEOUT', 30),
],
```

**Flagging an inconsistency rather than hiding it:** PHP's `upload_max_filesize` binds
uploads at 2 MB in production (`Dockerfile.prod:106`), so until
`score-upload-limits-implementation.md` lands, a link is a *wider* door into storage
than the upload form is. 10 MB is a deliberate, changeable default chosen to keep that
door narrow. This is the same `config/scores.php` that plan creates, so the two dovetail
rather than collide.

### 1.5 `ScoreFileRenderStatus::Fetching`

The row is created when the button is pressed, before any bytes exist, so that failure
has somewhere to live and the editor's existing machinery reports it for free:
`score-editor.blade.php:1239` already polls `wire:poll.2s` while `isRendering()`, and
already renders `render_error` verbatim.

- New case `Fetching = 'fetching'`, label *"Letöltés a hivatkozásról"*.
- `isFinal()` becomes `in_array($this, [Ready, Failed, Unsupported], true)` — `Fetching`
  is non-final, so polling continues.
- A `Fetching` row has no strips, so `stripList()` is `[]`, so `drawableFiles()` skips
  it and no booklet or public route can reach it. That falls out of existing code and
  needs no new check.

Migration: `score_files.checksum` becomes **nullable** (null = no bytes yet). It is a
plain index, not unique, so nothing else is affected. `size_bytes` defaults to 0.

### 1.6 `score_files.source_url`

```
+ source_url  text, nullable, cast 'encrypted'
```

Encrypted for the same reason `ScoreUrl::casts()` encrypts its `url`: it can be
somebody's private Drive link. Not indexed, and nothing needs it to be — dedup keys on
the checksum, which is the bytes.

It is the provenance record: shown on the file row in the editor (*"Hozva: durkonyv.hu
— 2026-09-08"*), carried forward by `ScoreDuplicator::duplicateFile()` beside `checksum`,
and included on a `ScoreRightsReport`, which is usually the fastest way to settle one.

This is the per-*file* sibling of the `scores.source` free-text column that
`score-source-import-design.md` proposes for typed content. The two do not overlap: that
one is a note a person writes about where a score's text came from; this one is written
by the machine and names the URL it fetched.

### 1.7 `ScoreFileUploader::storeBytes()` — reuse, not new code

`store()` and `replace()` already share their shape: read bytes, create a row, write the
source, dispatch the render. Extract that core rather than writing a parallel path:

```php
public function storeBytes(
    Score $score, string $bytes, string $originalName,
    ScoreFileRights $rights, ?string $label = null, ?string $sourceUrl = null,
): ScoreFile;
```

`store()` becomes `storeBytes($score, $this->read($upload), $upload->getClientOriginalName(), …)`.
The fetch path calls `storeBytes()` too — except that its row already exists, so it needs
the sibling that fills one in:

```php
public function completeFetch(ScoreFile $scoreFile, string $bytes, string $filename): void;
```

which writes `path`, `original_name`, `size_bytes`, `checksum`, flips `render_status` to
`Pending` and dispatches `RenderScoreFileJob`. Setting `checksum` on an existing row
fires `ScoreFile::booted()`'s `ScorePublicationWatcher` hook — correct, since bytes
genuinely appeared, and worth knowing so it is not a surprise in a test.

### 1.8 `app/Jobs/FetchLinkedScoreJob.php` (new)

**On the default queue, not `musescore`.** The `musescore` worker is a deliberately
hardened image (`docker-compose.prod.yml:144-167`: own queue, `user 33:33`,
`mem_limit: 1g`, `pids_limit: 256`, tmpfs `/tmp`) whose whole point is that it runs
untrusted engraving. Giving it a reason to make outbound HTTP requests would undo that.
The app image fetches; the render job then picks the file up exactly as it picks up an
upload.

```php
public int $timeout = 60;
public int $tries = 2;
// no onQueue() call — default queue, app image
```

`handle()`: resolve → fetch → `completeFetch()`. On `ScoreLinkNotFetchable`, set
`render_status = Failed` and `render_error` to the translated reason. `failed()` mirrors
`RenderScoreFileJob::failed()`.

### 1.9 The button, and where it lives

Everything happens inside `ScoreEditor`, which already owns the link list
(`score-editor.blade.php:1382-1430`, `ScoreEditor::scoreUrls()` at :1086) and already
owns the file list and the rights dialog.

- Each `ScoreUrl` labelled `sheet_music` whose host `mayFetch()` gets a **"Hozd ide"**
  button. Links that are not fetchable simply do not get one — no error, no explanation
  clutter.
- The music's own `MusicUrl` rows labelled `sheet_music` are listed in the same place as
  *offers* — *"Ehhez az énekhez van kottalink: durkonyv.hu"* — with the same button.
  This is what makes the durkonyv.hu corpus reachable without building a new page: the
  fetch always targets the score that is open, so there is no "which score does this
  attach to" question to answer. `NepenektarScrapeCommand` already wrote many of these
  rows, so the surface exists on day one.
- The button opens the **same rights dialog an upload opens** (`ScoreFileRights`), plus
  one sentence stating plainly what is about to happen: a copy of that file will be
  stored in your library, and you answer for it. Not a new consent mechanism — the
  existing one, in the one place a copy is being made.
- `fetchLink(int $urlId)` / `fetchMusicLink(int $musicUrlId)` create the `Fetching` row
  and dispatch. Guard: refuse if a non-final `ScoreFile` already exists on this score
  with the same `source_url`, so a double-click is one fetch.

New strings through `__()` with `lang/hu.json` entries, per the existing convention.

---

## Phase 2 — Do not render the same bytes twice

Small, independent of Phase 1, and worth landing on its own.

In `RenderScoreFileJob::handle()`, before `MuseScoreRenderer` is touched, look for a
donor:

```php
ScoreFile::query()
    ->where('checksum', $this->scoreFile->checksum)
    ->where('render_status', ScoreFileRenderStatus::Ready)
    ->where('id', '!=', $this->scoreFile->id)
    ->where('rendered_at', '>=', config('scores.render_epoch'))
    ->first();
```

If one exists, copy its artifacts and its render results instead of rendering — the copy
loop is the one `ScoreDuplicator::duplicateFile()` already uses, moving still-encrypted
bytes between directories, which `ScoreFileCipher`'s docblock explicitly guarantees is
safe (*"nothing binds a ciphertext to its path"*). Carried over: every file in the
donor's directory except `source.*`, plus `page_count`, `strips`, `has_thumbnail`,
`rendered_at`, `render_status`.

`config('scores.render_epoch')` is a date, not a column: bump it when the pipeline
changes (a MuseScore or poppler upgrade, a change to `STRIP_DPI` or the banding
heuristic) and older artifacts stop being donors without a migration.
`scores:cut-systems --all` remains the way to re-render the library behind it.

**Two invariants this must not violate, both worth a test:**

1. **A donor is never revealed.** Access is decided per `ScoreFile` row by
   `MusicPlanScoreListService::sourcesFor()` and `ScorePolicy`, never by path, and the
   donor query deliberately does **not** scope to the viewer — it does not need to,
   because identical sha256 means the recipient already holds those exact bytes. Nothing
   about the donor's owner, score or existence reaches the recipient.
2. **A takedown against one file cannot blank another.** Directories stay per-file in
   this phase, so `ScoreFileStorage::deleteAll()` is unchanged and this is automatic.

Storage is untouched — this saves the `musescore` worker, not the disk. The disk is
Phase 3.

---

## Phase 3 — Content-addressed artifacts (specified, deliberately not built here)

Not in this plan's build. Specified so the decisions are settled when it is picked up,
and so Phase 2's shape does not have to be undone.

- `score_blobs` keyed on the 64-char checksum, holding what is derived from bytes alone:
  `page_count`, `strips`, `has_thumbnail`, `render_status`, `render_error`, `rendered_at`,
  `stored_bytes`. Artifacts move to `score-blobs/{aa}/{sha256}/`. `ScoreFile` keeps
  everything that is *about the file rather than its bytes* — owner, label, rights,
  publication, supersession, `source_url` — and delegates its path accessors.
- **Deletion is refcounted**: the bytes go when the last `ScoreFile` naming that checksum
  goes. A rights report removes a `ScoreFile`; it removes the blob only if it was the
  last one.
- **Quota charges every referrer the full size.** The allowance in
  `score-upload-limits-implementation.md` is a policy limit on what you may put here,
  not a bill for bytes. Charging a share means the limit moves under people for reasons
  they cannot see, and it makes dedup gameable. Simple, and honest.
- The payoff that makes it worth doing: with a blob store, a `(normalised url → checksum)`
  note — which is *catalogue*, not content, by the import doc's own distinction — lets
  the second person to fetch a public durkonyv.hu link skip **both** the fetch and the
  render. That is the shape in which "many people, one public score" costs one copy,
  without the site ever having decided to hold it.

---

## What this plan deliberately does not do

- **No lazy or automatic fetching**, for the reason in the Context. This is the
  load-bearing constraint.
- **No system-owned copies.** Every fetched file belongs to a user and sits in their score.
- **No refresh or re-fetch on change.** `source_url` makes it easy later.
- **No arbitrary-URL fetching.** Entry URLs match an operator-managed whitelist row.
- **No booklet changes whatsoever.** If Phase 1 needs one, something has gone wrong.
- **No `.mscz`/MusicXML from links** in this slice. PDF only.

---

## Files touched

| File | Change |
|---|---|
| `app/Services/ScoreLinkFetcher.php` | new — guarded fetch |
| `app/Services/ScoreLinkResolver.php` | new — share URL → direct URL |
| `app/Jobs/FetchLinkedScoreJob.php` | new — default queue |
| `app/Exceptions/ScoreLinkNotFetchable.php` | new |
| `config/scores.php` | new — `link.max_bytes`, `link.timeout`, `render_epoch` |
| migrations ×4 | `whitelist_rules.allows_link/allows_fetch` (+ seed rows), `score_files.source_url`, `score_files.checksum` nullable |
| `app/Services/UrlWhitelistValidator.php` | `mayFetch()`; `validate()` requires `allows_link` |
| `app/Enums/ScoreFileRenderStatus.php` | `Fetching` case, `isFinal()` |
| `app/Models/ScoreFile.php` | `source_url` fillable + encrypted cast + docblock |
| `app/Services/ScoreFileUploader.php` | extract `storeBytes()`, add `completeFetch()` |
| `app/Services/ScoreDuplicator.php` | carry `source_url` |
| `app/Jobs/RenderScoreFileJob.php` | Phase 2 donor reuse |
| `app/Livewire/Pages/ScoreEditor.php` | `fetchLink()`, `fetchMusicLink()`, music-link offers |
| `resources/views/livewire/pages/score-editor.blade.php` | button + offers + provenance line |
| `resources/views/pages/admin/url-whitelist.blade.php` | two checkboxes in the rule form |
| `lang/hu.json` | new strings |

Stale prose to fix in the same pass: `ScoreFileUploader`'s class docblock (an upload is
no longer the only way bytes arrive) and `RenderScoreFileJob`'s (a render may now be a
copy).

---

## Verification

Pest, following `tests/Feature/ScoreFileUploadTest.php`'s style and its `fakeRenderer()`
helper. `Http::fake()` throughout — no test reaches the network.

**`tests/Unit/UrlWhitelistValidatorTest.php`** (extend)
1. `mayFetch()` is false for a rule with `allows_fetch = false`, true with it set.
2. `validate()` is false for a fetch-only rule — a Drive rule does not widen `MusicUrl`.

**`tests/Unit/ScoreLinkResolverTest.php`** (new)
3. A Drive `/file/d/{ID}/view` becomes `uc?export=download&id={ID}`; Dropbox `dl=0`
   becomes `dl=1`; an unknown host is unchanged.

**`tests/Feature/ScoreLinkFetchTest.php`** (new)
4. A whitelisted host returning `%PDF` bytes lands a `ScoreFile` with the right checksum,
   `source_url`, and a queued `RenderScoreFileJob`.
5. A non-whitelisted host is refused before any request is made (`Http::assertNothingSent()`).
6. An HTML body (the Drive sign-in page) fails with the *"share the link publicly"*
   message and leaves `render_status = Failed`.
7. A body past `link.max_bytes` is aborted and fails.
8. A redirect to `127.0.0.1` / `10.0.0.1` is refused.
9. A `Fetching` row is invisible to booklets: `MusicPlanScoreListService::sourcesFor()`
   returns nothing for it, and `booklets.score-page` 404s.
10. Pressing the button twice while a fetch is in flight dispatches one job.

**`tests/Feature/ScoreFileRenderReuseTest.php`** (new)
11. A second file with an identical checksum copies the donor's artifacts, ends `Ready`
    with the same `strips` and `page_count`, and the renderer mock is **never invoked**.
12. A donor whose `rendered_at` predates `render_epoch` is not used; the renderer runs.
13. Deleting the donor leaves the recipient's artifacts intact and still servable.
14. A donor owned by another user is used, and nothing about that user is exposed on the
    recipient's row.

**Must stay green** — these pin the behaviour being touched:
`ScoreFileUploadTest`, `ScoreUrlTest`, `ScoreLinksOnlyTest`, `BookletFileScoreTest`,
`BookletExportTest`, `BookletScoreSourcesTest`, `MusicUrl` whitelist coverage.

```
php artisan test --compact --filter=ScoreLink
php artisan test --compact --filter=ScoreFile
php artisan test --compact --filter=Booklet
php artisan test --compact --filter=Whitelist
vendor/bin/pint --dirty --format agent
```

Then the full suite, since `ScoreEditor`, `ScoreFileUploader` and `RenderScoreFileJob`
are reached by booklet, versioning and publication tests too.

**Manually in dev**, which is where the point of the feature is actually visible:
add a durkonyv.hu link to a links-only score, press the button, watch the row go
*Letöltés → Rendering → Ready* under the existing 2s poll, then drop it into a booklet
and export the PDF. Then upload the identical file to a second score under a second
user and confirm from the log that the renderer never ran.

## Deployment

Every migration is additive or widening (`checksum` not-null → nullable), so they are
safe ahead of the code. `allows_link` backfills to true, so `MusicUrl` validation is
unchanged on day one, and no host is fetchable until a rule says so — the feature is
dark until an operator turns a host on. Phase 2 is inert until `scores.render_epoch` is
set; leaving it unset (or in the future) disables donor reuse entirely, which is also
the rollback.

## Follow-ups this creates

Not optional extras — these are what the posture costs, in the same spirit as the import
doc's own steps 7 and 8.

1. **`terms.md` §7** forbids *"a szolgáltatás általános fájltárként, felhőmeghajtóként
   való használata"* while this feature moves files in from Drive. Same clause the import
   doc already flagged; it wants narrowing to content unrelated to church music service.
2. **`kotta-jogok.md`** should say that a fetched file is treated exactly as an upload —
   same declaration, same review, same report route — so the reporting page describes
   the software that will exist.
3. **The upload-limits plan matters more after this**, because a link is a wider door
   into storage than the 2 MB upload form. `config/scores.php` exists after this plan,
   which removes that plan's first step.
