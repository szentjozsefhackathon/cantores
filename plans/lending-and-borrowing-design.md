# Lending and Borrowing Design

## Overview

Scores reach people three ways today — ownership, secret link, and publication — and the middle one leaves no trace a recipient can return to. This document designs a lending centre over that middle axis, the service list it feeds, the publication versioning the third axis needs, and the vocabulary all three are described in.

Two things are missing today and everything here follows from them:

1. **Nothing is recorded per recipient.** `ScoreView::mount()` resolves the grant, calls `touchLastViewed()` and renders. No row says that *this person* met *this share*. A link from March lives in an inbox, not on the site.
2. **A plan cannot reach anyone else's score.** `MusicPlan::reachableScores()` is `Score::where('user_id', $this->user_id)` filtered to the `music_id`s its assignments name, and `music_plan_slot_assignments` has no `score_id`. A plan points at *songs*; every viewer gets the plan owner's own settings of them.

The invariant to protect throughout: **derive, never mint.** A folder or plan grant is resolved on every request rather than written onto the scores underneath, so one revoke closes a whole subtree and nothing is left to garbage-collect.

## Vocabulary

"Megosztás" carries no obligation — it suggests a wide audience and a thing given away. What happens on the private axis is a loan: the score stays its owner's, access is temporary, and the reader is using someone else's work. The two axes split along a line Hungarian already draws:

- **kölcsönzés** — the private link
- **közzététel** — the public library

This is a norm, not a control. Nothing stops a borrower downloading a score and re-uploading it; the design assumes they can and works by making the honest path better, not by closing the dishonest one. The words set an expectation where a rule cannot reach.

| Where | Now | Proposed |
|-------|-----|----------|
| Menu item | Titkos linkjeim | Kölcsönzések |
| Received tab | Velem megosztva | Kölcsönkapott kották |
| Given tab | Megosztottam | Kölcsönadott kották |
| Publication tab | Közzétett kottáim | *unchanged* |
| Action on a score | Megosztás | Kölcsönadás |
| The link | titkos link | kölcsönlink |
| Save action | Mentés a megosztásaim közé | Mentés a kölcsönzéseim közé |
| Row badge | — | kölcsönben · *tulajdonos* kottája |
| Ended loan | lejárt | visszavonva · visszakérte |

Folders and plans keep the same grant machinery — they are containers that lend the scores inside them, so *„kölcsönadott mappa"* reads correctly without a second concept.

**Model rename (decide now, not later).** The `shares` table was created 2026-08-31. Renaming `Share` → `Loan`, `shares` → `loans`, `ShareAccessService` → `LoanAccessService` today is a migration and a find-replace. After `received_shares`, exclusion sets and their tests accumulate, the same rename is archaeology. Recommendation: rename in the same pass as the UI vocabulary. The defensible alternative is keeping `Share` as the internal term and never surfacing it; drifting into a mismatch by accident is not defensible.

## The lending rules

Three sentences, and if they cannot be said this plainly the design is wrong.

1. **Whoever holds a lending link may read the score, print it, and keep it.** Signed in or not, forwarded or not. A link is a bearer token; this is already true.
2. **A loan may be passed along a chain of people; it may not go on a public noticeboard.** Lend a plan by link and the loans inside travel with it. Publish a plan and only your own scores go.
3. **The owner can change the lock whenever they like**, and it closes for everyone downstream at once.

### Why lending onward is allowed

Blocking does not stop redistribution, it selects the worst form of it. When Gergely wants Márta's arrangement in front of his choir:

| | Blocked → he re-uploads | Borrowed through her loan |
|---|---|---|
| Attribution | Lost — reads as his | Preserved, not removable |
| She corrects a bar | Never propagates; a fork | Everyone sees the correction |
| She revokes | No effect | Closes every downstream view |
| Her view-only setting | Irrelevant; his copy | Inherited; cannot be escalated |
| A rights complaint | Chase every copy | One row, one takedown |
| Storage | A duplicate per recipient | One file |

The primary argument is version integrity, not tolerance. In Hungarian church music, liturgical performance and a musician's own copy are free use; what no licence settles is whether the sheet on the stand is the corrected one. **A loan is a live subscription and a copy is a dead snapshot, and only one can carry a correction.** Márta attaches a flute part on Thursday and Gergely's flautist has it on Sunday.

The copyright worry belongs to `/ingyenes-kottak`, where an indexable page reaches an open-ended public and a review queue already exists. It is not a reason to constrain the private axis. (Worth a look from someone who does this professionally before it goes in writing on the site.)

### `allow_reshare` is not adopted

A permission gate on passing a loan onward would have to be understood by the person setting it, and nobody posting a link into a Messenger group is reasoning about a permission graph. Revocation, the lender's per-item inclusion choice, and the vocabulary cover everything the flag was for.

Per-person grants ("ask the owner for your own loan") were considered and rejected on scale: in a 3 000-member group, one score could accumulate thousands of grants for one owner to manage, and the "lend to anyone who asks" setting needed to make it bearable is `allow_reshare` in a new coat.

## Chain for reading, root for keeping

When someone saves a score they reached through another person's plan, their row records **the loan the score originates from, scoped to that score** — not the intermediary's plan loan, and not a newly minted grant.

```
Márta ──loan A──▶ Béla ──loan B──▶ Ilonka
  │                                  │
  └───────── keeping ────────────────┘   (scoped to one score)
                    reading ──▶ loan B
```

- **Reading** — anonymous, or signed in and not keeping — resolves through the loan actually opened. Béla revoking his plan ends it, as he intends.
- **Keeping** records the root loan plus the `score_id`. From then on access depends on the owner alone.

Rationale:

- **The intermediary should not hold a veto.** Béla is a route, not a rights-holder; deleting an old plan should not confiscate Márta's freely-lent score from people who kept it.
- **Grants scale with acts of lending, not with readers.** Márta has one link however many read it: *„1 kölcsönlink · 1 240 megnyitás · 380-an megtartották"*, one button to end it.
- **No folder escalation.** Márta lending a folder of twenty and Ilonka keeping one score yields a row naming that one score. A design that minted a grant, or that pointed at the folder loan, would widen access.
- **Containment was never real.** In a group of 3 000 a posted link is a link everyone has. The real question is whether those people hold live references or dead copies — and a chain-only model pushes the people who care most toward downloading.

**Given up:** the owner cannot remove one person while leaving the rest. At this scale that ability is false comfort for an already-public link; the answer if it is ever needed is to revoke and lend again. And revoking a plan takes back *the plan* — its arrangement, musics and order — not other people's scores that were in it. This is the one place the model surprises someone, so it needs a sentence where a plan loan is revoked.

## Plans, scores, and who sees which

A plan holds musics, not scores. Nobody edits anyone else's plan and nobody alters the scores selected in one. There is no per-user score selection.

### Private

For each music in a slot, the owner sees every score they have a right to — their own, ones they kept, and the public library. Nothing is chosen and nothing is stored. **The service list is this view**: a plan opened before a service, each borrowed entry resolved through its loan and carrying the score's last-changed date and any expiry. A list of live references rather than a folder of downloaded PDFs; a tablet on a music stand is ordinary now, and PDF, ChordPro and HTML remain export paths.

### Lent by link

The plan's grant carries the set of scores it opens — the owner's, and borrowed ones passed onward — editable afterwards from a loan management screen.

**Everything is included by default, and a score added later is included too.** The failure modes are not symmetric: a musician at a service who cannot open a score because of a forgotten tick is worse than one who sees a half-finished arrangement. Stored as **exclusions** on the grant, so an empty set means everything and nothing is written in the common case. The management screen marks what has joined since it was last opened.

### Published

A published plan is indexable and open to strangers, so it carries only the plan owner's scores. A borrowed one appears there for a reader who independently holds it and is invisible to everyone else — as private musics and private parts already behave.

All cases resolve in `ShareAccessService::scoresFor()`.

## Database schema

### Table: `received_shares`

One row per kept loan. It is a bookmark and an open-log, **never** consulted for authorization — `ShareAccessService` stays the only gate.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | `bigint` | Primary key | |
| `user_id` | `bigint` | FK `users.id`, cascade | Who opened or kept it |
| `share_id` | `bigint` | FK `shares.id`, cascade | The loan; the root loan when keeping something reached through a chain |
| `score_id` | `bigint` | FK `scores.id`, nullable, cascade | Null keeps the whole loan; set keeps one score out of it |
| `first_opened_at` | `timestamp` | Not null | Written on every authenticated open |
| `last_opened_at` | `timestamp` | Not null | |
| `kept_at` | `timestamp` | Nullable | Set only on a deliberate save |
| `hidden_at` | `timestamp` | Nullable | Dismissed without losing history |

**Unique:** (`user_id`, `share_id`, `score_id`)

Two readings of one table: *Kölcsönkapott kották* filters `kept_at IS NOT NULL`; the lender's "who opened this" reads every row.

### Table: `share_score_exclusions`

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `share_id` | `bigint` | FK `shares.id`, cascade | The plan or folder loan |
| `score_id` | `bigint` | FK `scores.id`, cascade | A score deliberately left out |

**Unique:** (`share_id`, `score_id`). Empty means everything is included.

### Table: `score_versions`

The published surface, frozen at submission. See *Publication* below.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | `bigint` | Primary key | |
| `score_id` | `bigint` | FK `scores.id`, cascade | |
| `content` | `text` | Nullable | Typed source as submitted |
| `format` | `varchar` | Nullable | |
| `settings` | `json` | Nullable | Render settings |
| `urls` | `json` | Nullable | Score links as submitted |
| `created_at` | `timestamp` | | |

Plus the set of `score_files` it includes (pivot or a `score_version_id` on an immutable file row — see the durability note below).

### Changes to existing tables

- `score_publications` — add `approved_version_id` and the id of the version under review, both FK `score_versions.id`, nullable.
- `music_plan_slot_assignments` — **no change.** The earlier idea of a `score_id` column is dropped; a plan holds musics only.

## Privacy

Recording opens makes a previously anonymous act attributable. Two obligations follow:

- Recipients are told before it happens — one line on the borrowed view, *„A kölcsönadó látja, hogy megnyitottad."* — plus a line in `/privacy`.
- The lender's list must not read as complete: signed-out opens stay anonymous, so it shows *„4 ismert megnyitás · 7 névtelen"*, never a bare list implying that is everyone.

The exposure is mild: `displayName` is the city-and-name nickname already public across the site. What is new is the link between a person and a document.

## Publication: re-review and versioning

### What already exists

`ScoreFile::booted()` watches `checksum`, `is_published` and `rights`. When one changes on an approved publication and `matchesApprovedFingerprint()` fails, `ScorePublicationService::invalidateApproval()` runs: status back to `Submitted`, `unpublished_at` stamped, fingerprint cleared, review note left, public caches flushed.

### The gap

The hook is on `ScoreFile`. `Score` has none — its only model hook is `deleting` — and `ScoreUrl` has none either. `computeFingerprint()` hashes published file checksums and nothing else.

But `PublicScoreView` exposes `content`, `format` and `settings`, with GABC, ABC and ChordPro rendered in the reader's browser from exactly those, and it loads `urls`, which the blade prints. **Uploading is policed; typing and linking are not.**

### The trigger

Review exists for copyright, so the trigger is anything that can introduce someone else's material:

- **Source edits** — `content`, `format`. Needs a `saved()` hook on `Score` and both fields in the fingerprint.
- **File upload, replacement or flag change** — already covered.
- **Link changes** — created, edited or removed `score_urls`. Needs its own hook and a place in the fingerprint.

**Render settings stay out.** A transpose or staff size changes how the same notes look and cannot introduce anyone else's work.

Two notes: publication licence and attribution edits already re-enter review, because the editor writes them through `ScorePublicationService::submit()`. And `ScoreFile`'s hook is on `saved` only, so deleting a published file leaves the fingerprint stale — removal need not re-review, but the fingerprint should be recomputed.

### Why versioning is needed

`invalidateApproval()` unpublishes. So today, correcting a wrong accidental takes a score off the public library until a reviewer gets to it: the site's answer to "there is an error in bar 12" is to remove the score rather than the error. Widening the trigger without versioning extends that to every typo fix.

Giving the publication a snapshot to point at clears every symptom:

- The public keeps reading the last approved version while the correction waits in the queue.
- The reviewer reads a stable target. Today `approve()` fingerprints whatever exists at the instant of approval, which need not be what the reviewer read.
- "The version published in July" becomes a producible row.
- `approved_fingerprint` reduces to a cheap equality check.

**Made at submission only** — not on every save, not on a timer. A handful of rows per score, no background machinery.

Settings sit inside the version (the page cannot render without them) but do not trigger re-review. Consequence: adjusting a transpose on a published score shows no change publicly until the next submission. That is correct — the public reads an approved artefact — but the editor should say so where the setting is changed.

### File durability (do this first)

`ScoreFileUploader::replace()` updates the row in place and calls `storage->deleteAll()` on the old artifacts; `deleteFile()` destroys them outright. A version cannot reference file rows and expect the bytes to survive — replacing a file would gut the approved snapshot.

- `replace()` must insert a new row rather than mutate one.
- A file referenced by any version must not be hard-deleted.

Checksums already identify content, so the model is halfway there. This is the part that touches storage and deserves the care.

### Where versioning must not spread

The private axis is exempt on purpose. A borrower wants the newest reading, always. No version history in the score editor, no version numbers in the interface, no branching, no per-version notes.

## The screen

Route `/kolcsonzesek`, replacing `/shared-links`. Three tabs:

1. **Kölcsönkapott kották** — kept loans. Owner, last-changed date, expiry, access state. Actions: open, add to a list, hide, ask again when ended.
2. **Kölcsönadott kották** — what I lent. Reach on every row (*„14 megnyitás · 5 ismert · 1 továbbadás"*), expiry, revoke.
3. **Közzétett kottáim** — publication status roll-up: draft, submitted, approved, rejected, taken down, with the reviewer's note and which version the public is reading. This exists nowhere today; status lives only inside a single score's editor, so a rejected nomination is invisible until you open that score.

A **loan management screen** for a lent plan or folder lists every score the link opens, all ticked by default, borrowed ones marked *kölcsönben · továbbadod*, and newly joined ones marked with the date they appeared.

The **save action** must be a bar across the top of a borrowed view for any signed-in non-owner — *„Mentés a kölcsönzéseim közé"*. A buried icon leaves the tab empty forever. There is no unopened badge: with explicit saving there is nothing unopened to count.

## Navigation

The `Könyvtár` group has become a drawer of seven mixing private material, the public library and catalogue metadata. Split it by whose material it is.

| Now | Proposed |
|-----|----------|
| Kezdőlap | Kezdőlap |
| Énektár | **Az én kottatáram:** Kottáim, Mappáim, Énekrendjeim, Kölcsönzések |
| *Énekrend:* Énekrendjeim, Közzétett énekrendek | **Böngészés:** Énektár, Énekek keresése, Ingyenes kották, Közzétett énekrendek, Gyűjtemények, Szerzők |
| *Könyvtár:* Énekek, Kottáim, Ingyenes kották, Mappáim, Titkos linkjeim, Gyűjtemények, Szerzők | |

- Everything in the first group is mine and editable; everything in the second belongs to the site and is browsable.
- *Énekrendjeim* moves up to sit with my scores and folders; *Közzétett énekrendek* moves down beside the public library.
- *Titkos linkjeim* becomes *Kölcsönzések*. An outbound-only entry with no inbound counterpart is what made the asymmetry visible.
- *Énekek* (`/musics`, the search) and *Énektár* (`/music-database`, the landing page over musics, collections and authors) are **not** duplicates, but two rows apart in one group they read as two names for one thing. Relabel the search *Énekek keresése*.

The guest header in `layouts/app/main.blade.php` needs the same treatment less urgently.

## Order of work

Two independent tracks.

### Lending

1. **Save, list, and log.** `received_shares` written from the three share mount paths, the save bar on borrowed views, the open notice. `/kolcsonzesek` with *Kölcsönkapott kották*, plus the existing shared-links list moved in as the second tab with opener names and reach.
2. **Menu regroup and vocabulary.** Sidebar split, *Énekek* relabelled, `/shared-links` redirecting, `lang/hu.json` through, origin line on every borrowed row. Do the model rename here if it is going to happen at all.
3. **Publication roll-up.** Third tab over `score_publications`. Independent of everything else.
4. **The service list.** A plan showing, per music, every score the viewer may see, each borrowed entry resolved through its loan with last-changed date and expiry. No new selection data; the work is in `ShareAccessService` and the plan view.
5. **Loan management for plans.** The exclusion set and the screen that edits it. Borrowed scores marked as passed on.
6. **Ask for access again.** An ended loan offers to notify its owner through the existing notification system. No approval flow, no per-person grants.

### Publication

1. **Make files durable.** `replace()` inserts rather than mutates; a file referenced by a version cannot be hard-deleted. Everything else rests on this.
2. **`score_versions` and the snapshot at submit.** The public library reads the version rather than the live score.
3. **Close the typing and linking paths.** Widen `computeFingerprint()`, add the `saved()` hook to `Score` and one to `ScoreUrl`. Last on purpose: this is the step that starts pulling scores off the shelf, and it should not land until versioning means they stay on it.

## Decisions

| # | Decision | Reason |
|---|----------|--------|
| 1 | Lending onward is allowed, and travels with the link | Blocking selects for the re-upload, which is worse on every count; and a rule nobody can state is a rule nobody follows |
| 2 | A loan stops at a published plan | An indexable page is a different act from handing someone a link |
| 3 | Reading resolves through the loan opened; keeping records the root loan scoped to the score | An intermediary is a route, not a rights-holder; and grants must scale with lending, not readers |
| 4 | No `allow_reshare`, no per-person grants, no policy setting | Neither survives a 3 000-member group, and both require the lender to reason about a permission graph |
| 5 | A lent plan carries an exclusion set; everything included by default | A forgotten tick that leaves a musician without a score at a service is worse than a half-finished arrangement being seen |
| 6 | The lender sees opener nicknames | `displayName` is already public site-wide; what is new is the link, and the notice covers it |
| 7 | Saving is explicit | A list that fills itself is noise within a month |
| 8 | Change signals are dates, not events | The list is opened before a service; that is when the date is read |
| 9 | Versioning is publication-only | The public needs a fixed thing to have approved; a borrower needs the opposite |
| 10 | Re-review triggers on anything that can carry someone else's work | The review exists for copyright, so the trigger is drawn on the same line |
| 11 | The site lends and borrows, it does not share | The word carries the obligation, which no permission flag can do |

Reference: the full design discussion is published at <https://claude.ai/code/artifact/8e6a58ff-a39a-4d60-8ade-6d492977d305>.
