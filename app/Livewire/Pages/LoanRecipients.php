<?php

namespace App\Livewire\Pages;

use App\Models\Loan;
use App\Models\ReceivedLoan;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View as IlluminateView;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Who a loan opens for.
 *
 * Open by default: anyone holding the link gets in, which is what a lending link
 * has always been. Restricted, it opens only for the lender and the people named
 * here, each of whom has to be signed in — and none of them may pass it on.
 *
 * People are added by the email they registered with, or picked from those who
 * already opened one of the lender's loans. There is no directory to browse:
 * names on this site are pseudonyms, and a lender should not be able to list them.
 */
class LoanRecipients extends Component
{
    public ?Loan $loan = null;

    public bool $restricted = false;

    public string $email = '';

    public function mount(Loan $loan): void
    {
        abort_unless($loan->user_id === Auth::id(), 404);

        $this->loan = $loan->load('lendable');
        $this->restricted = $loan->restricted;
    }

    public function updatedRestricted(bool $restricted): void
    {
        abort_unless($this->loan instanceof Loan, 404);

        $this->loan->forceFill(['restricted' => $restricted])->save();

        $this->dispatch(
            'toast',
            message: $restricted ? __('Only the people listed can open this loan now.') : __('Anyone with the link can open this loan now.'),
            type: 'success',
        );
    }

    public function addByEmail(): void
    {
        abort_unless($this->loan instanceof Loan, 404);

        $this->validate(
            ['email' => ['required', 'email']],
            [
                'email.required' => __('Enter an email address.'),
                'email.email' => __('Enter a valid email address.'),
            ],
        );

        $user = User::query()
            ->notBlocked()
            ->whereRaw('lower(email) = ?', [mb_strtolower(trim($this->email))])
            ->first();

        if (! $user instanceof User) {
            $this->addError('email', __('Nobody is registered with this email address.'));

            return;
        }

        if ($user->getKey() === $this->loan->user_id) {
            $this->addError('email', __('You can always open your own loans.'));

            return;
        }

        $this->attach($user);
        $this->email = '';
    }

    /**
     * Add someone who already opened one of the lender's loans.
     */
    public function add(int $userId): void
    {
        $user = $this->suggestions->firstWhere('id', $userId);

        abort_unless($user instanceof User, 404);

        $this->attach($user);
    }

    public function remove(int $userId): void
    {
        abort_unless($this->loan instanceof Loan, 404);

        $this->loan->recipients()->detach($userId);

        unset($this->recipients, $this->suggestions);
    }

    /**
     * @return Collection<int, User>
     */
    #[Computed]
    public function recipients(): Collection
    {
        return $this->loan?->recipients()->with(['city', 'firstName'])->orderBy('loan_recipients.created_at')->get()
            ?? new Collection;
    }

    /**
     * People who opened any of this lender's loans while signed in, and are not on
     * this one yet — the likely names, without a directory to search.
     *
     * @return Collection<int, User>
     */
    #[Computed]
    public function suggestions(): Collection
    {
        if (! $this->loan instanceof Loan) {
            return new Collection;
        }

        $openerIds = ReceivedLoan::query()
            ->whereHas('loan', fn (Builder $query) => $query->where('user_id', $this->loan->user_id))
            ->distinct()
            ->pluck('user_id');

        return User::query()
            ->notBlocked()
            ->whereIn('id', $openerIds)
            ->whereKeyNot($this->loan->user_id)
            ->whereNotIn('id', $this->recipients->modelKeys())
            ->with(['city', 'firstName'])
            ->limit(20)
            ->get();
    }

    public function rendering(IlluminateView $view): void
    {
        $view->layout('layouts::app', [
            'title' => __('Who can open this loan'),
        ]);
    }

    public function render(): IlluminateView
    {
        return view('livewire.pages.loan-recipients');
    }

    private function attach(User $user): void
    {
        abort_unless($this->loan instanceof Loan, 404);

        $this->loan->recipients()->syncWithoutDetaching([$user->getKey()]);

        unset($this->recipients, $this->suggestions);

        $this->dispatch('toast', message: __(':name can open this loan.', ['name' => $user->displayName]), type: 'success');
    }
}
