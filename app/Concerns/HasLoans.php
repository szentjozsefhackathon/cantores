<?php

namespace App\Concerns;

use App\Models\Loan;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Auth;

/**
 * Gives a model lending links. Applied to Score, Folder and MusicPlan.
 *
 * @mixin Model
 */
trait HasLoans
{
    /**
     * A lendable's loans go with it.
     *
     * `loans` is a morph table, so no foreign key cascades them: without this,
     * deleting a score or a folder would leave a live token behind pointing at
     * nothing. Deleting the rows also cascades the receipts and exclusions
     * hanging off them.
     */
    public static function bootHasLoans(): void
    {
        static::deleting(function (self $lendable): void {
            $lendable->loans()->delete();
        });
    }

    public function loans(): MorphMany
    {
        return $this->morphMany(Loan::class, 'lendable');
    }

    public function liveLoans(): MorphMany
    {
        return $this->loans()->live();
    }

    /**
     * Hand out a new lending link, alongside any already live.
     *
     * A model may be lent several ways at once — a week-long link for a crowd
     * beside a standing one for the band — and each is recalled on its own.
     */
    public function lend(?User $user = null, ?string $label = null): Loan
    {
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
     * The token of the newest live loan, or null when the model is not lent.
     */
    public function loanToken(): ?string
    {
        return $this->liveLoans()->latest('id')->value('token');
    }
}
