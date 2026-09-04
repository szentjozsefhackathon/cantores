<?php

namespace App\Enums;

/**
 * In what capacity somebody objects to a published score.
 *
 * Asked because the answer decides how a complaint is handled: a rights holder
 * is owed a takedown while the claim is examined, a passer-by is not.
 */
enum ScoreRightsClaimantCapacity: string
{
    case RightsHolder = 'rights_holder';
    case Representative = 'representative';
    case Publisher = 'publisher';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::RightsHolder => __('I hold the rights'),
            self::Representative => __('I represent the rights holder'),
            self::Publisher => __('I act for the publisher'),
            self::Other => __('Something else — I just noticed a problem'),
        };
    }

    /**
     * Whether a claim in this capacity is one the site takes down first and
     * examines afterwards.
     */
    public function isRightsClaim(): bool
    {
        return $this !== self::Other;
    }
}
