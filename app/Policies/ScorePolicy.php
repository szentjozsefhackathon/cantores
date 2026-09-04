<?php

namespace App\Policies;

use App\Models\Score;
use App\Models\User;

class ScorePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user !== null;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Score $score): bool
    {
        return $score->user_id === $user->id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user !== null;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Score $score): bool
    {
        return $score->user_id === $user->id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Score $score): bool
    {
        return $score->user_id === $user->id;
    }

    /**
     * Determine whether a viewer can see this score through the public library.
     *
     * Deliberately separate from view(): every file-serving controller
     * authorizes `view`, so widening that ability would open private files on
     * the authenticated routes too. Guests reach this with a null user.
     */
    public function viewPublic(?User $user, Score $score): bool
    {
        return $score->isPublished();
    }

    /**
     * Determine whether the user can offer this score to the public library.
     *
     * Nomination needs something to publish and a public music to hang it on:
     * a score carries no genre, collection or author of its own, and a private
     * music's metadata must not reach an indexable page.
     */
    public function nominate(User $user, Score $score): bool
    {
        if ($score->user_id !== $user->id) {
            return false;
        }

        if ($score->music_id === null || $score->music?->is_private !== false) {
            return false;
        }

        return $score->content !== null || $score->files()->exists();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Score $score): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Score $score): bool
    {
        return false;
    }
}
