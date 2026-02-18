<?php

namespace App\Policies;

use App\Models\Music;
use App\Models\User;

class MusicPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(?User $user): bool
    {
        // All authenticated users can view music (they are community-maintained)
        // Guests can view public music via individual pages, but not listing
        return $user !== null;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(?User $user, Music $music): bool
    {
        // Public music can be viewed by anyone (including guests)
        if (! $music->is_private) {
            return true;
        }

        // Private music can only be viewed by owner or admin
        return $user !== null && ($user->is_admin || $music->user_id === $user->id);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Any authenticated user can create music
        return $user !== null;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Music $music): bool
    {
        return $user !== null && (
            $user->hasPermissionTo('content.edit.published') ||
            ($user->hasPermissionTo('content.edit.own') && $music->user_id === $user->id)
        );
    }

    public function updateVerified(User $user, Music $music): bool
    {
        return $user !== null && $user->hasPermissionTo('content.edit.verified');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Music $music): bool
    {
        // Only users with 'content.edit.verified' can delete verified music
        if ($music->is_verified) {
            return $user !== null && $user->hasPermissionTo('content.edit.verified');
        }

        return $user !== null && (
            $user->hasPermissionTo('content.edit.published') ||
            ($user->hasPermissionTo('content.edit.own') && $music->user_id === $user->id)
        );
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

    public function merge(Music $target, Music $source, User $user): bool
    {
        // Only the owner of both or admin can merge music
        return $user !== null && ($user->is_admin || ($target->user_id === $user->id && $source->user_id === $user->id));
    }

    public function mergeAny(User $user): bool
    {
        // Only admin can merge music
        return $user !== null && $user->hasPermissionTo('content.edit.published');
    }
}
