<?php

namespace App\Policies;

use App\Models\Presentation;
use App\Models\User;

/**
 * A presentation belongs to whoever is showing it.
 *
 * Nothing is paired and no token is minted, because step two of the Sunday
 * already did all of it: the laptop was signed in from the phone, so both
 * devices are the same person and the only question left is whether this person
 * is that person. What the deck may *contain* is a separate question, answered
 * per score by MusicPlanScoreListService on every payload read.
 *
 * The endpoints turn a refusal here into a 404 rather than a 403: a presentation
 * somebody else is running is not a thing this account may know exists.
 */
class PresentationPolicy
{
    public function view(User $user, Presentation $presentation): bool
    {
        return $presentation->user_id === $user->id;
    }

    public function update(User $user, Presentation $presentation): bool
    {
        return $presentation->user_id === $user->id;
    }
}
