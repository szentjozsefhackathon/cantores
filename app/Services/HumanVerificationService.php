<?php

namespace App\Services;

use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Carbon;

/**
 * Remembers that this visitor has proved they are a person.
 *
 * Lending links are bearer URLs: whoever holds one may read what it opens. That
 * is what makes them useful between musicians, and also what makes them worth
 * harvesting — a crawler that gets hold of one can walk a whole folder of
 * someone else's work. Borrowing is meant for people, so a guest passes a
 * Turnstile challenge once and the answer is remembered in their session.
 *
 * Signed-in readers are never asked: registration already went through Turnstile,
 * and a loan opened while signed in is recorded against a real account.
 */
class HumanVerificationService
{
    /**
     * The session key holding when the challenge was passed.
     */
    private const SESSION_KEY = 'human_verified_at';

    /**
     * How long one passed challenge is honoured.
     *
     * Long enough that a reader working through a lent folder is asked once, short
     * enough that a solved challenge is not a season ticket for whoever stole the
     * cookie.
     */
    private const TTL_HOURS = 24;

    public function __construct(private Session $session) {}

    /**
     * Whether this visitor has passed a challenge that is still good.
     */
    public function isVerified(): bool
    {
        $verifiedAt = $this->session->get(self::SESSION_KEY);

        if (! is_numeric($verifiedAt)) {
            return false;
        }

        return Carbon::createFromTimestamp((int) $verifiedAt)
            ->addHours(self::TTL_HOURS)
            ->isFuture();
    }

    /**
     * Record that the challenge was passed.
     */
    public function markVerified(): void
    {
        $this->session->put(self::SESSION_KEY, Carbon::now()->getTimestamp());
    }
}
