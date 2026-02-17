<?php

namespace App\Policies;

use App\Models\Collection;
use App\Models\User;

class CollectionPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(?User $user): bool
    {
        // All authenticated users can view collections (they are community-maintained)
        // Guests can view public collections via individual pages, but not listing
        return $user !== null;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(?User $user, Collection $collection): bool
    {
        // Public collections can be viewed by anyone (including guests)
        if (! $collection->is_private) {
            return true;
        }

        // Private collections can only be viewed by owner or admin
        return $user !== null && ($user->is_admin || $collection->user_id === $user->id);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // All authenticated users can create collections (community-maintained)
        return $user !== null;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Collection $collection): bool
    {
        // Only the owner or admin can update collections
        return $user !== null && ($user->is_admin || $collection->user_id === $user->id);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Collection $collection): bool
    {
        // Only the owner or admin can delete collections
        // Additional checks for music assignments are done in the controller/component
        return $user !== null && ($user->is_admin || $collection->user_id === $user->id);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Collection $collection): bool
    {
        return $user !== null && $user->is_admin;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Collection $collection): bool
    {
        return $user !== null && $user->is_admin;
    }
}
