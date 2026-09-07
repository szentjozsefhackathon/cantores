# Lent Plan Service List Implementation Plan

> **Status: implemented.** Landed as planned, with one correction recorded in §3: the
> badge marks the *reader's own* score rather than attributing the lender's. Forking a
> lent part remains out of scope and is now recorded as an open question in
> `lending-and-borrowing-design.md` under *Forking a lent part*.

## Context

The same music plan has two read-only views, and they resolve the scores under each music
by different rules:

| View | Route | Resolves scores via | Axes it composes |
|---|---|---|---|
| Published plan | `/music-plan/{plan}/view` | `MusicPlanScoreListService::forViewer` | own + kept + public library |
| Lending link | `/p/{token}` | `LoanAccessService::scoresFor($loan)` | the loan only |

`MusicPlanLoanView::loadPlanSlots` (`app/Livewire/Pages/MusicPlanLoanView.php:113`) lists
strictly what the lender's loan reaches. Nothing the reader independently holds appears —
not their own scores, not the ones they kept out of somebody else's loan, not the free
library.

The case that exposes it: a band leader lends a plan; the flutist finds her part in it,
writes her own articulated setting of the same music, and then cannot see it on the link
she was given. She holds it, the plan names the music, and the page still shows only the
lender's copy. She has to keep two tabs open at a music stand.

This is not an access-control gap — every axis is already gated and already composed
correctly one layer down. It is that the lending link never asks
`MusicPlanScoreListService` the question the published view asks it. The service's own
docblock claims composition as its job ("Composing them is a reading concern, not a
widening of either"); the lending link is the one caller that bypasses it.

**Decisions taken:**

- The open loan becomes a **fourth axis on the service**, not a union performed in the
  component. `urlFor()` has to know which token the reader arrived on, so the composition
  cannot be done from outside without duplicating it.
- **One entry shape for both views.** The lending link keeps its richer renderer (incipit
  + external links inside the music card); the published view keeps its compact list. Only
  the shape they read is unified.
- **The library axis now applies on a lending link too.** A guest on a secret link will see
  free-library scores for the plan's musics alongside the lent ones. This is a visible
  behaviour change and is intended: it is the same list, and it is what makes the flutist's
  own score appear without needing a fork.
- **Exclusions govern what the loan reaches, not what the reader holds.** A score the
  lender ticked off the loan reappears for a reader who owns it, kept it elsewhere, or
  finds it published. This looks like a bypass and is not one; it needs a sentence in the
  design doc so it is never "fixed" by accident.
- **Out of scope:** forking a borrowed score into one's own (`ScoreDuplicator` copies
  `user_id` from the source; `addVariation` authorizes `update` on the source). That is a
  separate change with a rights argument attached, and this one stands without it.

No schema change. This is a pure read path.

---

## 1. `app/Services/MusicPlanScoreListService.php`

### 1.1 The signature

```php
public function forViewer(MusicPlan $plan, ?User $viewer, ?Loan $openLoan = null): Collection
```

`BookletEditor` and the published plan view pass no loan and are unaffected.

Guard the new argument rather than trusting it — the loan is resolved from a URL token:

```php
if ($openLoan instanceof Loan && $openLoan->lendable?->is($plan) !== true) {
    $openLoan = null;
}
```

### 1.2 `scopeToViewer()` gains the axis

Add `$openLoan` and fold its reachable ids in beside `$keptIds`:

```php
$loanIds = $openLoan instanceof Loan ? $this->loans->scoreIdsFor($openLoan) : [];
```

The early return for "no viewer and nothing kept" must now also require `$loanIds === []`,
otherwise a guest on a lending link falls through to `published()` only — the current bug,
relocated.

### 1.3 `urlFor()` learns the open loan

Precedence, most specific first:

1. viewer owns it → `route('scores.edit', …)`
2. the **open** loan reaches it → `route('loan.score', ['token' => $openLoan->token, …])`
3. a kept loan reaches it → that loan's token (unchanged)
4. published → `$score->publicUrl()`

The open loan must outrank a kept one so the reader stays inside the link they are reading.

### 1.4 `incipitUrlFor()` — new, mirroring `urlFor()`

Today `incipit_url` is populated only when a loan is involved, so a viewer's own score and a
library score have no incipit. The lending link renders incipits, so its rows would come out
inconsistent. Same four cases: `incipitUrl()` / `loanIncipitUrl($token)` /
`loanIncipitUrl($keptToken)` / `publicIncipitUrl()`, each guarded by `hasIncipit()`.

No visible change to the published view — `x-plan-score-list` renders no incipit.

### 1.5 `describe()` — three additions

| Key | Why |
|---|---|
| `urls` | The lending link renders each score's external `ScoreUrl` links. `forViewer` already eager-loads `urls` and then discards them, so this costs no query. Shape must match what `music/card.blade.php` reads: `url`, `label`, `icon`, `color`, `host`, `comment`. |
| `is_plan_owners` | `$score->user_id === $plan->user_id`. Replaces the loan view's `is_passed_on`; for a plan loan the lender *is* the plan owner (only the owner can mint the link), so the two are the same predicate. |
| `owner_id` | Lets a caller reason about attribution without a second query. |

**Format the dates here.** `changed_at` and `expires_at` are currently Carbon instances.
`x-plan-score-list` is a plain Blade component, so that is fine today — but `music-card` is a
Livewire component and `loanScores` is a public array property, so Carbon objects nested in
it go through hydration. Emit `translatedFormat('Y-m-d')` strings from `describe()` and drop
the formatting from `x-plan-score-list`. The service is already a presenter (it emits
`__('File')` and `__('Links')` labels), so this is in keeping, and it removes the
serialization hazard rather than working around it.

---

## 2. `app/Livewire/Pages/MusicPlanLoanView.php`

`loadPlanSlots()` loses its bespoke score mapping (the `$scoresByMusicId` closure, ~35
lines) and becomes:

```php
$scoresByMusicId = app(MusicPlanScoreListService::class)
    ->forViewer($this->musicPlan, Auth::user(), $loan);
```

`$lenderId` and the `ScoreUrl` mapping go away with it. `ownerName`, `canKeep`, `kept` and
the keep flow are untouched — keeping is still recorded against the loan, not the list.

Rewrite the method docblock: the list is now the service list with the open loan as a fourth
axis, and what the reader sees is theirs alone.

---

## 3. `resources/views/components/music/card.blade.php`

The `loanScores` block (lines 176–232) reads three keys that the service names differently:

| Blade reads | Service emits |
|---|---|
| `loan_url` | `url` |
| `is_passed_on` | `is_plan_owners` (inverted) |
| `format`, `title`, `owner_name`, `incipit_url`, `urls` | same |

The attribution badge needs a new rule. Today it fires on `is_passed_on` — the lender's own
scores get no badge because the page header already says whose plan it is. Once the reader's
own score sits in the same list, "whose is this" becomes ambiguous, so:

```blade
@if(empty($loanScore['is_own']) && empty($loanScore['is_plan_owners']) && !empty($loanScore['owner_name']))
```

For a guest this renders exactly as it does today (nothing is `is_own`, so the predicate
reduces to the current one).

**Correction made while implementing.** This plan first said the band leader's own scores
would be attributed to him, which contradicts the rule above and would have put a badge on
almost every line of a page whose header already names him. The ambiguity is resolved from
the other side instead: the minority case is badged. A new `Your score` / *Saját kottád*
badge fires on `is_own`, the attribution rule is left doing exactly what it did, and the
guest view is unchanged to the character.

`resources/views/components/plan-score-list.blade.php` changes only where it formatted the
two dates.

---

## 4. Tests

`tests/Feature/MusicPlanServiceListTest.php` — the axis itself:

- an open loan adds the lender's scores for a guest
- the open loan's token wins over a kept loan's token in `url`
- a loan for a *different* plan is ignored (the §1.1 guard)
- own and published scores both carry an incipit url now

`tests/Feature/MusicPlanLoanTest.php` — the scenario:

- a signed-in reader sees their own score for a plan music on the lending link
- …and it is absent for a guest and for a different reader
- a published library score appears for a guest on the link (the intended behaviour change)
- the lender's own score is attributed, the reader's own is not

`tests/Feature/LoanExclusionTest.php` — the invariant, so it is not "fixed" later:

- a score the lender excluded from the plan loan still appears for a reader who owns it

Regressions to run rather than assume: `MusicPlanLoanTest` in full (`does not show scores
from other users` must still pass — its viewer is a guest and the stranger's score is
unpublished), `MusicPlanServiceListTest`, `BookletEditorTest`, `BookletScoreSourcesTest`,
`LoanChainTest`, `LoanKeepingTest`, `MusicCardComponentTest`.

```
php artisan test --compact --filter='MusicPlanLoan|MusicPlanServiceList|LoanExclusion|LoanChain|LoanKeeping|Booklet|MusicCard'
```

---

## 5. Document updates

**a. `plans/lending-and-borrowing-design.md`** — the design record, and the one place this
change is load-bearing.

- *The service list* (§ around line 95–109): it currently reads "For each music in a slot,
  the owner sees every score they have a right to — their own, ones they kept, and the
  public library." Extend to four axes and state that the lending link is the same list, not
  a second one. The existing sentence at line 109 ("A borrowed one appears there for a
  reader who independently holds it and is invisible to everyone else") already says the
  right thing about a published plan; say it of a lent plan too.
- Add the exclusion invariant: **an exclusion governs what the loan reaches, not what the
  reader holds.** With the reasoning — the alternative is a lending link that hides a
  reader's own work from them, which no lender intended and no lender can see.
- The comparison table at line 54, row *"She corrects a bar → Never propagates; a fork"*:
  add a note that it is arguing against re-uploading in place of borrowing, and does not
  settle whether a performer may fork a lent part into her own annotated copy. That question
  is open and is not decided by this change.
- The decisions table (~line 279): a row for "a lending link composes the reader's own axes
  alongside the loan" with the one-line reason.

**b. `app/Services/MusicPlanScoreListService.php` class docblock** — currently "The upshot
is that a published plan needs no special case." Extend: a lent plan needs none either; it
is the same list with one more axis, which is why the loan is an argument here rather than a
union performed by a caller.

**c. `app/Livewire/Pages/MusicPlanLoanView.php`** — the `loadPlanSlots` docblock, per §2.

**d. `docs/felhasznaloi-kezikonyv.md`** — §6.3 covers publishing a plan but predates lending
entirely; it says nothing about the secret link. Add a short Hungarian subsection: what the
link opens, that the recipient sees their own scores beside the lent ones, and that only
they see them. Small, and the one user-facing surface of this change.

**e. This file** — status note when the work lands.

---

## 6. Risks

- **Query volume on the lending link.** `describe()` calls `primaryFile()` and
  `hasIncipit()` per score, and `hasIncipit()` hits `Storage::exists`. The lending link now
  lists more scores per music than before. It is the same cost the published view already
  pays, so this is a widening rather than a new problem — worth a look under a plan with
  many musics before it ships.
- **Guests see library scores on a lending link.** Deliberate (§Decisions) but visible.
  Worth the band leader knowing; it is the sort of thing that reads as a leak until
  explained, which is what document update (d) is for.

## 7. Order

1 → 2 → 3, then tests, then documents. Steps 1–3 are one commit's worth; nothing here is
independently shippable, since §1 alone changes no behaviour and §2 alone will not compile
against the old shape.
