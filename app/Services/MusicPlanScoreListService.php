<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\MusicPlan;
use App\Models\ReceivedLoan;
use App\Models\Score;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The service list: a plan opened before a service, showing for each music every
 * score the person reading it may actually see.
 *
 * A plan holds musics, not scores, and nobody edits anyone else's plan — so
 * nothing here is chosen and nothing is stored. The list is resolved per request
 * from the three access axes at once, which is why it lives in its own service
 * rather than inside either gate: LoanAccessService answers for lending,
 * PublicScoreAccessService for the library, and ownership answers for itself.
 * Composing them is a reading concern, not a widening of either.
 *
 * The upshot is that a published plan needs no special case. Each viewer sees
 * what they hold: the library, their own scores, and the ones they kept. A
 * borrowed score appears for a reader who independently holds it and is invisible
 * to everyone else, exactly as private musics and private parts already behave.
 *
 * Entries are live references rather than downloaded PDFs, so a correction the
 * lender makes on Thursday is on the stand on Sunday.
 */
class MusicPlanScoreListService
{
    public function __construct(private readonly LoanAccessService $loans) {}

    /**
     * Every score this viewer may see for the plan's musics, grouped by music id.
     *
     * @return Collection<int, Collection<int, array<string, mixed>>>
     */
    public function forViewer(MusicPlan $plan, ?User $viewer): Collection
    {
        $musicIds = $plan->assignedMusicIds();

        if ($musicIds->isEmpty()) {
            return collect();
        }

        $scores = Score::query()->whereIn('music_id', $musicIds);
        $this->scopeToViewer($scores, $viewer);

        $loansByScoreId = $viewer instanceof User ? $this->keptLoansByScoreId($viewer) : collect();

        return $scores
            ->with(['user', 'urls', 'publication'])
            ->orderBy('title')
            ->get()
            ->map(fn (Score $score): array => $this->describe($score, $viewer, $loansByScoreId))
            ->groupBy('music_id');
    }

    /**
     * The typed source of each named score this viewer may read.
     *
     * The booklet editor needs what forViewer() deliberately withholds — the
     * content itself — because it re-engraves every score in the browser at the
     * booklet's page size. It is the same three access axes and the same query,
     * so nothing is widened: a score reaches a booklet exactly when it would
     * reach the service list.
     *
     * Resolved per request, like everything else here, which is what makes a
     * recalled loan drop out of the booklet rather than leaving a copy behind.
     *
     * No attribution line travels with the source. A booklet is a service sheet
     * printed for the people in the pews, and a licence credit under every second
     * item reads as clutter to them; the credits stay on the library page the
     * score was taken from.
     *
     * @param  list<int>  $scoreIds
     * @return Collection<int, array<string, mixed>>
     */
    public function sourcesFor(array $scoreIds, ?User $viewer): Collection
    {
        if ($scoreIds === []) {
            return collect();
        }

        $query = Score::query()->whereIn('id', $scoreIds)->whereNotNull('format');
        $this->scopeToViewer($query, $viewer);

        return $query
            ->get()
            ->mapWithKeys(fn (Score $score): array => [$score->getKey() => [
                'id' => $score->id,
                'title' => $score->variationLabel(),
                'format' => $score->format?->value,
                'content' => $score->content ?? '',
                'settings' => $score->settings ?? [],
            ]]);
    }

    /**
     * Narrow a score query to what this viewer holds: the public library, their
     * own scores, and the ones they kept out of a live loan.
     *
     * @param  Builder<Score>  $query
     */
    private function scopeToViewer(Builder $query, ?User $viewer): void
    {
        $keptIds = $viewer instanceof User ? $this->loans->keptScoreIds($viewer) : [];
        $viewerId = $viewer?->getKey();

        if ($viewerId === null && $keptIds === []) {
            $query->published();

            return;
        }

        $query->where(function (Builder $inner) use ($viewerId, $keptIds): void {
            $inner->published();

            if ($viewerId !== null) {
                $inner->orWhere('user_id', $viewerId);
            }

            if ($keptIds !== []) {
                $inner->orWhereIn('id', $keptIds);
            }
        });
    }

    /**
     * @param  Collection<int, Loan>  $loansByScoreId
     * @return array<string, mixed>
     */
    private function describe(Score $score, ?User $viewer, Collection $loansByScoreId): array
    {
        $isOwn = $viewer instanceof User && $score->user_id === $viewer->getKey();
        $loan = $loansByScoreId->get($score->getKey());

        return [
            'id' => $score->id,
            'music_id' => $score->music_id,
            'title' => $score->variationLabel(),
            'format' => $score->format?->label() ?? __('Links'),
            'format_value' => $score->format?->value,
            'owner_name' => $score->user?->displayName,
            'is_own' => $isOwn,
            'is_borrowed' => ! $isOwn && $loan instanceof Loan,
            // Read before a service, so what matters is whether the arrangement has
            // moved since it was last looked at, and when it stops opening.
            'changed_at' => $score->updated_at,
            'expires_at' => $loan?->expires_at,
            'url' => $this->urlFor($score, $viewer, $loan),
            'incipit_url' => $score->hasIncipit() && $loan instanceof Loan
                ? $score->loanIncipitUrl($loan->token)
                : null,
        ];
    }

    /**
     * Where this viewer reads the score: their own editor, the loan they hold it
     * through, or the public library page.
     */
    private function urlFor(Score $score, ?User $viewer, ?Loan $loan): ?string
    {
        if ($viewer instanceof User && $score->user_id === $viewer->getKey()) {
            return route('scores.edit', ['score' => $score->id]);
        }

        if ($loan instanceof Loan) {
            return route('loan.score', ['token' => $loan->token, 'score' => $score->id]);
        }

        return $score->isPublished() ? $score->publicUrl() : null;
    }

    /**
     * The live loan each kept score is held through, keyed by score id.
     *
     * A score kept as part of a whole folder or plan loan is keyed to that loan, so
     * every borrowed entry has a link that stays inside the loan it came from.
     *
     * @return Collection<int, Loan>
     */
    private function keptLoansByScoreId(User $viewer): Collection
    {
        $byScoreId = collect();

        $receipts = ReceivedLoan::query()
            ->kept()
            ->where('user_id', $viewer->getKey())
            ->whereHas('loan', fn (Builder $query) => $query->live())
            ->with('loan.lendable')
            ->get();

        foreach ($receipts as $receipt) {
            $loan = $receipt->loan;

            if (! $loan instanceof Loan) {
                continue;
            }

            $scoreIds = $receipt->score_id !== null
                ? [$receipt->score_id]
                : $this->loans->scoreIdsFor($loan);

            foreach ($scoreIds as $scoreId) {
                $byScoreId->put($scoreId, $loan);
            }
        }

        return $byScoreId;
    }
}
