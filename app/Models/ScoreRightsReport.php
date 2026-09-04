<?php

namespace App\Models;

use App\Enums\ScoreRightsClaimantCapacity;
use App\Enums\ScoreRightsReportStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A complaint that a published score should not be public.
 *
 * Filed from the score's own page rather than through the contact form, so the
 * score being objected to is recorded by the site rather than described by the
 * person objecting. Kept as its own row because the promise made on the rights
 * page is that every report is recorded and answered by a person.
 *
 * @property int $id
 * @property int $score_id
 * @property int|null $score_publication_id
 * @property \App\Enums\ScoreRightsReportStatus $status
 * @property \App\Enums\ScoreRightsClaimantCapacity $capacity
 * @property string $claim
 * @property int|null $reporter_id
 * @property string $reporter_name
 * @property string $reporter_email
 * @property int|null $handled_by
 * @property \Carbon\CarbonImmutable|null $handled_at
 * @property string|null $resolution_notes
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \App\Models\Score $score
 * @property-read \App\Models\ScorePublication|null $publication
 * @property-read \App\Models\User|null $reporter
 * @property-read \App\Models\User|null $handler
 *
 * @method static \Database\Factories\ScoreRightsReportFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScoreRightsReport newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScoreRightsReport newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScoreRightsReport open()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScoreRightsReport query()
 *
 * @mixin \Eloquent
 */
class ScoreRightsReport extends Model
{
    /** @use HasFactory<\Database\Factories\ScoreRightsReportFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'score_id',
        'score_publication_id',
        'status',
        'capacity',
        'claim',
        'reporter_id',
        'reporter_name',
        'reporter_email',
        'handled_by',
        'handled_at',
        'resolution_notes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ScoreRightsReportStatus::class,
            'capacity' => ScoreRightsClaimantCapacity::class,
            'handled_at' => 'datetime',
        ];
    }

    public function score(): BelongsTo
    {
        return $this->belongsTo(Score::class);
    }

    public function publication(): BelongsTo
    {
        return $this->belongsTo(ScorePublication::class, 'score_publication_id');
    }

    /**
     * The account behind the report, when the reporter happened to be logged in.
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', ScoreRightsReportStatus::Open);
    }
}
