<?php

namespace App\Policies;

use App\Models\Booklet;
use App\Models\User;

/**
 * A booklet is one person's working document, like a folder: only its owner
 * reads or edits it. What it may contain is a separate question, answered per
 * score by MusicPlanScoreListService on every request.
 */
class BookletPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Booklet $booklet): bool
    {
        return $booklet->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Booklet $booklet): bool
    {
        return $booklet->user_id === $user->id;
    }

    public function delete(User $user, Booklet $booklet): bool
    {
        return $booklet->user_id === $user->id;
    }

    public function restore(User $user, Booklet $booklet): bool
    {
        return false;
    }

    public function forceDelete(User $user, Booklet $booklet): bool
    {
        return false;
    }
}
