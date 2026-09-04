<?php

namespace App\Livewire\Pages;

use App\Models\Folder;
use App\Models\Loan;
use App\Models\LoanScoreExclusion;
use App\Models\MusicPlan;
use App\Models\Score;
use App\Services\LoanAccessService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View as IlluminateView;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Which scores a lent folder or plan actually opens.
 *
 * Everything is lent by default and a score added later is lent too, because the
 * failure modes are not symmetric: a musician at a service who cannot open a
 * score because of a forgotten tick is worse than one who sees a half-finished
 * arrangement. What the lender removes is stored as an exclusion, so the common
 * case writes nothing at all.
 */
class LoanManager extends Component
{
    use AuthorizesRequests;

    public ?Loan $loan = null;

    /**
     * Score ids the lender has taken out of this loan.
     *
     * @var list<int>
     */
    public array $excluded = [];

    public function mount(Loan $loan): void
    {
        abort_unless($loan->user_id === Auth::id(), 404);
        abort_unless($loan->isContainer(), 404);

        $this->loan = $loan->load('lendable');
        $this->excluded = $loan->exclusions()->pluck('score_id')->map(fn ($id): int => (int) $id)->all();
    }

    /**
     * Every score this loan could open, whether or not it currently does.
     *
     * Read off the container rather than through LoanAccessService: the excluded
     * ones have to appear here to be put back.
     *
     * @return \Illuminate\Support\Collection<int, Score>
     */
    #[Computed]
    public function candidates(): \Illuminate\Support\Collection
    {
        $lendable = $this->loan?->lendable;

        $scores = match (true) {
            $lendable instanceof Folder => $lendable->scores()->with('user')->orderBy('title')->get(),
            $lendable instanceof MusicPlan => $this->planCandidates($lendable),
            default => collect(),
        };

        return $scores->values();
    }

    /**
     * Whether a score joined this loan after the lender last looked at this screen.
     *
     * A container grows on its own — a score added to the folder, a music assigned
     * to the plan — so the lender is told what arrived rather than left to notice.
     *
     * Dated by when the score was created, not when it joined: `folder_score` has
     * no timestamps, and a score created since the last look is the case that
     * actually matters.
     */
    public function isNew(Score $score): bool
    {
        $seenAt = $this->loan?->contents_reviewed_at;

        return $seenAt !== null
            && $score->created_at instanceof Carbon
            && $score->created_at->greaterThan($seenAt);
    }

    /**
     * Whether the lender is passing on a score that is not theirs.
     */
    public function isPassedOn(Score $score): bool
    {
        return $this->loan !== null && $score->user_id !== $this->loan->user_id;
    }

    public function toggle(int $scoreId): void
    {
        abort_unless($this->loan instanceof Loan, 404);

        if (in_array($scoreId, $this->excluded, true)) {
            $this->loan->exclusions()->where('score_id', $scoreId)->delete();
            $this->excluded = array_values(array_diff($this->excluded, [$scoreId]));

            return;
        }

        LoanScoreExclusion::query()->firstOrCreate([
            'loan_id' => $this->loan->getKey(),
            'score_id' => $scoreId,
        ]);

        $this->excluded[] = $scoreId;
    }

    /**
     * Mark everything currently in the loan as seen, so the "new" marks clear.
     */
    public function markReviewed(): void
    {
        abort_unless($this->loan instanceof Loan, 404);

        $this->loan->forceFill(['contents_reviewed_at' => Carbon::now()])->save();

        $this->dispatch('toast', message: __('Loan contents reviewed.'), type: 'success');
    }

    public function rendering(IlluminateView $view): void
    {
        $view->layout('layouts::app', [
            'title' => __('What this loan opens'),
        ]);
    }

    public function render(): IlluminateView
    {
        return view('livewire.pages.loan-manager');
    }

    /**
     * The plan owner's own scores for the plan's musics, plus the ones they
     * borrowed and are passing on.
     *
     * @return \Illuminate\Support\Collection<int, Score>
     */
    private function planCandidates(MusicPlan $plan): \Illuminate\Support\Collection
    {
        $musicIds = $plan->assignedMusicIds();

        if ($musicIds->isEmpty()) {
            return collect();
        }

        $keptIds = app(LoanAccessService::class)->keptScoreIds($plan->user);

        return Score::query()
            ->whereIn('music_id', $musicIds)
            ->where(function ($query) use ($plan, $keptIds): void {
                $query->where('user_id', $plan->user_id);

                if ($keptIds !== []) {
                    $query->orWhereIn('id', $keptIds);
                }
            })
            ->with('user')
            ->orderBy('title')
            ->get();
    }
}
