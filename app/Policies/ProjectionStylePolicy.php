<?php

namespace App\Policies;

use App\Models\ProjectionStyle;
use App\Models\User;

/**
 * A style is one cantor's description of the screens they serve, so only they
 * may see it, use it on a deck or change it.
 */
class ProjectionStylePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ProjectionStyle $style): bool
    {
        return $style->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, ProjectionStyle $style): bool
    {
        return $style->user_id === $user->id;
    }

    public function delete(User $user, ProjectionStyle $style): bool
    {
        return $style->user_id === $user->id;
    }

    public function restore(User $user, ProjectionStyle $style): bool
    {
        return false;
    }

    public function forceDelete(User $user, ProjectionStyle $style): bool
    {
        return false;
    }
}
