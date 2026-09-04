<?php

namespace App\Enums;

/**
 * Where a score stands on its way to the public library.
 *
 * The absence of a publication row is the draft state, so there is no Draft
 * case here — a row exists only once its owner has deliberately nominated the
 * score.
 */
enum ScorePublicationStatus: string
{
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';
    case TakenDown = 'taken_down';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => __('Awaiting review'),
            self::Approved => __('Published'),
            self::Rejected => __('Rejected'),
            self::Withdrawn => __('Withdrawn'),
            self::TakenDown => __('Taken down'),
        };
    }

    /**
     * Whether guests may reach the score through this status. Only one case may.
     */
    public function isPublic(): bool
    {
        return $this === self::Approved;
    }

    /**
     * Whether the owner may nominate again without a reviewer's involvement.
     *
     * A takedown is a post-publication decision made against the owner, so they
     * must not be able to resubmit their way out of it.
     */
    public function isOwnerResubmittable(): bool
    {
        return $this === self::Rejected || $this === self::Withdrawn;
    }

    /**
     * What a guest asking for a non-public score should be told.
     *
     * 410 for a takedown so search engines drop the URL quickly; 404 everywhere
     * else, because 403 would confirm that the score exists.
     */
    public function httpStatusForGuest(): int
    {
        return $this === self::TakenDown ? 410 : 404;
    }
}
