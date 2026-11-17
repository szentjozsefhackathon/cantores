<?php

namespace App\Policies;

use App\Models\Season;
use App\Models\User;

/**
 * A season is its leader's to edit. The choir reaches it only through a lending
 * link, which LoanAccessService answers for, never through this policy.
 */
class SeasonPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Season $season): bool
    {
        return $season->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Season $season): bool
    {
        return $season->user_id === $user->id;
    }

    public function delete(User $user, Season $season): bool
    {
        return $season->user_id === $user->id;
    }

    public function restore(User $user, Season $season): bool
    {
        return false;
    }

    public function forceDelete(User $user, Season $season): bool
    {
        return false;
    }
}
