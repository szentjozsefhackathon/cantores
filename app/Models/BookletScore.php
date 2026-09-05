<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One score's place in one booklet.
 *
 * A pivot with opinions: it carries the order, whether the score insists on
 * starting a fresh page, and the settings someone changed by hand to stop a bad
 * line or page break. Those overrides belong to this row and nowhere else — not
 * to the score, which may not even be the booklet owner's, and not to any other
 * booklet the same score appears in.
 *
 * @property int $id
 * @property int $booklet_id
 * @property int $score_id
 * @property int $sequence
 * @property array<string, mixed>|null $settings_override
 * @property bool $start_on_new_page
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \App\Models\Booklet $booklet
 * @property-read \App\Models\Score $score
 *
 * @method static \Database\Factories\BookletScoreFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BookletScore newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BookletScore newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BookletScore query()
 *
 * @mixin \Eloquent
 */
class BookletScore extends Model
{
    /** @use HasFactory<\Database\Factories\BookletScoreFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'booklet_id',
        'score_id',
        'sequence',
        'settings_override',
        'start_on_new_page',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'settings_override' => 'array',
            'start_on_new_page' => 'boolean',
        ];
    }

    public function booklet(): BelongsTo
    {
        return $this->belongsTo(Booklet::class);
    }

    public function score(): BelongsTo
    {
        return $this->belongsTo(Score::class);
    }
}
