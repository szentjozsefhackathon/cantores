<?php

namespace App\Livewire\Pages;

use App\Models\Folder;
use App\Models\Loan;
use App\Models\MusicPlan;
use App\Models\ReceivedLoan;
use App\Models\Score;
use App\Models\ScorePublication;
use App\Services\LoanAccessService;
use App\Services\LoanKeepingService;
use App\Services\NotificationService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View as IlluminateView;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The lending centre: what I borrowed, what I lent, and what I published.
 *
 * The screen exists because the private axis left no trace a recipient could
 * return to. A link from March lived in an inbox; now it lives here, on the
 * site, beside the loans the same person handed out and the scores they offered
 * the public library.
 *
 * Nothing here decides access. LoanAccessService is the only gate; these lists
 * are bookmarks, reach figures and status.
 */
class Loans extends Component
{
    use AuthorizesRequests, WithPagination;

    public const TAB_BORROWED = 'borrowed';

    public const TAB_LENT = 'lent';

    public const TAB_PUBLISHED = 'published';

    #[Url(as: 'tab', keep: false)]
    public string $tab = self::TAB_BORROWED;

    /**
     * The loan whose openers are expanded, if any. The list is collapsed by
     * default: it names people, and that should be a deliberate look.
     */
    public ?int $expandedLoanId = null;

    public function rendering(IlluminateView $view): void
    {
        $view->layout('layouts::app', [
            'title' => __('Loans'),
        ]);
    }

    public function selectTab(string $tab): void
    {
        $this->tab = in_array($tab, [self::TAB_BORROWED, self::TAB_LENT, self::TAB_PUBLISHED], true)
            ? $tab
            : self::TAB_BORROWED;

        $this->expandedLoanId = null;
        $this->resetPage();
    }

    public function toggleOpeners(int $loanId): void
    {
        $this->expandedLoanId = $this->expandedLoanId === $loanId ? null : $loanId;
    }

    public function revoke(int $loanId): void
    {
        $loan = Loan::query()->mine(Auth::user())->findOrFail($loanId);

        $loan->revoke();

        $this->dispatch('toast', message: __('Loan recalled.'), type: 'success');
    }

    /**
     * Put a borrowed score into one of my own folders.
     *
     * The folder is a bookmark, not a grant: the score stays reachable only while
     * the loan behind it is live, and LoanAccessService checks that on every
     * request. Lending the folder on therefore passes the score on for exactly as
     * long as I am entitled to it myself.
     */
    public function addToFolder(int $receiptId, int $folderId): void
    {
        $receipt = ReceivedLoan::query()
            ->with(['loan.lendable', 'score'])
            ->where('user_id', Auth::id())
            ->findOrFail($receiptId);

        $folder = Folder::query()->mine(Auth::user())->findOrFail($folderId);

        $score = $receipt->score ?? ($receipt->loan?->lendable instanceof Score ? $receipt->loan->lendable : null);

        if (! $score instanceof Score) {
            return;
        }

        // Not a formality: a kept row outlives the loan it remembers, and a folder
        // must never be the thing that keeps a recalled score reachable.
        if (! in_array($score->getKey(), app(LoanAccessService::class)->keptScoreIds(Auth::user()), true)) {
            $this->dispatch('toast', message: __('This loan has ended.'), type: 'error');

            return;
        }

        $folder->scores()->syncWithoutDetaching([$score->getKey()]);

        $this->dispatch('toast', message: __('Added to :folder.', ['folder' => $folder->name]), type: 'success');
    }

    /**
     * The folders a borrowed score can be filed into.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Folder>
     */
    #[Computed]
    public function myFolders(): \Illuminate\Database\Eloquent\Collection
    {
        return Folder::query()->mine(Auth::user())->orderBy('name')->get();
    }

    /**
     * The single score a kept row stands for, if it stands for one.
     */
    public function keptScore(ReceivedLoan $receipt): ?Score
    {
        return $receipt->score ?? ($receipt->loan?->lendable instanceof Score ? $receipt->loan->lendable : null);
    }

    /**
     * Take a kept loan off the borrowed list without losing that it was opened.
     */
    public function hide(int $receiptId): void
    {
        $receipt = ReceivedLoan::query()
            ->where('user_id', Auth::id())
            ->findOrFail($receiptId);

        app(LoanKeepingService::class)->hide($receipt);

        $this->dispatch('toast', message: __('Removed from your loans.'), type: 'success');
    }

    /**
     * Ask the owner of an ended loan to lend it again.
     *
     * No approval flow and no per-person grant: it is a message, and the owner
     * answers it by lending again or not at all.
     */
    public function askAgain(int $receiptId): void
    {
        $receipt = ReceivedLoan::query()
            ->with(['loan.lendable', 'loan.user'])
            ->where('user_id', Auth::id())
            ->findOrFail($receiptId);

        $loan = $receipt->loan;

        if (! $loan instanceof Loan || $loan->user === null) {
            return;
        }

        app(NotificationService::class)->createLoanRequest(
            $loan,
            Auth::user(),
            __(':name is asking you to lend “:title” again.', [
                'name' => Auth::user()->displayName,
                'title' => $this->describeReceipt($receipt)['title'],
            ]),
        );

        $this->dispatch('toast', message: __('Your request has been sent.'), type: 'success');
    }

    /**
     * The loans this person kept, newest first.
     *
     * @return LengthAwarePaginator<int, ReceivedLoan>
     */
    #[Computed]
    public function borrowed(): LengthAwarePaginator
    {
        return ReceivedLoan::query()
            ->kept()
            ->visible()
            ->where('user_id', Auth::id())
            ->with(['loan.lendable', 'loan.user', 'score.user'])
            ->latest('kept_at')
            ->paginate(15, pageName: 'borrowedPage');
    }

    /**
     * The loans this person handed out and has not taken back, newest first.
     *
     * Ended loans are left out: this list is what is currently open, and a recalled
     * link is not something the lender has to act on.
     *
     * @return LengthAwarePaginator<int, Loan>
     */
    #[Computed]
    public function lent(): LengthAwarePaginator
    {
        return Loan::query()
            ->mine(Auth::user())
            ->live()
            ->with('lendable')
            ->latest('id')
            ->paginate(15, pageName: 'lentPage');
    }

    /**
     * Every score this person has offered the public library, whatever came of it.
     *
     * Publication status lives inside a single score's editor today, so a rejected
     * nomination is invisible until you happen to open that score. This is the roll-up.
     *
     * @return LengthAwarePaginator<int, ScorePublication>
     */
    #[Computed]
    public function published(): LengthAwarePaginator
    {
        return ScorePublication::query()
            ->whereHas('score', fn (Builder $query) => $query->where('user_id', Auth::id()))
            ->with(['score', 'reviewer'])
            ->latest('updated_at')
            ->paginate(15, pageName: 'publishedPage');
    }

    /**
     * What a lender may say about one loan's reach.
     *
     * @return array{opens: int, known: int, anonymous: int, kept: int, passed_on: int}
     */
    public function reach(Loan $loan): array
    {
        return app(LoanKeepingService::class)->reach($loan);
    }

    /**
     * The people who opened a loan while signed in.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, ReceivedLoan>
     */
    public function openers(Loan $loan): \Illuminate\Database\Eloquent\Collection
    {
        return app(LoanKeepingService::class)->openers($loan);
    }

    /**
     * How many scores a container loan currently opens, so the lender can tell at a
     * glance whether their exclusions took effect.
     */
    public function reachedScoreCount(Loan $loan): int
    {
        return count(app(LoanAccessService::class)->scoreIdsFor($loan));
    }

    /**
     * A label, an owner and an address for a kept loan.
     *
     * @return array{type: string, title: string, owner: string, url: string|null, changed_at: \Illuminate\Support\Carbon|null}
     */
    public function describeReceipt(ReceivedLoan $receipt): array
    {
        $loan = $receipt->loan;
        $token = $loan?->token;

        if ($receipt->score instanceof Score) {
            return [
                'type' => __('Score'),
                'title' => $receipt->score->title,
                'owner' => $receipt->score->user?->displayName ?? '',
                'url' => $token === null ? null : route('loan.score', ['token' => $token, 'score' => $receipt->score]),
                'changed_at' => $receipt->score->updated_at,
            ];
        }

        $lendable = $loan?->lendable;

        return match (true) {
            $lendable instanceof Score => [
                'type' => __('Score'),
                'title' => $lendable->title,
                'owner' => $loan->user?->displayName ?? '',
                'url' => route('score.loan', ['token' => $token]),
                'changed_at' => $lendable->updated_at,
            ],
            $lendable instanceof Folder => [
                'type' => __('Folder'),
                'title' => $lendable->name,
                'owner' => $loan->user?->displayName ?? '',
                'url' => route('folder.loan', ['token' => $token]),
                'changed_at' => $lendable->updated_at,
            ],
            $lendable instanceof MusicPlan => [
                'type' => __('Music Plan'),
                'title' => $lendable->celebration_name ?? __('Music Plan'),
                'owner' => $loan->user?->displayName ?? '',
                'url' => route('music-plan.loan', ['token' => $token]),
                'changed_at' => $lendable->updated_at,
            ],
            default => [
                'type' => __('Unknown'),
                'title' => __('Deleted'),
                'owner' => '',
                'url' => null,
                'changed_at' => null,
            ],
        };
    }

    /**
     * A label and edit URL for whatever a loan lends.
     *
     * @return array{type: string, title: string, url: string|null}
     */
    public function describe(Loan $loan): array
    {
        $lendable = $loan->lendable;

        return match (true) {
            $lendable instanceof Score => [
                'type' => __('Score'),
                'title' => $lendable->title,
                'url' => route('scores.edit', ['score' => $lendable->id]),
            ],
            $lendable instanceof Folder => [
                'type' => __('Folder'),
                'title' => $lendable->name,
                'url' => route('folders.edit', ['folder' => $lendable->id]),
            ],
            $lendable instanceof MusicPlan => [
                'type' => __('Music Plan'),
                'title' => $lendable->celebration_name ?? __('Music Plan'),
                'url' => null,
            ],
            default => ['type' => __('Unknown'), 'title' => __('Deleted'), 'url' => null],
        };
    }

    /**
     * The public URL a loan resolves to.
     */
    public function linkFor(Loan $loan): string
    {
        return match (true) {
            $loan->lendable instanceof Folder => route('folder.loan', ['token' => $loan->token]),
            $loan->lendable instanceof MusicPlan => route('music-plan.loan', ['token' => $loan->token]),
            default => route('score.loan', ['token' => $loan->token]),
        };
    }

    /**
     * Why a kept loan can no longer be opened, if it cannot.
     */
    public function endedReason(?Loan $loan): ?string
    {
        if (! $loan instanceof Loan) {
            return __('Deleted');
        }

        return match (true) {
            $loan->revoked_at !== null => __('Recalled'),
            $loan->expires_at !== null && $loan->expires_at->isPast() => __('Expired'),
            default => null,
        };
    }

    /**
     * The counts on the tab headings.
     *
     * @return Collection<string, int>
     */
    #[Computed]
    public function tabCounts(): Collection
    {
        return collect([
            self::TAB_BORROWED => ReceivedLoan::query()->kept()->visible()->where('user_id', Auth::id())->count(),
            self::TAB_LENT => Loan::query()->mine(Auth::user())->live()->count(),
            self::TAB_PUBLISHED => ScorePublication::query()
                ->whereHas('score', fn (Builder $query) => $query->where('user_id', Auth::id()))
                ->count(),
        ]);
    }

    public function render(): IlluminateView
    {
        return view('livewire.pages.loans');
    }
}
