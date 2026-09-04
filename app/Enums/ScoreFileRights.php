<?php

namespace App\Enums;

/**
 * What the uploader declares about their right to keep this file here.
 *
 * This is a storage claim, not a licence. It answers "may cantores.hu hold
 * this?" — never "may a visitor take it away?", which is what
 * \App\Enums\ScoreLicense records on the score's publication.
 *
 * The two compose rather than overlap: a file is only ever served publicly when
 * its own rights permit it (see mayBePublished()) *and* the score it belongs to
 * carries an approved publication. Note that these declarations were made while
 * the site published nothing, so they must never be read as consent to publish
 * — every publication is a fresh, deliberate act by the owner.
 */
enum ScoreFileRights: string
{
    case OwnWork = 'own_work';
    case PublicDomain = 'public_domain';
    case LicensedCopy = 'licensed_copy';
    case PermissionHeld = 'permission_held';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::OwnWork => __('My own work'),
            self::PublicDomain => __('Public domain'),
            self::LicensedCopy => __('A copy I bought or licensed'),
            self::PermissionHeld => __('I have the rightholder’s permission'),
            self::Unknown => __('Not sure'),
        };
    }

    /**
     * Whether a file carrying this declaration may ever be served publicly.
     *
     * A bought copy and an unsure provenance can be stored and shared over a
     * secret link, but never published.
     */
    public function mayBePublished(): bool
    {
        return $this === self::OwnWork
            || $this === self::PublicDomain
            || $this === self::PermissionHeld;
    }
}
