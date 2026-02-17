<?php

namespace App\Policies;

use App\Models\Author;
use App\Models\User;

class AuthorPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // All authenticated users can view authors (they are community-maintained)
        return $user !== null;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Author $author): bool
    {
        // Public authors can be viewed by any authenticated user
        if (! $author->is_private) {
            return $user !== null;
        }

        // Private authors can only be viewed by owner or admin
        return $user !== null && ($user->is_admin || $author->user_id === $user->id);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // All authenticated users can create authors (community-maintained)
        return $user !== null;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Author $author): bool
    {
        // Only the owner or admin can update authors
        return $user !== null && ($user->is_admin || $author->user_id === $user->id);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Author $author): bool
    {
        // Only the owner or admin can delete authors
        // Additional checks for music assignments are done in the controller/component
        return $user !== null && ($user->is_admin || $author->user_id === $user->id);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Author $author): bool
    {
        return $user !== null && $user->is_admin;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Author $author): bool
    {
        return $user !== null && $user->is_admin;
    }
}
