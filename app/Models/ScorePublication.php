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
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \App\Models\Score $score
 * @property-read \App\Models\User|null $reviewer
 * @property-read \App\Models\User|null $submitter
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
     * A digest of the bytes this publication was approved against.
     *
     * ScoreFileUploader::replace() deliberately keeps a file's row and swaps the
     * bytes behind it, which would otherwise let an owner get a public domain
     * engraving approved and then serve something else from the same URL. The
     * checksums are sorted so the digest depends on the set of published bytes
     * and not on the order they come back in.
     */
    public function computeFingerprint(): string
    {
        // Queried rather than read off the score's loaded relation: this runs
        // from a saved() hook, where an in-memory `files` collection may still
        // hold the bytes that were just replaced.
        $checksums = ScoreFile::query()
            ->where('score_id', $this->score_id)
            ->where('is_published', true)
            ->orderBy('id')
            ->pluck('checksum')
            ->sort()
            ->values()
            ->all();

        return hash('sha256', implode('|', $checksums));
    }

    /**
     * Whether the published bytes are still the ones a reviewer approved.
     */
    public function matchesApprovedFingerprint(): bool
    {
        return $this->approved_fingerprint === $this->computeFingerprint();
    }

    /**
     * Scope to nominations waiting for a reviewer.
     *
     * @param  Builder<ScorePublication>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->where('status', ScorePublicationStatus::Submitted);
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
