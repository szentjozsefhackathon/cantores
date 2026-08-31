<?php

namespace App\Enums;

/**
 * What the uploader declares about their right to keep this file here.
 *
 * cantores.hu never publishes uploaded files, so this is not a licence check —
 * it is the record that makes "I hold a purchased edition privately" reviewable
 * later, and separable from sharing one.
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
}
