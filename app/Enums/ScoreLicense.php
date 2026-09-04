<?php

namespace App\Enums;

/**
 * The terms a published score is offered under.
 *
 * Two distinct jobs are done by this enum, and a publication records both:
 * the *basis* (why cantores.hu may publish at all) and the *outbound* licence
 * (what the person downloading it may then do). For a Creative Commons or
 * public domain work the two are the same value. For OwnWork and
 * ExplicitPermission they cannot be — "I drew it" and "the publisher said yes"
 * grant nothing to a visitor — so those two carry a separate outbound licence
 * drawn from the redistributable cases below.
 */
enum ScoreLicense: string
{
    case PublicDomain = 'public_domain';
    case Cc0 = 'cc0';
    case CcBy = 'cc_by';
    case CcBySa = 'cc_by_sa';
    case CcByNc = 'cc_by_nc';
    case CcByNcSa = 'cc_by_nc_sa';
    case OwnWork = 'own_work';
    case ExplicitPermission = 'explicit_permission';

    public function label(): string
    {
        return match ($this) {
            self::PublicDomain => __('Public domain'),
            self::Cc0 => __('CC0 — dedicated to the public domain'),
            self::CcBy => __('CC BY — attribution'),
            self::CcBySa => __('CC BY-SA — attribution, share alike'),
            self::CcByNc => __('CC BY-NC — attribution, non-commercial'),
            self::CcByNcSa => __('CC BY-NC-SA — attribution, non-commercial, share alike'),
            self::OwnWork => __('My own work'),
            self::ExplicitPermission => __('I have the rightholder’s written permission'),
        };
    }

    /**
     * The badge text, short enough to sit beside a title in a listing.
     */
    public function shortCode(): string
    {
        return match ($this) {
            self::PublicDomain => 'PD',
            self::Cc0 => 'CC0 1.0',
            self::CcBy => 'CC BY 4.0',
            self::CcBySa => 'CC BY-SA 4.0',
            self::CcByNc => 'CC BY-NC 4.0',
            self::CcByNcSa => 'CC BY-NC-SA 4.0',
            self::OwnWork => __('Own work'),
            self::ExplicitPermission => __('By permission'),
        };
    }

    /**
     * The canonical deed, used for the licence link and the rel="license" header.
     */
    public function deedUrl(): ?string
    {
        return match ($this) {
            self::PublicDomain => 'https://en.wikipedia.org/wiki/Public_domain',
            self::Cc0 => 'https://creativecommons.org/publicdomain/zero/1.0/',
            self::CcBy => 'https://creativecommons.org/licenses/by/4.0/',
            self::CcBySa => 'https://creativecommons.org/licenses/by-sa/4.0/',
            self::CcByNc => 'https://creativecommons.org/licenses/by-nc/4.0/',
            self::CcByNcSa => 'https://creativecommons.org/licenses/by-nc-sa/4.0/',
            self::OwnWork, self::ExplicitPermission => null,
        };
    }

    /**
     * Whether this value can stand as the outbound licence a visitor relies on.
     *
     * OwnWork and ExplicitPermission cannot: they explain why we may publish,
     * not what the downloader may do.
     */
    public function isRedistributable(): bool
    {
        return $this !== self::OwnWork && $this !== self::ExplicitPermission;
    }

    /**
     * Whether the publication must name a separate outbound licence.
     */
    public function requiresOutboundLicense(): bool
    {
        return ! $this->isRedistributable();
    }

    public function requiresAttribution(): bool
    {
        return match ($this) {
            self::PublicDomain, self::Cc0 => false,
            default => true,
        };
    }

    public function allowsCommercialUse(): bool
    {
        return match ($this) {
            self::CcByNc, self::CcByNcSa => false,
            default => true,
        };
    }

    /**
     * Whether the nominator must link the copy they started from.
     *
     * Only the Creative Commons cases: there the licence is a claim about a
     * particular published copy, and the reviewer has no way to check it
     * without the copy. A public domain claim is not asked for a link, because
     * the commonest public domain nomination on this site is an engraving the
     * nominator typed here themselves, which has no source page at all.
     */
    public function requiresSourceUrl(): bool
    {
        return $this !== self::OwnWork
            && $this !== self::PublicDomain
            && $this !== self::ExplicitPermission;
    }

    /**
     * Whether the nominator must affirm that the engraving is free as well.
     *
     * A public domain claim is about the composition. In the EU the engraving
     * is a separate question: a faithful reproduction of an out-of-copyright
     * work carries no new right, but a modern critical edition or typesetting
     * does, for its editor's life plus seventy years. So a public domain
     * nomination is asked one yes-or-no question about the edition rather than
     * a year most nominators cannot look up.
     */
    public function requiresEditionAffirmation(): bool
    {
        return $this === self::PublicDomain;
    }

    /**
     * Whether the nominator must record the permission itself, rather than
     * merely asserting that it exists.
     */
    public function requiresPermissionEvidence(): bool
    {
        return $this === self::ExplicitPermission;
    }

    /**
     * The cases a visitor-facing licence may be set to.
     *
     * @return list<self>
     */
    public static function redistributableCases(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $case): bool => $case->isRedistributable(),
        ));
    }
}
