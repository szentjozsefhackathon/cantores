<?php

namespace App\Policies;

use App\Models\ScorePublication;
use App\Models\User;

/**
 * Who may act on a nomination.
 *
 * The self-approval bar is the load-bearing rule and it lives here rather than
 * in the review component, so no other caller can route around it.
 */
class ScorePublicationPolicy
{
    public const REVIEW_PERMISSION = 'scores.publish.review';

    public function viewAny(User $user): bool
    {
        return $user->can(self::REVIEW_PERMISSION);
    }

    public function view(User $user, ScorePublication $publication): bool
    {
        return $user->can(self::REVIEW_PERMISSION) || $this->ownsScore($user, $publication);
    }

    /**
     * A reviewer may not approve a score they own or nominated. An admin can
     * override this, but the override is recorded on the row as `self_approved`
     * rather than being silently permitted here.
     */
    public function approve(User $user, ScorePublication $publication): bool
    {
        if (! $user->can(self::REVIEW_PERMISSION)) {
            return false;
        }

        return ! $this->hasStakeIn($user, $publication);
    }

    /**
     * The escape hatch for a site with a single active editor. Admins only, and
     * the service records that it happened.
     */
    public function selfApprove(User $user, ScorePublication $publication): bool
    {
        return $user->isAdmin() && $user->can(self::REVIEW_PERMISSION);
    }

    public function reject(User $user, ScorePublication $publication): bool
    {
        return $user->can(self::REVIEW_PERMISSION) && ! $this->hasStakeIn($user, $publication);
    }

    /**
     * The same escape hatch as {@see selfApprove()}, for the other side of the
     * decision: a single active admin must be able to turn their own nomination
     * down too, not just wave it through.
     */
    public function selfReject(User $user, ScorePublication $publication): bool
    {
        return $user->isAdmin() && $user->can(self::REVIEW_PERMISSION);
    }

    public function takeDown(User $user, ScorePublication $publication): bool
    {
        return $user->can(self::REVIEW_PERMISSION);
    }

    /**
     * Answering a rights complaint is a reviewer's job, whoever filed it.
     */
    public function handleReports(User $user, ScorePublication $publication): bool
    {
        return $user->can(self::REVIEW_PERMISSION);
    }

    /**
     * Moving a score out of `taken_down` is a reviewer's decision alone.
     */
    public function restore(User $user, ScorePublication $publication): bool
    {
        return $user->can(self::REVIEW_PERMISSION);
    }

    public function withdraw(User $user, ScorePublication $publication): bool
    {
        return $this->ownsScore($user, $publication) || $user->can(self::REVIEW_PERMISSION);
    }

    private function ownsScore(User $user, ScorePublication $publication): bool
    {
        return $publication->score->user_id === $user->id;
    }

    private function hasStakeIn(User $user, ScorePublication $publication): bool
    {
        return $this->ownsScore($user, $publication)
            || $publication->submitted_by === $user->id;
    }
}
