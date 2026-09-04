<?php

namespace App\Concerns;

use App\Models\Loan;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Auth;

/**
 * Gives a model lending links. Applied to Score, Folder and MusicPlan.
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait HasLoans
{
    public function loans(): MorphMany
    {
        return $this->morphMany(Loan::class, 'lendable');
    }

    public function liveLoans(): MorphMany
    {
        return $this->loans()->live();
    }

    /**
     * The live loan on this model, creating one if it does not exist yet.
     */
    public function mintLoan(?User $user = null, ?string $label = null): Loan
    {
        $existing = $this->liveLoans()->latest('id')->first();

        if ($existing instanceof Loan) {
            return $existing;
        }

        return $this->loans()->create([
            'user_id' => $user instanceof User ? $user->getKey() : Auth::id(),
            'token' => Loan::generateToken(),
            'label' => $label,
        ]);
    }

    /**
     * Revoke every live loan on this model. Access derived through it — the scores
     * inside a lent folder, the scores a lent plan reaches — dies with it, since
     * nothing was ever minted onto those children.
     */
    public function revokeLoans(): void
    {
        $this->liveLoans()->update(['revoked_at' => now()]);
    }

    /**
     * The token of the current live loan, or null when the model is not lent.
     */
    public function loanToken(): ?string
    {
        return $this->liveLoans()->latest('id')->value('token');
    }
}
