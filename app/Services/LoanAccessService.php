<?php

namespace App\Services;

use App\Models\Folder;
use App\Models\Loan;
use App\Models\MusicPlan;
use App\Models\ReceivedLoan;
use App\Models\Score;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Resolves lending links and decides what a link reaches.
 *
 * Access to a score held through a folder or plan loan is derived here on every
 * request rather than minted onto the score, so revoking the loan revokes every
 * URL beneath it and nothing is left behind to garbage-collect. That is the
 * invariant the whole design rests on: derive, never mint.
 *
 * A loan may be passed along a chain — Márta lends a score to Béla, Béla puts it
 * in a plan and lends that on — and the chain is resolved here too. Reading goes
 * through the loan actually opened, so an intermediary revoking their own loan
 * ends what they lent. Keeping (LoanKeepingService) records the root loan instead,
 * because an intermediary is a route, not a rights-holder.
 */
class LoanAccessService
{
    /**
     * How far a chain of loans is followed before the answer is "no".
     *
     * A cycle cannot form through live data — a score has one owner and the root
     * of every chain is that owner — but the set is built from rows anyone can
     * write, so the walk is bounded rather than trusted.
     */
    private const MAX_CHAIN_DEPTH = 8;

    /**
     * The live loan for a token, or null when the token is unknown, revoked or expired.
     */
    public function resolve(?string $token): ?Loan
    {
        if (! is_string($token) || $token === '') {
            return null;
        }

        return Loan::query()
            ->live()
            ->with('lendable')
            ->where('token', $token)
            ->first();
    }

    /**
     * The live loan for a token, but only when it lends the given model class.
     *
     * The entry-point routes are typed — /f/{token} is a folder link — so a token
     * that resolves to a different kind of loan must not be honoured there.
     *
     * @param  class-string  $lendableType
     */
    public function resolveOfType(?string $token, string $lendableType): ?Loan
    {
        $loan = $this->resolve($token);

        return $loan?->lendable instanceof $lendableType ? $loan : null;
    }

    /**
     * Whether a loan reaches the given score.
     */
    public function grantsScore(Loan $loan, Score $score): bool
    {
        return in_array($score->getKey(), $this->scoreIdsFor($loan), true);
    }

    /**
     * Every score a loan reaches, for the folder and plan listing views.
     *
     * @return Collection<int, Score>
     */
    public function scoresFor(Loan $loan): Collection
    {
        $ids = $this->scoreIdsFor($loan);

        if ($ids === []) {
            /** @var Collection<int, Score> */
            return Score::query()->whereRaw('1 = 0')->get();
        }

        return Score::query()->whereIn('id', $ids)->orderBy('title')->get();
    }

    /**
     * The ids of every score a loan reaches, after the lender's exclusions.
     *
     * @return list<int>
     */
    public function scoreIdsFor(Loan $loan): array
    {
        return $this->scoreIdsForLoan($loan, []);
    }

    /**
     * Whether a score reached through this loan belongs to someone other than the
     * lender — that is, whether the lender is passing on something they borrowed.
     */
    public function isPassedOn(Loan $loan, Score $score): bool
    {
        return $score->user_id !== $loan->user_id;
    }

    /**
     * Every score a user holds a live right to read besides their own: the ones
     * they kept out of somebody else's loan that has not been revoked or expired.
     *
     * Nothing is stored on the scores themselves, so an owner revoking their loan
     * empties this set for everyone downstream on the next request.
     *
     * @return list<int>
     */
    public function keptScoreIds(User $user): array
    {
        return $this->keptScoreIdsFor($user->getKey(), []);
    }

    /**
     * Constrain a score query to what a loan reaches.
     *
     * @param  Builder<Score>  $query
     * @return Builder<Score>
     */
    public function scopeToLoan(Builder $query, Loan $loan): Builder
    {
        return $query->whereIn('id', $this->scoreIdsFor($loan));
    }

    /**
     * Every live loan of the owner's that reaches a score — its own, plus any folder
     * or plan loan that leads to it. This is what an owner needs to see before
     * assuming a score is private: a folder or plan they lent once still opens it.
     *
     * Loans other people made while passing the score on are deliberately not listed.
     * They are not the owner's to revoke, and revoking the owner's own loan closes
     * them anyway, since the borrower's right is derived from it.
     *
     * @return Collection<int, Loan>
     */
    public function loansReaching(Score $score): Collection
    {
        $folderIds = $score->folders()->pluck('folders.id');

        $planIds = MusicPlan::query()
            ->where('user_id', $score->user_id)
            ->when(
                $score->music_id !== null,
                fn (Builder $query) => $query->whereHas(
                    'musicAssignments',
                    fn (Builder $assignments) => $assignments->where('music_id', $score->music_id)
                ),
                fn (Builder $query) => $query->whereRaw('1 = 0')
            )
            ->pluck('id');

        /** @var Collection<int, Loan> $loans */
        $loans = Loan::query()
            ->live()
            ->with('lendable')
            ->where(function (Builder $query) use ($score, $folderIds, $planIds): void {
                $query->where(fn (Builder $q) => $q->where('lendable_type', Score::class)->where('lendable_id', $score->getKey()))
                    ->orWhere(fn (Builder $q) => $q->where('lendable_type', Folder::class)->whereIn('lendable_id', $folderIds))
                    ->orWhere(fn (Builder $q) => $q->where('lendable_type', MusicPlan::class)->whereIn('lendable_id', $planIds));
            })
            ->latest('id')
            ->get();

        // A container loan can have the score excluded from it, in which case it does
        // not reach it at all and must not be reported as an open door.
        return $loans->filter(
            fn (Loan $loan): bool => $this->grantsScore($loan, $score)
        )->values();
    }

    /**
     * @param  list<int>  $seenLoanIds
     * @return list<int>
     */
    private function scoreIdsForLoan(Loan $loan, array $seenLoanIds): array
    {
        if (in_array($loan->getKey(), $seenLoanIds, true) || count($seenLoanIds) >= self::MAX_CHAIN_DEPTH) {
            return [];
        }

        $seenLoanIds[] = $loan->getKey();

        $lendable = $loan->lendable;

        $ids = match (true) {
            $lendable instanceof Score => [$lendable->getKey()],
            $lendable instanceof Folder => $this->folderScoreIds($lendable, $seenLoanIds),
            $lendable instanceof MusicPlan => $this->planScoreIds($lendable, $seenLoanIds),
            default => [],
        };

        if ($ids === []) {
            return [];
        }

        // A single-score loan has nothing to exclude, and reading its exclusions
        // would let a stray row revoke the loan by the back door.
        if (! $lendable instanceof Score) {
            $excluded = $loan->exclusions()->pluck('score_id')->all();
            $ids = array_values(array_diff($ids, $excluded));
        }

        return array_values(array_unique(array_map('intval', $ids)));
    }

    /**
     * The scores a folder reaches: its owner's own, plus the ones they borrowed,
     * put in the folder, and are still entitled to.
     *
     * A borrowed score sitting in someone's folder is passed on only while their
     * own right to it holds. The owner recalling their loan therefore closes it
     * here on the next request, without anything being unpicked.
     *
     * @param  list<int>  $seenLoanIds
     * @return list<int>
     */
    private function folderScoreIds(Folder $folder, array $seenLoanIds): array
    {
        $ids = $folder->scores()->pluck('scores.id')->map(fn ($id): int => (int) $id)->all();

        if ($ids === []) {
            return [];
        }

        $ownIds = Score::query()
            ->whereIn('id', $ids)
            ->where('user_id', $folder->user_id)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $borrowed = array_diff($ids, $ownIds);

        if ($borrowed === []) {
            return $ownIds;
        }

        $stillHeld = array_intersect($borrowed, $this->keptScoreIdsFor($folder->user_id, $seenLoanIds));

        return array_values([...$ownIds, ...$stillHeld]);
    }

    /**
     * The scores a plan reaches: its owner's own, plus the ones the owner kept out
     * of someone else's loan and is passing on.
     *
     * Borrowed scores travel with a lending link but not with a publication — see
     * MusicPlanPublicView, which asks for the owner's half only.
     *
     * @param  list<int>  $seenLoanIds
     * @return list<int>
     */
    private function planScoreIds(MusicPlan $plan, array $seenLoanIds): array
    {
        $musicIds = $plan->assignedMusicIds();

        if ($musicIds->isEmpty()) {
            return [];
        }

        $keptIds = $this->keptScoreIdsFor($plan->user_id, $seenLoanIds);

        return Score::query()
            ->whereIn('music_id', $musicIds)
            ->where(function (Builder $query) use ($plan, $keptIds): void {
                $query->where('user_id', $plan->user_id);

                if ($keptIds !== []) {
                    $query->orWhereIn('id', $keptIds);
                }
            })
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * @param  list<int>  $seenLoanIds
     * @return list<int>
     */
    private function keptScoreIdsFor(int $userId, array $seenLoanIds): array
    {
        /** @var Collection<int, ReceivedLoan> $receipts */
        $receipts = ReceivedLoan::query()
            ->kept()
            ->where('user_id', $userId)
            ->whereHas('loan', fn (Builder $query) => $query->live())
            ->with('loan.lendable')
            ->get();

        $ids = [];

        foreach ($receipts as $receipt) {
            if ($receipt->score_id !== null) {
                $ids[] = $receipt->score_id;

                continue;
            }

            $loan = $receipt->loan;

            if ($loan instanceof Loan) {
                $ids = [...$ids, ...$this->scoreIdsForLoan($loan, $seenLoanIds)];
            }
        }

        return array_values(array_unique(array_map('intval', $ids)));
    }
}
