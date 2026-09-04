<?php

namespace App\Support;

use App\Enums\ScoreLicense;
use Illuminate\Validation\Rule;

/**
 * The validation a nomination must pass, which depends on the licence claimed.
 *
 * Kept out of the Livewire component so the same rules back the nomination
 * form, the review screen and their tests.
 *
 * The list of *required* fields is deliberately short. Every extra mandatory
 * box is a score that never gets offered, and a reviewer looks at each
 * nomination anyway: anything the nominator cannot state with certainty is
 * better left blank than guessed at.
 */
class ScorePublicationRules
{
    /**
     * The earliest year a nomination may claim. Guards against typos rather
     * than expressing a real lower bound on notation.
     */
    public const EARLIEST_YEAR = 1000;

    /**
     * How old an edition must be before a nominator may call the engraving
     * itself free.
     *
     * Seventy years is the term Hungarian copyright runs for after an author's
     * death (Szjt. § 31), the editor of an edition being an author like any
     * other. Publication year is not death year, so this is a rule of thumb and
     * not a guarantee — which is why the answer is an affirmation a reviewer
     * weighs, not a computed permission.
     */
    public const EDITION_FREE_AFTER_YEARS = 70;

    /**
     * The publication year at or before which an edition may be affirmed free.
     */
    public static function editionFreeBefore(): int
    {
        return (int) date('Y') - self::EDITION_FREE_AFTER_YEARS;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function for(?ScoreLicense $license): array
    {
        $currentYear = (int) date('Y');

        $rules = [
            'license' => ['required', Rule::enum(ScoreLicense::class)],
            'attribution_line' => ['nullable', 'string', 'max:255'],
            'source_title' => ['nullable', 'string', 'max:255'],
            'rights_note' => ['nullable', 'string', 'max:2000'],
            'composer_death_year' => ['nullable', 'integer', 'min:'.self::EARLIEST_YEAR, 'max:'.$currentYear],
            'edition_is_free' => ['boolean'],
            'license_version' => ['nullable', 'string', 'max:8'],
            'outbound_license' => ['nullable', Rule::enum(ScoreLicense::class)],
            'source_url' => ['nullable', 'url:http,https', 'max:2000'],
            'permission_evidence' => ['nullable', 'string', 'max:5000'],
        ];

        if (! $license instanceof ScoreLicense) {
            return $rules;
        }

        if ($license->requiresOutboundLicense()) {
            // "I drew it" grants a visitor nothing, so the offer has to name a
            // licence the visitor can actually rely on.
            $rules['outbound_license'] = [
                'required',
                Rule::enum(ScoreLicense::class)->only(ScoreLicense::redistributableCases()),
            ];
        }

        if ($license->requiresSourceUrl()) {
            $rules['source_url'] = ['required', 'url:http,https', 'max:2000'];
        }

        if ($license->requiresEditionAffirmation()) {
            // The one thing a public domain nomination must answer: the music
            // being old says nothing about who owns this particular engraving.
            $rules['edition_is_free'] = ['accepted'];
        }

        if ($license->requiresPermissionEvidence()) {
            $rules['permission_evidence'] = ['required', 'string', 'min:20', 'max:5000'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'outbound_license.required' => __('Choose the licence you are releasing this under, so people know what they may do with it.'),
            'source_url.required' => __('Say where this copy came from.'),
            'edition_is_free.accepted' => __('Confirm that the engraving is free as well — a recent edition of an old work is still protected.'),
            'permission_evidence.required' => __('Record the permission itself: who gave it, when, and in what words.'),
            'permission_evidence.min' => __('Please quote the permission rather than summarising it.'),
        ];
    }
}
