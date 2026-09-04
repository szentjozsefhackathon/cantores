<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\ReceivedLoan;
use App\Models\Score;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * Writes the borrower's side of a loan: who opened it, and what they kept.
 *
 * Nothing here grants access. LoanAccessService is the only gate, and a kept row
 * whose loan has since been revoked is a dead bookmark. What this adds is a place
 * a borrower can come back to — a link from March lives in an inbox, not on the
 * site, and that is the gap the lending centre exists to close.
 *
 * Reading resolves through the loan actually opened; keeping records the *root*
 * loan the score originates from, scoped to that score. An intermediary is a route,
 * not a rights-holder: Béla deleting the plan he passed on must not confiscate
 * Márta's freely-lent score from the people who kept it.
 */
class LoanKeepingService
{
    /**
     * How far the chain back to the owner is followed before giving up.
     */
    private const MAX_CHAIN_DEPTH = 8;

    public function __construct(private readonly LoanAccessService $access) {}

    /**
     * Note that this person opened this loan. Anonymous opens are counted on the
     * loan itself and named nowhere.
     */
    public function recordOpen(Loan $loan, ?User $user): ?ReceivedLoan
    {
        if (! $user instanceof User || $user->getKey() === $loan->user_id) {
            return null;
        }

        $now = Carbon::now();

        $receipt = $this->receipt($loan, $user, null);

        if ($receipt instanceof ReceivedLoan) {
            $receipt->forceFill(['last_opened_at' => $now])->save();

            return $receipt;
        }

        return ReceivedLoan::query()->create([
            'user_id' => $user->getKey(),
            'loan_id' => $loan->getKey(),
            'score_id' => null,
            'first_opened_at' => $now,
            'last_opened_at' => $now,
        ]);
    }

    /**
     * Save what this loan opens into the borrower's own list.
     *
     * With a score given, only that score is kept, recorded against the loan it
     * originates from — so a folder of twenty lent onward and one score kept yields
     * a row naming that one score, and never widens into the folder.
     *
     * With no score, the loan is kept whole: the folder, or the plan with its
     * arrangement and order. That one the lender can take back.
     */
    public function keep(Loan $loan, User $user, ?Score $score = null): ?ReceivedLoan
    {
        if ($user->getKey() === $loan->user_id) {
            return null;
        }

        [$rootLoan, $scopedScore] = $this->rootFor($loan, $score);

        if ($rootLoan->user_id === $user->getKey()) {
            return null;
        }

        $now = Carbon::now();

        $receipt = $this->receipt($rootLoan, $user, $scopedScore?->getKey())
            ?? ReceivedLoan::query()->create([
                'user_id' => $user->getKey(),
                'loan_id' => $rootLoan->getKey(),
                'score_id' => $scopedScore?->getKey(),
                'first_opened_at' => $now,
                'last_opened_at' => $now,
            ]);

        $receipt->keep();

        return $receipt;
    }

    /**
     * Drop a kept loan out of the borrower's list without losing the open history.
     */
    public function hide(ReceivedLoan $receipt): void
    {
        $receipt->forceFill(['hidden_at' => Carbon::now()])->save();
    }

    /**
     * What a lender may say about a loan's reach.
     *
     * The named openers are never the whole story — a signed-out reader leaves no
     * name — so the anonymous remainder is reported beside them rather than left
     * for the lender to assume away.
     *
     * @return array{opens: int, known: int, anonymous: int, kept: int, passed_on: int}
     */
    public function reach(Loan $loan): array
    {
        $known = $loan->receipts()->distinct()->count('user_id');
        $opens = max($loan->open_count, $known);

        return [
            'opens' => $opens,
            'known' => $known,
            'anonymous' => max($opens - $known, 0),
            'kept' => $loan->receipts()->kept()->distinct()->count('user_id'),
            'passed_on' => $this->passedOnCount($loan),
        ];
    }

    /**
     * The people who opened this loan while signed in, most recent first.
     *
     * @return Collection<int, ReceivedLoan>
     */
    public function openers(Loan $loan): Collection
    {
        /** @var Collection<int, ReceivedLoan> */
        return $loan->receipts()
            ->with('user')
            ->latest('last_opened_at')
            ->get();
    }

    /**
     * How many live loans of other people's carry a score out of this one.
     *
     * A borrower passing a loan onward makes their own loan; this counts those, so
     * the lender can see the link has travelled without being told by whom.
     */
    private function passedOnCount(Loan $loan): int
    {
        $scoreIds = $this->access->scoreIdsFor($loan);

        if ($scoreIds === []) {
            return 0;
        }

        $borrowerIds = $loan->receipts()->kept()->pluck('user_id')->unique();

        if ($borrowerIds->isEmpty()) {
            return 0;
        }

        return Loan::query()
            ->live()
            ->whereIn('user_id', $borrowerIds)
            ->with('lendable')
            ->get()
            ->filter(fn (Loan $onward): bool => array_intersect(
                $this->access->scoreIdsFor($onward),
                $scoreIds
            ) !== [])
            ->count();
    }

    /**
     * The loan a kept score should be recorded against, and the score to scope it to.
     *
     * Walks back through the intermediaries until the loan is the owner's own. A
     * loan of a whole container is kept as itself, with no score scope.
     *
     * @return array{0: Loan, 1: Score|null}
     */
    private function rootFor(Loan $loan, ?Score $score): array
    {
        if (! $score instanceof Score) {
            return [$loan, null];
        }

        // A loan of exactly this one score is already the thing being kept; there is
        // no narrower scope to record.
        $scope = $loan->lendable instanceof Score && $loan->lendable->getKey() === $score->getKey()
            ? null
            : $score;

        $root = $loan;

        for ($depth = 0; $depth < self::MAX_CHAIN_DEPTH; $depth++) {
            if ($score->user_id === $root->user_id) {
                break;
            }

            $next = $this->lendersOwnReceipt($root, $score)?->loan;

            if (! $next instanceof Loan) {
                break;
            }

            $root = $next;
            $scope = $next->lendable instanceof Score && $next->lendable->getKey() === $score->getKey()
                ? null
                : $score;
        }

        return [$root, $scope];
    }

    /**
     * The receipt through which this loan's lender holds a score they do not own.
     */
    private function lendersOwnReceipt(Loan $loan, Score $score): ?ReceivedLoan
    {
        /** @var Collection<int, ReceivedLoan> $receipts */
        $receipts = ReceivedLoan::query()
            ->kept()
            ->where('user_id', $loan->user_id)
            ->whereHas('loan', fn (Builder $query) => $query->live())
            ->with('loan.lendable')
            ->get();

        return $receipts->first(function (ReceivedLoan $receipt) use ($score): bool {
            if ($receipt->score_id === $score->getKey()) {
                return true;
            }

            return $receipt->score_id === null
                && $receipt->loan instanceof Loan
                && $this->access->grantsScore($receipt->loan, $score);
        });
    }

    private function receipt(Loan $loan, User $user, ?int $scoreId): ?ReceivedLoan
    {
        return ReceivedLoan::query()
            ->where('user_id', $user->getKey())
            ->where('loan_id', $loan->getKey())
            ->where(fn (Builder $query) => $scoreId === null
                ? $query->whereNull('score_id')
                : $query->where('score_id', $scoreId))
            ->first();
    }
}
