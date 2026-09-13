<?php

namespace App\Policies;

use App\Models\Projection;
use App\Models\User;

/**
 * A projection is one person's working document, like a booklet or a folder:
 * only its owner reads or edits it. What it may contain is a separate question,
 * answered per score by MusicPlanScoreListService on every request.
 */
class ProjectionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Projection $projection): bool
    {
        return $projection->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Projection $projection): bool
    {
        return $projection->user_id === $user->id;
    }

    public function delete(User $user, Projection $projection): bool
    {
        return $projection->user_id === $user->id;
    }

    public function restore(User $user, Projection $projection): bool
    {
        return false;
    }

    public function forceDelete(User $user, Projection $projection): bool
    {
        return false;
    }
}
