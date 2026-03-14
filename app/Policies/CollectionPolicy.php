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
        // Collections list is publicly browseable by guests and authenticated users
        return true;
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

        // Private collections can only be viewed by its owner.
        return $user !== null && $collection->user_id === $user->id;
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
     * Determine whether the user can upload/delete a cover image for the collection.
     * Requires the content.edit.verified permission (editor role) or admin.
     */
    public function uploadCover(User $user, Collection $collection): bool
    {
        return $user !== null && ($user->is_admin || $user->hasPermissionTo('content.edit.verified'));
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
