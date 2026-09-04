<?php

namespace App\Models;

use App\Enums\ScoreLicense;
use App\Enums\ScorePublicationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * One deliberate offer of a score to the public library.
 *
 * A row exists only once its owner has nominated the score, so the absence of a
 * row is the draft state and the common case. The licence, the provenance and
 * the reviewer's decision live here rather than on `scores` because this is a
 * legal record: it is audited, it is read on every public request, and it must
 * be legible on its own long after the score has changed.
 *
 * The audit trail is the point of `implements Auditable`. Note that `audits`
 * rows are not cascaded when the audited model is deleted, so the record of a
 * takedown outlives the score it was made against. That is deliberate.
 *
 * @property int $id
 * @property int $score_id
 * @property \App\Enums\ScorePublicationStatus $status
 * @property \App\Enums\ScoreLicense $license
 * @property \App\Enums\ScoreLicense|null $outbound_license
 * @property string|null $license_version
 * @property string|null $attribution_line
 * @property string|null $source_url
 * @property string|null $source_title
 * @property int|null $composer_death_year
 * @property bool $edition_is_free
 * @property string|null $rights_note
 * @property string|null $permission_evidence
 * @property int|null $submitted_by
 * @property \Carbon\CarbonImmutable|null $submitted_at
 * @property int|null $reviewer_id
 * @property \Carbon\CarbonImmutable|null $reviewed_at
 * @property string|null $review_notes
 * @property bool $self_approved
 * @property \Carbon\CarbonImmutable|null $published_at
 * @property \Carbon\CarbonImmutable|null $unpublished_at
 * @property string|null $takedown_reason
 * @property string|null $approved_fingerprint
 * @property int|null $approved_version_id
 * @property int|null $submitted_version_id
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \App\Models\Score $score
 * @property-read \App\Models\User|null $reviewer
 * @property-read \App\Models\User|null $submitter
 * @property-read \App\Models\ScoreVersion|null $approvedVersion
 * @property-read \App\Models\ScoreVersion|null $submittedVersion
 *
 * @method static \Database\Factories\ScorePublicationFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScorePublication approved()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScorePublication newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScorePublication newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScorePublication pending()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScorePublication query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScorePublication takenDown()
 *
 * @mixin \Eloquent
 */
class ScorePublication extends Model implements Auditable
{
    /** @use HasFactory<\Database\Factories\ScorePublicationFactory> */
    use HasFactory;

    use \OwenIt\Auditing\Auditable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'score_id',
        'status',
        'license',
        'outbound_license',
        'license_version',
        'attribution_line',
        'source_url',
        'source_title',
        'composer_death_year',
        'edition_is_free',
        'rights_note',
        'permission_evidence',
        'submitted_by',
        'submitted_at',
        'reviewer_id',
        'reviewed_at',
        'review_notes',
        'self_approved',
        'published_at',
        'unpublished_at',
        'takedown_reason',
        'approved_fingerprint',
        'approved_version_id',
        'submitted_version_id',
    ];

    /**
     * The columns whose changes are worth a permanent record. Everything here
     * is either the offer itself or the decision made about it.
     *
     * @var list<string>
     */
    protected $auditInclude = [
        'status',
        'license',
        'outbound_license',
        'license_version',
        'attribution_line',
        'source_url',
        'source_title',
        'composer_death_year',
        'edition_is_free',
        'rights_note',
        'permission_evidence',
        'reviewer_id',
        'review_notes',
        'self_approved',
        'published_at',
        'unpublished_at',
        'takedown_reason',
        'approved_version_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ScorePublicationStatus::class,
            'license' => ScoreLicense::class,
            'outbound_license' => ScoreLicense::class,
            'composer_death_year' => 'integer',
            'edition_is_free' => 'boolean',
            'self_approved' => 'boolean',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'published_at' => 'datetime',
            'unpublished_at' => 'datetime',
        ];
    }

    public function score(): BelongsTo
    {
        return $this->belongsTo(Score::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /**
     * The version the public is reading.
     */
    public function approvedVersion(): BelongsTo
    {
        return $this->belongsTo(ScoreVersion::class, 'approved_version_id');
    }

    /**
     * The version waiting in the review queue, which is not necessarily the one the
     * public is reading — that is the point of versioning the published surface.
     */
    public function submittedVersion(): BelongsTo
    {
        return $this->belongsTo(ScoreVersion::class, 'submitted_version_id');
    }

    /**
     * Whether a correction is queued behind what the public is currently reading.
     */
    public function hasUnpublishedChanges(): bool
    {
        return $this->approved_version_id !== null
            && $this->submitted_version_id !== null
            && $this->approved_version_id !== $this->submitted_version_id;
    }

    public function isPublic(): bool
    {
        return $this->status->isPublic();
    }

    /**
     * The licence a visitor actually relies on: the outbound one where the
     * basis does not grant anything by itself, otherwise the basis.
     */
    public function effectiveLicense(): ScoreLicense
    {
        return $this->outbound_license ?? $this->license;
    }

    /**
     * A digest of everything in this score that a copyright review had to judge.
     *
     * Review exists for copyright, so the digest covers anything that can carry
     * someone else's work: the typed source, its format, the score links the public
     * page prints, and the checksums of the published files. Render settings are
     * deliberately outside it — a transpose or a staff size changes how the same
     * notes look and cannot introduce anyone else's material.
     *
     * ScoreFileUploader supersedes rather than mutates a replaced file, but the
     * checksums are still what identifies the published bytes, and they are sorted
     * so the digest depends on the set and not on the order it comes back in.
     */
    public function computeFingerprint(): string
    {
        // Queried rather than read off the score's loaded relation: this runs
        // from a saved() hook, where an in-memory relation may still hold the
        // values that were just replaced.
        $score = Score::query()->find($this->score_id);

        if ($score === null) {
            return self::fingerprintOf(null, null, [], []);
        }

        $checksums = ScoreFile::query()
            ->where('score_id', $this->score_id)
            ->where('is_published', true)
            ->whereNull('superseded_at')
            ->orderBy('id')
            ->pluck('checksum')
            ->all();

        return self::fingerprintOf(
            $score->content,
            $score->format?->value,
            ScoreUrl::query()
                ->where('score_id', $this->score_id)
                ->orderBy('id')
                ->get()
                ->map(fn (ScoreUrl $url): array => ['url' => $url->url, 'label' => $url->label?->value])
                ->all(),
            $checksums,
        );
    }

    /**
     * The one place the digest's shape is decided, so a version and a live score
     * are always hashed the same way.
     *
     * @param  array<int, array<string, mixed>>  $urls
     * @param  array<int, string|null>  $checksums
     */
    public static function fingerprintOf(?string $content, ?string $format, array $urls, array $checksums): string
    {
        $urlDigest = collect($urls)
            ->map(fn (array $url): string => ($url['url'] ?? '').'|'.($url['label'] ?? ''))
            ->sort()
            ->values()
            ->implode(',');

        $checksumDigest = collect($checksums)->filter()->sort()->values()->implode('|');

        return hash('sha256', implode("\n", [
            (string) $content,
            (string) $format,
            $urlDigest,
            $checksumDigest,
        ]));
    }

    /**
     * Whether the published bytes are still the ones a reviewer approved.
     */
    public function matchesApprovedFingerprint(): bool
    {
        return $this->approved_fingerprint === $this->computeFingerprint();
    }

    /**
     * Scope to everything waiting for a reviewer.
     *
     * Two shapes: a nomination not yet published, and a change queued behind a
     * score that already is. The second one only exists because the public keeps
     * reading the approved version while a correction waits — without it, that
     * correction would sit in nobody's queue.
     *
     * @param  Builder<ScorePublication>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->where(function (Builder $query): void {
            $query->where('status', ScorePublicationStatus::Submitted)
                ->orWhere(function (Builder $query): void {
                    $query->where('status', ScorePublicationStatus::Approved)
                        ->whereNotNull('submitted_version_id')
                        ->whereNotNull('approved_version_id')
                        ->whereColumn('submitted_version_id', '!=', 'approved_version_id');
                });
        });
    }

    /**
     * @param  Builder<ScorePublication>  $query
     */
    public function scopeApproved(Builder $query): void
    {
        $query->where('status', ScorePublicationStatus::Approved);
    }

    /**
     * @param  Builder<ScorePublication>  $query
     */
    public function scopeTakenDown(Builder $query): void
    {
        $query->where('status', ScorePublicationStatus::TakenDown);
    }
}
