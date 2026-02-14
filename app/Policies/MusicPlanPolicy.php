<?php

namespace App\Policies;

use App\Models\MusicPlan;
use App\Models\User;

class MusicPlanPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // Users can view their own music plans (handled via query scopes)
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(?User $user, MusicPlan $musicPlan): bool
    {
        // Published music plans can be viewed by anyone
        if ($musicPlan->is_published) {
            return true;
        }

        // Non-published plans can only be viewed by the owner
        return $user && $user->id === $musicPlan->user_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Any authenticated user can create a music plan
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, MusicPlan $musicPlan): bool
    {
        // Only the owner can update their own music plan
        return $user->id === $musicPlan->user_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, MusicPlan $musicPlan): bool
    {
        // Only the owner can delete their own music plan
        return $user->id === $musicPlan->user_id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, MusicPlan $musicPlan): bool
    {
        // Only the owner can restore their own music plan
        return $user->id === $musicPlan->user_id;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, MusicPlan $musicPlan): bool
    {
        // Only the owner can force delete their own music plan
        return $user->id === $musicPlan->user_id;
    }
}
