<?php

namespace App\Policies;

use App\Models\Screen;
use App\Models\User;

/**
 * A screen belongs to whoever is facing the room with it.
 *
 * Nothing is paired and no token is minted, exactly as for a presentation: both
 * devices were made the same person by the QR sign-in, so the only question left
 * is whether this person is that person. What may be *put on* the screen is a
 * separate question, asked of the projection.
 *
 * The endpoints turn a refusal here into a 404 rather than a 403: a screen
 * somebody else is facing a room with is not a thing this account may know
 * exists.
 */
class ScreenPolicy
{
    public function view(User $user, Screen $screen): bool
    {
        return $screen->user_id === $user->id;
    }

    public function update(User $user, Screen $screen): bool
    {
        return $screen->user_id === $user->id;
    }
}
