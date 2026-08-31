<?php

namespace App\Concerns;

use App\Models\Share;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Auth;

/**
 * Gives a model secret links. Applied to Score, Folder and MusicPlan.
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait HasShares
{
    public function shares(): MorphMany
    {
        return $this->morphMany(Share::class, 'shareable');
    }

    public function liveShares(): MorphMany
    {
        return $this->shares()->live();
    }

    /**
     * The live grant for this model, creating one if it does not exist yet.
     */
    public function mintShare(?User $user = null, ?string $label = null): Share
    {
        $existing = $this->liveShares()->latest('id')->first();

        if ($existing instanceof Share) {
            return $existing;
        }

        return $this->shares()->create([
            'user_id' => $user instanceof User ? $user->getKey() : Auth::id(),
            'token' => Share::generateToken(),
            'label' => $label,
        ]);
    }

    /**
     * Revoke every live grant on this model. Access derived through it — the scores
     * inside a shared folder, the scores a shared plan reaches — dies with it, since
     * nothing was ever minted onto those children.
     */
    public function revokeShares(): void
    {
        $this->liveShares()->update(['revoked_at' => now()]);
    }

    /**
     * The token of the current live grant, or null when the model is not shared.
     */
    public function shareToken(): ?string
    {
        return $this->liveShares()->latest('id')->value('token');
    }
}
