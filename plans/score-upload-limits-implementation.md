# Score File Upload Limits Implementation Plan

## Context

Uploaded score files are stored as an encrypted source plus everything the renderer derives
from it: the PDF, one 150 dpi page PNG per page, an incipit crop, and one 300 dpi strip per
musical system. A 10-page file therefore lands as roughly 70 artifacts, and nothing in the
application bounds how many files one person may put there. The volume can fill silently.

Two things are wrong today:

1. **No quota of any kind.** There is no per-user limit, no global limit, and nothing
   anywhere aggregates stored bytes. Confirmed: no `withSum`, no `sum('size_bytes')`, no
   admin report.

2. **The stated size cap is a fiction.** Three caps sit in front of an upload and the app
   only knows about the loosest one:

   | Where | Value | Effect |
   |---|---|---|
   | PHP `upload_max_filesize` / `post_max_size` (stock `php.ini-production`, `Dockerfile.prod:106`) | **2 MB** / 8 MB | binds first, silently |
   | Livewire `temporary_file_upload.rules` (`config/livewire.php:133` is `null` → framework default) | 12 MB | second |
   | `ScoreEditor::UPLOAD_RULES` (`app/Livewire/Pages/ScoreEditor.php:52`) `max:25600` | 25 MB | never reached |
   | UI copy (`score-editor.blade.php:1275`), promising "Max 25 MB" | 25 MB | a lie |

   A file over 2 MB is discarded by PHP before Laravel sees it, so the user gets
   "The pendingFile field is required" rather than a size message.

**Decisions taken** (from the questions asked while planning):

- The per-user limit counts **stored bytes** — source plus every derived artifact.
- Allowance comes from **role tiers**, overridable **per user**, with a **global library
  ceiling** as a backstop.
- **No page-count limit.** Size is the only thing being capped.
- **Do not touch php.ini or Livewire's plumbing.** Instead make the app state the truth:
  the effective cap becomes `min(app cap, PHP's own ini values)` computed at runtime, so
  the promise can never drift from what the server actually accepts again.

> One factual consequence worth stating plainly: at 2 MB, most scanned PDFs will be
> refused. `.mscz`, MusicXML and MIDI sources are far smaller and are unaffected. Raising
> it later is one env var plus the two ini lines — the code will follow automatically
> because the cap is computed, not hard-coded.
>
> A second: with no page-count check, one 2 MB, 200-page `.mscz` can still expand to
> hundreds of megabytes of strips in a single render. The per-user quota absorbs this
> (that user is then out of allowance and cannot upload again), so the overshoot is
> bounded by one file. Step 6 adds an optional per-file ceiling if you want it tighter;
> it is size-based, not page-based, and can be dropped without affecting anything else.

---

## 1. `app/Support/ScoreUploadLimits.php` (new)

A container-free helper, alongside `ScorePublicationRules` and `CacheKey`. Container-free
matters: `config/livewire.php` has to call it, and config is loaded before the container.

```php
/** The largest upload the application itself is willing to take, in kilobytes. */
public const MAX_KILOBYTES = 2048;

/** The cap that actually applies: ours, or PHP's, whichever is lower. */
public static function effectiveKilobytes(): int;   // min(MAX_KILOBYTES, ini-derived)

/** The validation rules every score-file upload passes. */
public static function rules(): array;              // ['file', 'extensions:...', 'max:N']

/** The cap as the description text states it, e.g. "2 MB". */
public static function humanCap(): string;
```

`effectiveKilobytes()` parses `ini_get('upload_max_filesize')` and `ini_get('post_max_size')`
(shorthand `2M`/`8M` notation) and takes the minimum of the three. The extension list moves
here from `ScoreEditor::UPLOAD_RULES`, so the rule has exactly one home.

## 2. `config/scores.php` (new)

The first score-domain config file. Justified because these numbers are read from three
places that cannot share a class constant (`config/livewire.php`, the component, the Blade
copy) and because operators need to tune them per deployment without a code change.

```php
'storage' => [
    'allowance' => [
        'contributor' => env('SCORES_ALLOWANCE_CONTRIBUTOR', 512 * 1024 * 1024),
        'editor'      => env('SCORES_ALLOWANCE_EDITOR', 5 * 1024 * 1024 * 1024),
        'admin'       => 0,   // 0 means unlimited
    ],
    'library_ceiling' => env('SCORES_LIBRARY_CEILING', 50 * 1024 * 1024 * 1024),
],
```

Tune `SCORES_LIBRARY_CEILING` to sit comfortably under the real volume size.

## 3. `config/livewire.php`

Replace `'rules' => null` (line 133) with the computed rule, so the temporary-upload
endpoint rejects at the same number the app states rather than at 12 MB:

```php
'rules' => ['required', 'file', 'max:'.\App\Support\ScoreUploadLimits::effectiveKilobytes()],
```

Note for whoever runs `config:cache`: this bakes in the value at cache time. That is
correct behaviour — the ini values do not change without a deploy, which clears the cache.

## 4. `score_files.stored_bytes` (migration + model)

`unsignedBigInteger('stored_bytes')->nullable()`, added to `$fillable` and to the model
docblock. Null means "never measured" — the backfill in step 8 fills existing rows.

Written in three places:

- `ScoreFileUploader::store()` / `replace()` — the source's ciphertext size
  (`strlen($bytes) + ScoreFileCipher::OVERHEAD_BYTES`).
- `RenderScoreFileJob` — recomputed in the final `update()` alongside `page_count`, by
  summing `$storage->disk()->size($path)` over
  `$storage->disk()->allFiles($scoreFile->directory())`. One directory listing, after
  every artifact is written, so it is exact and includes the envelope.
- `ScoreDuplicator` — copies the source value, as it already does for `size_bytes`
  (`ScoreDuplicator.php:93`).

## 5. `app/Services/ScoreStorageQuota.php` (new)

```php
public function usedBy(User $user): int;        // SUM(stored_bytes) via scores.user_id
public function allowanceFor(User $user): int;  // override ?? role tier; 0 = unlimited
public function remainingFor(User $user): int;
public function hasRoom(User $user): bool;      // unlimited, or used < allowance
public function libraryUsed(): int;             // SUM over everything, cached 60s
public function libraryHasRoom(User $user): bool; // admins are never stopped by it
```

- `usedBy()` counts **superseded files too** — they still occupy the volume until the last
  publication referring to them is gone.
- `allowanceFor()` reads a new nullable `users.storage_allowance_bytes` first, then falls
  back to the config tier for the user's highest role, using the existing `isAdmin()` /
  `isEditor()` helpers (`User.php:313,329`); everyone else is `contributor`.
- `libraryUsed()` is cached with `Cache::remember` keyed via
  `CacheKey::forModel('score_file', 'library_bytes')`, so it is not a `SUM` per keystroke.

The rule is **block when already at or over the allowance**, not "block if this file would
exceed it". The artifact size is not knowable before the render, and this way a user
overshoots by at most one file.

## 6. Enforcement

**`ScoreFileUploader::store()` and `replace()`** — throw a new
`App\Exceptions\ScoreStorageQuotaExceeded` when there is no room. This is the backstop, in
the same spirit as the existing `safeExtension()` "belt to those braces" comment.

**`ScoreEditor`** — the friendly path, so the message lands in the existing
`<flux:error name="pendingFile" />` slot rather than as a 500:

- Delete the `#[Validate(self::UPLOAD_RULES)]` attribute (line 85) and the
  `UPLOAD_RULES` constant (line 52). An attribute needs a compile-time constant and the
  cap is now computed. All four call sites already validate explicitly, so this is a
  substitution, not a loss:
  - `updatedPendingFile()` (line 841) → `$this->validateOnly('pendingFile', ['pendingFile' => ['nullable', ...ScoreUploadLimits::rules()]])`
  - `save()` (line 273), `addFile()` (line 877), `updateFile()` (line 926) → same list
- After validation and before calling the uploader, check `hasRoom()` and
  `libraryHasRoom()` and `addError('pendingFile', ...)` / `addError('replacementFile', ...)`
  with a translated message naming what is left.

**Blade** (`score-editor.blade.php:1268-1278` and `:1342-1350`) — replace the hard-coded
"Max 25 MB" copy with the computed cap, and add the remaining allowance underneath. Add a
small Alpine `x-on:change` guard on both file inputs that compares
`$event.target.files[0].size` against the cap and reports it before the POST is attempted,
so an oversized file never becomes a confusing server-side "required" error.

*(Optional, and separable — drop it if you'd rather not.)* A per-file stored-bytes ceiling
in `RenderScoreFileJob`: while writing page and strip artifacts, keep a running total, and
if it passes `config('scores.storage.max_file_bytes')` abort to `Failed` with a translated
message and `deleteAll()` the partial artifacts. This is what bounds a single pathological
upload; without it the per-user quota still catches it, one file late.

## 7. `users.storage_allowance_bytes`

`unsignedBigInteger(...)->nullable()` migration, added to the `User` docblock and
`$fillable`. Null means "use the role tier". No UI — it is set by an admin through tinker
or a seeder, which matches how `blocked` is handled today.

## 8. `app/Console/Commands/ScoreStorageCommand.php` (new) — `scores:storage`

Modelled on `ReencryptScoreFiles`, which already walks the disk this way.

- No options: report the library total, the top users by usage with their allowance and
  percentage, and a count of rows whose `stored_bytes` is still null.
- `--measure`: recompute `stored_bytes` for every file from `allFiles()` + `size()` first.
  This is the backfill for existing rows, and repairs drift.
- `--limit=N` to bound a measuring run, matching the other two score commands.

## 9. Translations

New English source strings through `__()`, with entries added to `lang/hu.json`, following
the existing convention:

- the file-field description carrying the computed cap
- "You have N left of your M allowance."
- the over-quota upload error
- the library-full error
- the render-time per-file ceiling message (if step 6's optional part is kept) — note this
  one is stored into `render_error`, which is currently shown verbatim and untranslated in
  the editor (`score-editor.blade.php:1188-1190`); using `__()` in the job resolves it to
  Hungarian at store time, which is consistent with the hard-coded Hungarian fallback
  already in `RenderScoreFileJob::failed()`.

Also add the missing `extensions` key to `lang/hu/validation.php` — it exists in the
English file (`lang/en/validation.php:59`) but not the Hungarian one, so a wrong file type
currently produces an English message. And add a `pendingFile` / `replacementFile` entry to
the `attributes` block (`lang/hu/validation.php:110-118`) so the size and required messages
read naturally.

---

## Files touched

| File | Change |
|---|---|
| `app/Support/ScoreUploadLimits.php` | new |
| `app/Services/ScoreStorageQuota.php` | new |
| `app/Exceptions/ScoreStorageQuotaExceeded.php` | new |
| `app/Console/Commands/ScoreStorageCommand.php` | new |
| `config/scores.php` | new |
| `config/livewire.php` | computed temp-upload rule |
| migrations ×2 | `score_files.stored_bytes`, `users.storage_allowance_bytes` |
| `app/Models/ScoreFile.php`, `app/Models/User.php` | fillable + docblocks |
| `app/Services/ScoreFileUploader.php` | quota check, write `stored_bytes` |
| `app/Jobs/RenderScoreFileJob.php` | recompute `stored_bytes`; optional per-file ceiling |
| `app/Services/ScoreDuplicator.php` | carry `stored_bytes` |
| `app/Livewire/Pages/ScoreEditor.php` | drop `UPLOAD_RULES`/attribute, quota errors |
| `resources/views/livewire/pages/score-editor.blade.php` | computed copy, remaining, Alpine guard |
| `lang/hu.json`, `lang/hu/validation.php` | new strings |

Stale comments naming the 25 MB cap as an invariant must be corrected in the same pass:
`RenderScoreFileJob.php:54-56`, `ScoreFileCipher.php:17`, `ScoreFileResponder.php:17`,
`ReencryptScoreFiles.php:110`.

---

## Verification

New tests in `tests/Feature/ScoreFileUploadTest.php` (or a sibling
`ScoreStorageQuotaTest.php`, following that file's Pest style and its `fakeRenderer()`
helper):

1. The effective cap is `min(app, ini)`, and the rule and the description text both
   report the same number.
2. An upload above the cap is rejected with a size error, not a "required" error.
3. `stored_bytes` is written on upload and recomputed after a render, and equals the sum of
   the file's directory on the fake disk.
4. A user at their allowance gets `assertHasErrors('pendingFile')` and no `ScoreFile` row
   is created; a user under it succeeds.
5. `allowanceFor()` honours the role tier, and the per-user override beats it.
6. Superseded files still count against usage.
7. The library ceiling blocks a contributor and does not block an admin.
8. `scores:storage --measure` fills null `stored_bytes` and the plain run reports totals.

Existing coverage that must stay green — these pin behaviour the change touches:
`it('rejects a file over the size cap')` (line 114, its `25601` becomes the new number),
`it('rejects a file type the renderer cannot read')` (line 103),
`it('keeps the add-file dialog open when the upload is rejected')` (line 873).

Run:

```
php artisan test --compact --filter=ScoreFile
php artisan test --compact --filter=Score
vendor/bin/pint --dirty --format agent
```

Then the full suite before committing, since `ScoreEditor` validation and
`RenderScoreFileJob` are touched by booklet and versioning tests too.

Manual check in dev: `php artisan scores:storage --measure` on the three existing files,
then `php artisan scores:storage` to see the report; set
`SCORES_ALLOWANCE_CONTRIBUTOR=1` in `.env` and confirm the editor refuses an upload with
the Hungarian message in the field's error slot.

## Deployment

Both migrations are additive and nullable, so they are safe to run ahead of the code. After
deploying, run `php artisan scores:storage --measure` once to backfill `stored_bytes`;
until it has run, usage reads low and nobody is wrongly blocked.
