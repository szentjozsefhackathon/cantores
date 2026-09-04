<?php

namespace App\Livewire\Pages;

use App\Models\Folder;
use App\Models\Loan;
use App\Models\Score;
use App\Services\LoanAccessService;
use App\Services\LoanKeepingService;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View as IlluminateView;
use Livewire\Component;

class FolderView extends Component
{
    public ?Folder $folder = null;

    public string $name = '';

    public string $loanToken = '';

    /** Whose folder this is, so a borrowed page says whose work it holds. */
    public string $ownerName = '';

    public bool $kept = false;

    public bool $canKeep = false;

    /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\Score> */
    public $scores;

    public function mount(string $token): void
    {
        $loanAccess = app(LoanAccessService::class);

        $loan = $loanAccess->resolveOfType($token, Folder::class);
        abort_if(! $loan instanceof Loan, 404);

        /** @var Folder $folder */
        $folder = $loan->lendable;

        if (Auth::check() && Auth::id() === $folder->user_id) {
            $this->redirectRoute('folders.edit', ['folder' => $folder->id], navigate: true);

            return;
        }

        $loan->touchLastViewed();

        $receipt = app(LoanKeepingService::class)->recordOpen($loan, Auth::user());

        $this->folder = $folder;
        $this->name = $folder->name;
        $this->loanToken = $token;
        $this->ownerName = $folder->user?->displayName ?? '';
        $this->canKeep = Auth::check() && Auth::id() !== $loan->user_id;
        $this->kept = $receipt?->isKept() === true;

        // Not the folder's own contents: the lender may have left some out, and the
        // loan is the only thing that says which.
        $this->scores = Score::query()
            ->whereIn('id', $loanAccess->scoreIdsFor($loan))
            ->with(['music', 'files'])
            ->orderBy('title')
            ->get();
    }

    /**
     * Save the whole folder into the reader's own lending centre.
     */
    public function keep(): void
    {
        if (! Auth::check()) {
            return;
        }

        $loan = app(LoanAccessService::class)->resolveOfType($this->loanToken, Folder::class);

        if (! $loan instanceof Loan) {
            return;
        }

        if (app(LoanKeepingService::class)->keep($loan, Auth::user()) === null) {
            return;
        }

        $this->kept = true;

        $this->dispatch('toast', message: __('Saved to your loans.'), type: 'success');
    }

    public function rendering(IlluminateView $view): void
    {
        if (! $this->folder instanceof Folder) {
            return;
        }

        $view->layout('layouts::app.main', [
            'title' => $this->folder->name,
            'noindex' => true,
        ]);
    }

    public function render(): IlluminateView
    {
        return view('livewire.pages.folder-view');
    }
}
