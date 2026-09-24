<?php

namespace App\Livewire;

use App\Models\Loan;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View as IlluminateView;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Every live lending link of one score, folder, plan or booklet, side by side.
 *
 * One thing may be lent several ways at once — an open link handed round for a
 * week beside a standing one restricted to the band — so each link is named,
 * copied and recalled on its own. Who a link opens for is set on its own screen.
 */
class LoanLinks extends Component
{
    /**
     * The score, folder, plan or booklet being lent. Anything using HasLoans.
     */
    #[Locked]
    public Model $lendable;

    public function mount(Model $lendable): void
    {
        $this->lendable = $lendable;
    }

    /**
     * Hand out another link, open to anyone until the lender says otherwise.
     */
    public function lend(): void
    {
        $this->ensureOwner();

        $this->lendable->lend(Auth::user());

        $this->changed();
    }

    /**
     * Name a link, so the lender can tell "the band" from "the retreat".
     */
    public function rename(int $loanId, string $label): void
    {
        $label = trim(mb_substr($label, 0, 100));

        $this->findLoan($loanId)->forceFill(['label' => $label === '' ? null : $label])->save();

        unset($this->loans);
    }

    /**
     * Take one link back. The others stay open.
     */
    public function recall(int $loanId): void
    {
        $this->findLoan($loanId)->revoke();

        $this->changed();

        $this->dispatch('toast', message: __('Loan recalled.'), type: 'success');
    }

    /**
     * @return Collection<int, Loan>
     */
    #[Computed]
    public function loans(): Collection
    {
        return $this->lendable->liveLoans()
            ->with('lendable')
            ->withCount('recipients')
            ->oldest('id')
            ->get();
    }

    public function render(): IlluminateView
    {
        return view('livewire.loan-links');
    }

    private function findLoan(int $loanId): Loan
    {
        $this->ensureOwner();

        /** @var Loan */
        return $this->lendable->liveLoans()->findOrFail($loanId);
    }

    private function ensureOwner(): void
    {
        abort_unless(Auth::check() && $this->lendable->getAttribute('user_id') === Auth::id(), 403);
    }

    /**
     * Tell the page around this list that what is lent changed, for its badges.
     */
    private function changed(): void
    {
        unset($this->loans);

        $this->dispatch('loans-changed');
    }
}
