<?php

namespace App\Policies;

use App\Models\Music;
use App\Models\User;

class MusicPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // All authenticated users can view music (they are community-maintained)
        return $user !== null;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Music $music): bool
    {
        // Public music can be viewed by any authenticated user
        if (! $music->is_private) {
            return $user !== null;
        }

        // Private music can only be viewed by owner or admin
        return $user !== null && ($user->is_admin || $music->user_id === $user->id);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // All authenticated users can create music (community-maintained)
        return $user !== null;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Music $music): bool
    {
        // Only the owner or admin can update music
        return $user !== null && ($user->is_admin || $music->user_id === $user->id);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Music $music): bool
    {
        // Only the owner or admin can delete music
        // Additional checks for assignments are done in the controller/component
        return $user !== null && ($user->is_admin || $music->user_id === $user->id);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Music $music): bool
    {
        return $user !== null && $user->is_admin;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Music $music): bool
    {
        return $user !== null && $user->is_admin;
    }
}
