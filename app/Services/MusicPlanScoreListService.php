<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\MusicPlan;
use App\Models\ReceivedLoan;
use App\Models\Score;
use App\Models\ScoreUrl;
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
 * The upshot is that neither a published plan nor a lent one needs a special
 * case. Each viewer sees what they hold: the library, their own scores, the ones
 * they kept, and — on a lending link — whatever that link reaches. A borrowed
 * score appears for a reader who independently holds it and is invisible to
 * everyone else, exactly as private musics and private parts already behave.
 *
 * The lending link is why the open loan is an argument here rather than a union
 * performed by the caller: which token a score is read through depends on how the
 * reader arrived, and that decision belongs beside the other three.
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
     * `$openLoan` is the lending link the reader arrived on, when they arrived on
     * one. It widens the list by what that link reaches and decides which token
     * those entries are read through; everything else about the list is the same
     * on a lending link as it is on a published plan.
     *
     * @return Collection<int, Collection<int, array<string, mixed>>>
     */
    public function forViewer(MusicPlan $plan, ?User $viewer, ?Loan $openLoan = null): Collection
    {
        $musicIds = $plan->assignedMusicIds();

        if ($musicIds->isEmpty()) {
            return collect();
        }

        $openLoan = $this->loanLending($plan, $openLoan);
        $openLoanScoreIds = $openLoan instanceof Loan ? $this->loans->scoreIdsFor($openLoan) : [];

        $scores = Score::query()->whereIn('music_id', $musicIds);
        $this->scopeToViewer($scores, $viewer, $openLoanScoreIds);

        $loansByScoreId = $this->loansByScoreId($viewer, $openLoan, $openLoanScoreIds);

        return $scores
            ->with(['user', 'urls', 'publication', 'files'])
            ->orderBy('title')
            ->get()
            ->map(fn (Score $score): array => $this->describe($score, $viewer, $plan, $loansByScoreId))
            ->groupBy('music_id');
    }

    /**
     * The given loan, but only when it is really a loan of this plan.
     *
     * The loan arrives resolved from a URL token, so that it lends the plan being
     * read is checked here rather than assumed: a token for somebody else's plan
     * must widen nothing.
     */
    private function loanLending(MusicPlan $plan, ?Loan $openLoan): ?Loan
    {
        if (! $openLoan instanceof Loan) {
            return null;
        }

        return $openLoan->lendable?->is($plan) === true ? $openLoan : null;
    }

    /**
     * The typed source of each named score this viewer may read.
     *
     * The booklet editor needs what forViewer() deliberately withholds — the
     * content itself — because it re-engraves every score in the browser at the
     * booklet's page size. It is the same access axes and the same query, so
     * nothing is widened: a score reaches a booklet exactly when it would reach
     * the service list.
     *
     * `$openLoan` is the lending link the reader arrived on, exactly as in
     * forViewer(). The booklet's owner has none — they are reading their own
     * editor — but a musician opening the shared handout has nothing else, and
     * it is that link, not an account, that entitles them to the pages.
     *
     * Resolved per request, like everything else here, which is what makes a
     * recalled loan drop out of the booklet rather than leaving a copy behind.
     *
     * No attribution line travels with the source. A booklet is a service sheet
     * printed for the people in the pews, and a licence credit under every second
     * item reads as clutter to them; the credits stay on the library page the
     * score was taken from.
     *
     * A score reaches a booklet two ways. One written in the editor arrives as
     * its source, to be re-engraved at the booklet's size. An uploaded one cannot
     * be re-engraved, so it arrives as the systems RenderScoreFileJob cut out of
     * its pages — which is why it is listed here at all, having no format of its
     * own. A score offering neither is a page of links, and is left out.
     *
     * An uploaded score may hold several files, and they are not versions of one
     * another: the projection slide is not the accompaniment. So every one of
     * them that has been cut up travels here under `files`, for the booklet to
     * choose between, with `file_id` and `strips` naming the score's default —
     * the oldest, which is what a row that names no file gets.
     *
     * The score's own page and incipit come along as well, resolved the same way
     * forViewer() resolves them: the list of what is in a booklet is read the way
     * the plan beside it is read, and a line naming a score should lead back to
     * it.
     *
     * @param  list<int>  $scoreIds
     * @return Collection<int, array<string, mixed>>
     */
    public function sourcesFor(array $scoreIds, ?User $viewer, ?Loan $openLoan = null): Collection
    {
        if ($scoreIds === []) {
            return collect();
        }

        $openLoanScoreIds = $openLoan instanceof Loan ? $this->loans->scoreIdsFor($openLoan) : [];

        $query = Score::query()->whereIn('id', $scoreIds);
        $this->scopeToViewer($query, $viewer, $openLoanScoreIds);

        $scores = $query->with(['files', 'publication'])->get();

        // A loan only ever answers for a score that is not the viewer's own, and
        // this is resolved afresh on every rendered page and every strip served,
        // so a booklet built from one's own music asks nothing extra.
        $loansByScoreId = $viewer instanceof User && $scores->contains(fn (Score $score): bool => $score->user_id !== $viewer->getKey())
            ? $this->keptLoansByScoreId($viewer)
            : collect();

        foreach ($openLoanScoreIds as $scoreId) {
            $loansByScoreId->put($scoreId, $openLoan);
        }

        return $scores
            ->mapWithKeys(function (Score $score) use ($viewer, $loansByScoreId): array {
                $files = $score->format === null ? $this->drawableFiles($score) : [];
                $default = reset($files) ?: null;

                if ($score->format === null && $default === null) {
                    return [];
                }

                $loan = $loansByScoreId->get($score->getKey());

                return [$score->getKey() => [
                    'id' => $score->id,
                    'title' => $score->variationLabel(),
                    'format' => $score->format?->value,
                    'content' => $score->content ?? '',
                    'settings' => $score->settings ?? [],
                    'file_id' => $default['file_id'] ?? null,
                    'strips' => $default['strips'] ?? [],
                    'files' => $files,
                    'url' => $this->urlFor($score, $viewer, $loan),
                    'incipit_url' => $this->incipitUrlFor($score, $viewer, $loan),
                ]];
            });
    }

    /**
     * The score's uploaded files a booklet can actually draw, oldest first and
     * keyed by file id.
     *
     * A file that has not been cut into systems is left out rather than offered
     * and then found empty, which is the same rule the single-file case has
     * always applied — only now it is applied to each file rather than deciding
     * the whole score on the first one.
     *
     * @return array<int, array{file_id: int, name: string, strips: list<array<string, mixed>>}>
     */
    private function drawableFiles(Score $score): array
    {
        $files = [];

        foreach ($score->orderedFiles() as $file) {
            $strips = $file->stripList();

            if ($strips === []) {
                continue;
            }

            $files[$file->getKey()] = [
                'file_id' => $file->id,
                'name' => $file->displayName(),
                'strips' => $strips,
            ];
        }

        return $files;
    }

    /**
     * Narrow a score query to what this viewer holds: the public library, their
     * own scores, the ones they kept out of a live loan, and the ones the lending
     * link they are reading reaches.
     *
     * @param  Builder<Score>  $query
     * @param  list<int>  $openLoanScoreIds
     */
    private function scopeToViewer(Builder $query, ?User $viewer, array $openLoanScoreIds = []): void
    {
        $keptIds = $viewer instanceof User ? $this->loans->keptScoreIds($viewer) : [];
        $viewerId = $viewer?->getKey();

        if ($viewerId === null && $keptIds === [] && $openLoanScoreIds === []) {
            $query->published();

            return;
        }

        $query->where(function (Builder $inner) use ($viewerId, $keptIds, $openLoanScoreIds): void {
            $inner->published();

            if ($viewerId !== null) {
                $inner->orWhere('user_id', $viewerId);
            }

            if ($keptIds !== []) {
                $inner->orWhereIn('id', $keptIds);
            }

            if ($openLoanScoreIds !== []) {
                $inner->orWhereIn('id', $openLoanScoreIds);
            }
        });
    }

    /**
     * Dates are rendered here rather than handed on as instants. The reader of
     * this list is a person at a music stand, and one of the two views that
     * renders it is a Livewire component, whose properties these entries become.
     *
     * @param  Collection<int, Loan>  $loansByScoreId
     * @return array<string, mixed>
     */
    private function describe(Score $score, ?User $viewer, MusicPlan $plan, Collection $loansByScoreId): array
    {
        $isOwn = $viewer instanceof User && $score->user_id === $viewer->getKey();
        $loan = $loansByScoreId->get($score->getKey());
        $drawable = $score->format === null ? $this->drawableFiles($score) : [];

        return [
            'id' => $score->id,
            'music_id' => $score->music_id,
            'title' => $score->variationLabel(),
            'format' => $score->format?->label() ?? ($score->primaryFile()?->isReady() === true ? __('File') : __('Links')),
            'format_value' => $score->format?->value,
            // Whether a booklet can draw it: either it has a source to
            // re-engrave, or it has been cut into systems that can be flowed.
            'in_booklets' => $score->format !== null || $drawable !== [],
            // The uploaded files a booklet may choose between, where there is a
            // choice to make. Names only: the systems themselves are heavy, and
            // a list is read long before anything is drawn.
            'files' => array_values(array_map(
                fn (array $file): array => ['id' => $file['file_id'], 'name' => $file['name']],
                $drawable,
            )),
            'owner_id' => $score->user_id,
            'owner_name' => $score->user?->displayName,
            'is_own' => $isOwn,
            'is_borrowed' => ! $isOwn && $loan instanceof Loan,
            // Whose plan this is, so a view that already names the plan's owner
            // can attribute the entries that are somebody else's without
            // repeating them on every line.
            'is_plan_owners' => $score->user_id === $plan->user_id,
            // Read before a service, so what matters is whether the arrangement has
            // moved since it was last looked at, and when it stops opening.
            'changed_at' => $score->updated_at?->translatedFormat('Y-m-d'),
            'expires_at' => $loan?->expires_at?->translatedFormat('Y-m-d'),
            'url' => $this->urlFor($score, $viewer, $loan),
            'incipit_url' => $this->incipitUrlFor($score, $viewer, $loan),
            'urls' => $this->externalUrlsFor($score),
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
     * The incipit, from wherever this viewer is entitled to read the score — the
     * same four cases as urlFor(), because a list that links a score and cannot
     * draw it comes out looking broken rather than restricted.
     */
    private function incipitUrlFor(Score $score, ?User $viewer, ?Loan $loan): ?string
    {
        if (! $score->hasIncipit()) {
            return null;
        }

        if ($viewer instanceof User && $score->user_id === $viewer->getKey()) {
            return $score->incipitUrl();
        }

        if ($loan instanceof Loan) {
            return $score->loanIncipitUrl($loan->token);
        }

        return $score->isPublished() ? $score->publicIncipitUrl() : null;
    }

    /**
     * The score's own links out — a publisher's page, a recording — as a listing
     * renders them. Nothing here is gated: a link is not the score.
     *
     * @return array<int, array<string, mixed>>
     */
    private function externalUrlsFor(Score $score): array
    {
        return $score->urls->map(fn (ScoreUrl $url): array => [
            'url' => $url->url,
            'label' => $url->label?->label() ?? $url->url,
            'icon' => $url->label?->icon() ?? 'link',
            'color' => $url->label?->color() ?? 'text-gray-500',
            'host' => preg_replace('/^www\./', '', parse_url($url->url, PHP_URL_HOST) ?? $url->url),
            'comment' => $url->comment,
        ])->all();
    }

    /**
     * The loan each score is read through, keyed by score id.
     *
     * The lending link the reader is actually on is laid over the loans they kept,
     * so an entry reached both ways links back into the link they arrived on and
     * carries that link's expiry. Reading resolves through the loan opened; this
     * is that rule, applied to a list rather than to one score.
     *
     * @param  list<int>  $openLoanScoreIds
     * @return Collection<int, Loan>
     */
    private function loansByScoreId(?User $viewer, ?Loan $openLoan, array $openLoanScoreIds): Collection
    {
        $byScoreId = $viewer instanceof User ? $this->keptLoansByScoreId($viewer) : collect();

        if ($openLoan instanceof Loan) {
            foreach ($openLoanScoreIds as $scoreId) {
                $byScoreId->put($scoreId, $openLoan);
            }
        }

        return $byScoreId;
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
