<?php

namespace App\Policies;

use App\Models\MusicPlan;
use App\Models\MusicPlanSlot;
use App\Models\User;

class MusicPlanSlotPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // All authenticated users can view slots (global slots are public)
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, MusicPlanSlot $musicPlanSlot): bool
    {
        // Global slots are viewable by all
        if (! $musicPlanSlot->is_custom) {
            return true;
        }

        // Custom slots are viewable only by the owner
        return $musicPlanSlot->user_id === $user->id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, ?MusicPlan $musicPlan = null): bool
    {
        // Users can create custom slots only in plans they own
        if ($musicPlan) {
            return $musicPlan->user_id === $user->id;
        }

        // Only admins can create global slots
        return $user->is_admin;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, MusicPlanSlot $musicPlanSlot): bool
    {
        // Global slots can only be updated by admins
        if (! $musicPlanSlot->is_custom) {
            return $user->is_admin;
        }

        // Custom slots can be updated by the owner
        return $musicPlanSlot->user_id === $user->id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, MusicPlanSlot $musicPlanSlot): bool
    {
        // Global slots can only be deleted by admins
        if (! $musicPlanSlot->is_custom) {
            return $user->is_admin;
        }

        // Custom slots can be deleted by the owner
        return $musicPlanSlot->user_id === $user->id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, MusicPlanSlot $musicPlanSlot): bool
    {
        // Only admins can restore deleted slots
        return $user->is_admin;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, MusicPlanSlot $musicPlanSlot): bool
    {
        // Only admins can permanently delete slots
        return $user->is_admin;
    }
}
