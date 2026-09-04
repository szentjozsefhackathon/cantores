<?php

namespace App\Livewire\Pages;

use App\Models\Loan;
use App\Models\Score;
use App\Models\ScoreFile;
use App\Services\LoanAccessService;
use App\Services\LoanKeepingService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View as IlluminateView;
use Livewire\Attributes\Computed;
use Livewire\Component;

class ScoreView extends Component
{
    public ?Score $score = null;

    public string $title = '';

    public ?string $format = null;

    public string $content = '';

    /** @var array<string, array<string, array<string, mixed>>> */
    public array $settings = [];

    /**
     * The token of the grant this score is being viewed through, so the page can
     * build further links (incipits) that stay inside the same grant.
     */
    public string $loanToken = '';

    /**
     * Whether this loan lets the reader take the original file away, as opposed
     * to only reading the rendered pages.
     */
    public bool $allowDownload = false;

    /**
     * The nickname of whoever the score belongs to, so a borrowed page says whose
     * work it is. Attribution is the point of lending rather than re-uploading.
     */
    public string $ownerName = '';

    /**
     * Whether this reader has already saved the score into their own lending centre.
     */
    public bool $kept = false;

    /**
     * Whether there is anyone to offer the save bar to: a signed-in non-owner.
     */
    public bool $canKeep = false;

    /**
     * Serves both the direct score link (/s/{token}) and a score reached through a
     * folder or plan grant (/share/{token}/score/{score}).
     */
    public function mount(string $token, mixed $score = null): void
    {
        $loanAccess = app(LoanAccessService::class);

        $loan = $loanAccess->resolve($token);
        abort_if(! $loan instanceof Loan, 404);

        if (is_numeric($score)) {
            $score = Score::query()->find((int) $score);
        }

        if ($score instanceof Score) {
            abort_unless($loanAccess->grantsScore($loan, $score), 404);
        } else {
            abort_unless($loan->lendable instanceof Score, 404);
            $score = $loan->lendable;
        }

        if (Auth::check() && Auth::id() === $score->user_id) {
            $this->redirectRoute('scores.edit', ['score' => $score->id], navigate: true);

            return;
        }

        $loan->touchLastViewed();

        $receipt = app(LoanKeepingService::class)->recordOpen($loan, Auth::user());

        // The lender reading their own loan has nothing to save; everyone else does.
        $this->canKeep = Auth::check() && Auth::id() !== $loan->user_id;
        $this->kept = $receipt?->isKept() === true || $this->hasKeptScore($score);
        $this->ownerName = $score->user?->displayName ?? '';

        $this->score = $score->load('urls');
        $this->loanToken = $token;
        $this->allowDownload = (bool) $loan->allow_download;
        $this->title = $score->title;
        $this->format = $score->format?->value;
        $this->content = $score->content ?? '';
        $this->settings = $score->settings ?? [];
    }

    /**
     * Save this score into the reader's own lending centre.
     *
     * Recorded against the loan the score originates from rather than the one that
     * happened to reach it, so the person who passed it on cannot take it back.
     */
    public function keep(): void
    {
        if (! Auth::check() || ! $this->score instanceof Score) {
            return;
        }

        $loan = app(LoanAccessService::class)->resolve($this->loanToken);

        if (! $loan instanceof Loan) {
            return;
        }

        // Null when there is nothing to keep — the reader is the lender passing on
        // their own loan, or the score's owner.
        if (app(LoanKeepingService::class)->keep($loan, Auth::user(), $this->score) === null) {
            return;
        }

        $this->kept = true;

        $this->dispatch('toast', message: __('Saved to your loans.'), type: 'success');
    }

    /**
     * Whether this score is already in the reader's list, however it got there.
     */
    private function hasKeptScore(Score $score): bool
    {
        $user = Auth::user();

        return $user !== null
            && in_array($score->getKey(), app(LoanAccessService::class)->keptScoreIds($user), true);
    }

    /**
     * The uploaded files behind this score, oldest first — a score often keeps
     * the editable source beside the PDFs cut for different paper.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Models\ScoreFile>
     */
    #[Computed]
    public function scoreFiles(): Collection
    {
        return $this->score?->orderedFiles() ?? new Collection;
    }

    /**
     * Whether the queue still owes this score a preview, so the page keeps
     * polling until it lands.
     */
    #[Computed]
    public function filesRendering(): bool
    {
        return $this->scoreFiles->contains(fn (ScoreFile $scoreFile): bool => $scoreFile->isRendering());
    }

    /**
     * URLs of every rendered page, in page order, keyed by score file id.
     *
     * @return array<int, list<string>>
     */
    #[Computed]
    public function filePageUrls(): array
    {
        $urls = [];

        foreach ($this->scoreFiles as $scoreFile) {
            $urls[$scoreFile->id] = array_map(
                fn (int $page): string => route('loan.score.file.page', [
                    'token' => $this->loanToken,
                    'score' => $this->score,
                    'scoreFile' => $scoreFile,
                    'page' => $page,
                ]),
                $scoreFile->pageNumbers(),
            );
        }

        return $urls;
    }

    public function rendering(IlluminateView $view): void
    {
        if (! $this->score instanceof Score) {
            return;
        }

        $view->layout('layouts::app.main', [
            'title' => $this->score->title,
            'noindex' => true,
        ]);
    }

    public function render(): IlluminateView
    {
        return view('livewire.pages.score-view');
    }
}
