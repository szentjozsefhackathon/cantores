# Diatár `.dia` Export Implementation Plan

## Context and settled decisions

Cantores users should be able to export a finalized Music Plan to Diatár by pressing **Export as .dia**, reviewing a suggested ordered list of Diatár slides, and confirming the download.

The design keeps Music Plans independent of music collections:

- Do not add collection or Diatár fields to plan slots or assignments.
- A Music can belong to several Cantores collections.
- A Cantores collection can be associated with several DTX books, and a DTX book can cover several collections.
- Editors choose at most one default DTX for each Cantores collection. Additional associations remain available as alternatives.
- Different Music records, including related variants, resolve independently.
- Explicit Music-to-Diatár bindings are exceptional overrides, not required data for every Music.
- Export-time changes affect only the current download. They do not rewrite the plan, collection defaults, or Music metadata.

The indexed catalogue is metadata only. Persist DTX/book identity, song title/reference/order, verse name/order, slide ID, source revision, checksums, availability, and diagnostics. Do not persist lyrics, notation, images, audio, DTX formatting, or source files.

The public source is [diatar-eu/diatar-dtxs](https://github.com/diatar-eu/diatar-dtxs). A scheduled job refreshes the catalogue monthly. Malformed source content must reduce what can be offered for export, but must not fail the complete import or invalidate the last usable catalogue.

## Target user flow

1. The owner opens a finalized Music Plan and selects **Export as .dia**.
2. Cantores resolves suggestions in Music Plan order without changing the plan.
3. A dialog lists every assigned Music and shows:
   - the Cantores Music;
   - the selected Diatár source, such as `SZVU 230 — szvu.dtx`;
   - the selected slides/verses in export order;
   - a clear unresolved or ambiguous state where no safe suggestion exists.
4. Normally the user only reviews and confirms.
5. For the current export, the user may:
   - switch to another matching DTX representation;
   - select or deselect slides;
   - reorder slides within the Music;
   - repeat a slide;
   - explicitly omit an unresolved Music.
6. The download action revalidates the complete selection against the current catalogue.
7. Cantores downloads a UTF-8 `.dia` file. No plan or mapping data is mutated.

Every plan assignment must either contribute at least one valid slide or be explicitly omitted. Missing or ambiguous matches are never silently dropped.

## Diatár output contract

Generate the format used by the local Diatár implementation:

```ini
[main]
diaszam=2
utf8=1

[1]
id=ABC12345
kotet=Exact imported book title
enek=Exact imported song title
versszak=Exact imported verse name

[2]
id=DEF67890
kotet=Exact imported book title
enek=Exact imported song title
versszak=Exact imported verse name
```

Requirements:

- Treat the eight-character hexadecimal slide ID as authoritative and normalize it to uppercase.
- Preserve exact indexed book, song, and verse names as Diatár's fallback identifiers.
- Number output sections sequentially in the confirmed order.
- Preserve repeated slides as separate output sections.
- Emit UTF-8 with `utf8=1` and a stable newline convention.
- Use a sanitized plan title for the attachment filename.

The implementation should be checked manually against the local Diatár source at `~/prog/szjh/diatar-flutter` and by importing a generated file into Diatár before release.

## Data model

Add additive migrations, Eloquent models, relationships, and factories for the following structures. Final column names should follow neighboring project conventions.

### `diatar_sync_runs`

Record each attempted catalogue refresh:

- source commit/revision;
- status: `running`, `completed`, `completed_with_warnings`, or `failed`;
- fetched, indexed, skipped, unavailable, and warning counts;
- start and completion timestamps;
- a bounded error summary for operational failures.

### `diatar_books`

Store one row per source DTX:

- stable source path/filename, unique within the configured repository;
- exact book title and optional short name/group metadata;
- source commit and checksum;
- `available` state and last-seen sync run;
- source order where useful for deterministic presentation.

### `diatar_songs`

Store exportable song headings, not song content:

- parent book;
- exact title;
- parsed collection reference/order number when interpretable;
- source ordinal;
- availability and diagnostic state;
- last-seen sync run.

Index the book/reference and book/source-order lookup paths.

### `diatar_slides`

Store slide identity only:

- parent song;
- source ordinal;
- normalized external slide ID, nullable when malformed;
- exact verse name;
- `is_exportable` and a bounded diagnostic reason;
- last-seen sync run.

Index external IDs, but do not make them blindly unique: duplicate IDs in source data must be recordable and quarantined instead of aborting a sync. Mark every side of an ambiguous duplicate non-exportable until a later successful sync resolves it.

### `collection_diatar_book`

Represent the many-to-many association:

- Cantores collection;
- Diatár book;
- `is_default`;
- optional editor/audit timestamps if consistent with existing editor metadata.

Enforce a unique collection/book pair. The write service must transactionally demote the previous default before promoting another, preserving the invariant of at most one default per collection. The same Diatár book may be the default for several collections.

### Exceptional Music bindings

Add `diatar_music_bindings` for editor-curated exceptional matches:

- Music;
- Diatár song;
- preferred/active state and optional short editor note.

Add ordered `diatar_music_binding_slides` only if an exception needs a curated subset, combination, or non-source ordering. Use a sequence key rather than a unique binding/slide pair so intentional repeats remain possible.

These bindings override normal inference but do not introduce collection awareness into plans.

## Monthly catalogue publication pipeline

Implement the network, parsing, and persistence boundaries separately:

- `DiatarRepositoryClient` obtains the authoritative repository revision/tree and fetches DTX contents through Laravel's HTTP client with configured timeouts and retries.
- `DiatarDtxMetadataParser` is a pure, lenient parser that returns recognized metadata plus warnings. It never stores or returns lyrics beyond the transient text needed to locate structural boundaries.
- `DiatarCatalogSynchronizer` coordinates a snapshot and records its run.
- A per-book publisher writes one book in a short database transaction so a failure cannot roll back successfully processed books.
- `SyncDiatarCatalogJob` runs the work on the queue.
- `cantores:sync-diatar-catalog` provides a deterministic manual/operational entry point using the same service.

Configure repository endpoints, timeouts, and scheduling inputs in `config/diatar.php`; do not add a dependency for GitHub access.

Schedule the queued job monthly in `routes/console.php`, outside peak hours, with overlap protection. Use `onOneServer()` where the deployed cache driver provides distributed locks.

### Publication and failure rules

The source parser should require only enough structure to produce a safe export entry:

- A recognizable book/song/verse with a valid eight-character hexadecimal slide ID is eligible.
- A malformed slide is skipped or marked non-exportable; later slides continue.
- A malformed song is skipped or marked unavailable; later songs continue.
- A wholly uninterpretable DTX is unavailable for suggestions; other books continue.
- Duplicate or ambiguous slide IDs are retained for diagnostics but excluded from export.
- A per-book persistence failure rolls back only that book and adds a run warning.
- Source oddities that do not affect safe identification remain warnings, not validation failures.

Publication must distinguish transport failure from bad source content:

- If the repository revision/tree cannot be fetched, fail and retry the run while leaving the currently published catalogue unchanged.
- Only a successfully fetched authoritative tree may establish that a previously indexed file disappeared. Then mark that book unavailable rather than deleting it.
- If an existing file becomes malformed, keep its previous metadata for traceability but mark the affected book/entries unavailable for new suggestions.
- A later successful sync restores repaired entries automatically.
- Complete usable entries from a warning-bearing run are published. Mark the run `completed_with_warnings` rather than failing it.

Do not hold a transaction open during network requests or parse the complete repository inside one transaction.

## Candidate resolution

Create a query/service boundary such as `DiatarPlanSuggestionService` backed by a `DiatarCandidateResolver`. Resolve each assignment through its Music record:

```text
Music Plan assignment
  -> Music
    -> collection memberships and their order_number
      -> associated Diatár books
        -> matching indexed songs
          -> exportable slides
```

Rank candidates deterministically:

1. active explicit Music binding;
2. exact reference match in each collection's default DTX;
3. exact reference match in additional DTXs for that collection;
4. matches contributed by the Music's other collection memberships, using existing collection priority/verification conventions as tie-breakers.

A default DTX may cover only a subset of a collection. A missing song there is not an error: continue to associated alternatives and other collection memberships.

Do not make a confident automatic selection when the reference is absent, produces several indistinguishable songs in one book, or contains only non-exportable slides. Return an unresolved/ambiguous result and all safe alternatives for review.

Load candidates for the complete plan in bounded queries. Avoid a per-assignment query cascade and add a query-count regression test for a representative larger plan.

## Editor configuration

Extend the existing Collection editor rather than creating a parallel collection-management workflow:

- Add a searchable Diatár source selector to `CollectionEditModal` for the default DTX.
- Allow additional associated DTX books to be selected.
- Show unavailable books and the reason, but prevent choosing an unavailable book as a new default.
- Save associations and default promotion transactionally.
- Authorize with the existing collection update policy so editors and administrators can configure defaults while ordinary users cannot.

Add a small administrator-facing catalogue status view only for operations: last successful run, latest warnings, source revision, stale/unavailable counts, and a guarded manual sync action. Do not require editors to use this page for ordinary collection setup.

## Export dialog and endpoint

Add the export action to the plan editor and the authenticated read-only plan view. For the first release, authorize exporting using the existing owner/update rule; viewers of shared plans can copy the plan before export.

The Livewire dialog should:

- build suggestions when opened and keep edits in component state only;
- key rows by assignment occurrence, not by Music or slide ID, so repeats are independent;
- display selected source prominently and raw IDs as secondary details;
- support source switching, slide toggling, reordering, and repetition;
- require a deliberate omit acknowledgement for unresolved rows;
- display actionable validation beside the affected Music;
- rebuild from persisted plan/catalogue state when reopened.

Submit the confirmed selection to a POST export endpoint with CSRF protection. Use a Form Request for payload shape and an export controller/service for authorization and generation. The payload should include the catalogue sync/revision observed by the dialog and stable selected slide identifiers.

Before writing bytes, the server must:

- reauthorize access to the plan;
- confirm assignments still belong to the plan and preserve plan order;
- reload every selected book/song/slide from the published catalogue;
- reject unavailable, non-exportable, ambiguous, or stale selections;
- confirm each assignment has slides or an explicit omission;
- return field-level errors that cause the dialog to refresh instead of producing a partial file.

Keep the `.dia` writer independent of Livewire and HTTP so exact output can be unit tested.

## Implementation phases

### Phase 1: Catalogue schema and domain model

1. Create migrations, models, factories, relationships, casts, and status enums.
2. Add repository configuration with safe defaults and environment overrides.
3. Add model tests for relationships, availability scopes, and default-source invariants.

Exit condition: the metadata catalogue and many-to-many collection association can be represented without touching Music Plans.

### Phase 2: Lenient parser and monthly synchronizer

1. Add small synthetic DTX fixtures covering ordinary, Unicode, CRLF/LF, lowercase IDs, malformed records, duplicates, subset books, and wholly malformed files. Fixtures must contain invented text rather than copyrighted lyrics.
2. Implement and unit-test metadata parsing.
3. Implement the repository client with all network calls fakeable.
4. Implement run bookkeeping, per-book publication, command, queued job, and schedule.
5. Add an operations summary suitable for logs and the future status view.

Exit condition: a manual or scheduled sync publishes every safely interpretable entry, reports warnings, and preserves the previous catalogue on source transport failure.

### Phase 3: Collection source configuration

1. Add collection/DTX association services and authorization.
2. Extend the Collection editor with default and alternative source controls.
3. Add the minimal administrator catalogue health/status view and guarded manual trigger.

Exit condition: an editor can choose one default and several alternative DTXs for a collection, and one DTX can serve multiple collections.

### Phase 4: Suggestions and exceptional bindings

1. Implement batched resolution and deterministic ranking.
2. Add optional explicit Music binding management for cases inference cannot represent.
3. Cover cross-collection variants, subset DTXs, ambiguous records, and availability changes.

Exit condition: a complete plan can be converted into an ordered suggestion result with explicit resolved, ambiguous, and unresolved states.

### Phase 5: Review dialog and `.dia` download

1. Implement the independent `.dia` writer.
2. Add the plan export dialog and temporary review controls.
3. Add the authorized POST download endpoint with stale-catalogue revalidation.
4. Add exact-format and user-flow tests.

Exit condition: a normal plan needs only confirmation, while every exceptional or missing entry is visibly resolved or deliberately omitted before download.

### Phase 6: Compatibility and rollout

1. Run the initial catalogue sync in the target environment.
2. Configure defaults for the high-use Cantores collections, including SZVU where applicable.
3. Test representative finalized plans with ordinary songs, alternatives, related Music variants, repeats, and omissions.
4. Import generated files into the local/current Diatár application.
5. Enable the export action after catalogue health and common defaults are verified.

## Test plan

Use Pest and the existing test conventions. Run the narrowest affected test file after each change, then the complete suite before release.

### Parser unit tests

- extracts book, song, verse, ordinal, reference, and slide ID metadata;
- supports Unicode and both common newline styles;
- normalizes lowercase hexadecimal IDs;
- ignores lyrics and does not expose them in parsed results;
- continues after malformed slides and songs;
- returns warnings for uninterpretable records without throwing for the complete file;
- identifies duplicate/ambiguous IDs as non-exportable.

### Synchronization feature tests

- creates a catalogue from a successful repository snapshot;
- is idempotent for an unchanged revision;
- updates changed books and restores repaired entries;
- publishes valid books when another file is malformed;
- rolls back only the affected book on a per-book failure;
- records `completed_with_warnings` and accurate counters;
- keeps the published catalogue unchanged when revision/tree fetching fails;
- marks missing books unavailable only after a successful authoritative listing;
- never writes source lyrics or DTX bodies to the database;
- prevents stray HTTP requests in tests and fakes every expected endpoint.

### Collection configuration tests

- a collection can have several associated DTXs and only one default;
- changing a default demotes the previous one atomically;
- one DTX can be default for several collections;
- unavailable sources cannot become a new default;
- editors/administrators may update mappings and ordinary users may not.

### Resolution tests

- selects an exact match from the default DTX;
- falls back from a subset default to an alternative associated DTX;
- considers all of a Music's collection memberships;
- exposes and deterministically ranks several representations;
- treats related Music records independently;
- prefers an explicit exceptional binding;
- returns unresolved for missing references and ambiguous/non-exportable entries;
- excludes unavailable books and slides;
- resolves a larger plan within a fixed query bound.

### Dialog and endpoint tests

- presents rows in plan order and shows the selected source;
- permits source switching, slide toggling, reordering, and repetition;
- keeps repeated plan occurrences independent;
- blocks download until unresolved rows are fixed or explicitly omitted;
- does not persist temporary review changes;
- rejects unauthorized export attempts;
- rejects stale, unavailable, or tampered slide selections;
- sanitizes the download filename;
- produces byte-for-byte expected `.dia` output, including repeated slides and Unicode metadata.

### Manual acceptance checks

- A typical configured Music Plan opens with all suggestions selected and downloads after one confirmation.
- `SZVU 230 — szvu.dtx`-style provenance is understandable without opening technical details.
- A Music in several collections lets the user choose among safe representations.
- One malformed upstream DTX does not remove suggestions from other books.
- A repository outage does not empty or corrupt the current catalogue.
- A generated file imports successfully in Diatár and opens the intended slides.

## Expected file areas

The exact file list should follow existing neighboring conventions, but implementation is expected to touch:

- `config/diatar.php`
- `database/migrations/*diatar*`
- `database/factories/*Diatar*Factory.php`
- `app/Models/Diatar*`
- `app/Enums/Diatar*`
- `app/Services/Diatar/*`
- `app/Jobs/SyncDiatarCatalogJob.php`
- `app/Console/Commands/SyncDiatarCatalogCommand.php`
- `routes/console.php`
- the existing Collection editor Livewire component and view
- a Music Plan Diatár export Livewire component and view
- an export Form Request, controller, policy checks, and route
- focused Pest tests and synthetic fixtures under `tests/Fixtures/Diatar`

Do not change plan-assignment schema, copy DTX contents into application storage, or add a new package without a separately justified need.

## Deployment and operations

1. Deploy the additive schema and synchronizer with the export action disabled or hidden.
2. Run the initial sync manually and inspect the summary; warnings are expected to be actionable but not release-blocking unless common sources are unavailable.
3. Configure default DTXs for commonly used collections.
4. Verify representative exports and Diatár import compatibility.
5. Enable the export action.
6. Confirm the scheduler and queue worker execute the monthly job.
7. Monitor last successful sync age, failed transport runs, warning trends, and unavailable-source counts.

The feature is complete when routine exports need only confirmation, malformed source data is safely excluded without collapsing the catalogue, and the generated `.dia` files reliably open the intended slides in Diatár.
