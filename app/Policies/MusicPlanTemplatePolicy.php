<?php

namespace App\Policies;

use App\Models\MusicPlanTemplate;
use App\Models\User;

class MusicPlanTemplatePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->is_admin;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, MusicPlanTemplate $musicPlanTemplate): bool
    {
        return $user->is_admin;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->is_admin;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, MusicPlanTemplate $musicPlanTemplate): bool
    {
        return $user->is_admin;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, MusicPlanTemplate $musicPlanTemplate): bool
    {
        return $user->is_admin;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, MusicPlanTemplate $musicPlanTemplate): bool
    {
        return $user->is_admin;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, MusicPlanTemplate $musicPlanTemplate): bool
    {
        return $user->is_admin;
    }
}
