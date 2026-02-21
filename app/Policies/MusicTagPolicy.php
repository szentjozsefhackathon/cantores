<?php

namespace App\Policies;

use App\Models\MusicTag;
use App\Models\User;

class MusicTagPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('editor');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, MusicTag $musicTag): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('editor');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, MusicTag $musicTag): bool
    {
        return $user->hasRole('editor');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, MusicTag $musicTag): bool
    {
        return $user->hasRole('editor');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, MusicTag $musicTag): bool
    {
        return $user->hasRole('editor');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, MusicTag $musicTag): bool
    {
        return $user->hasRole('editor');
    }
}
