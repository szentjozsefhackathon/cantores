<?php

namespace App\Models;

use App\MusicUrlLabel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $score_id
 * @property string $url
 * @property \App\MusicUrlLabel|null $label
 * @property string|null $comment
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \App\Models\Score $score
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScoreUrl newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScoreUrl newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScoreUrl query()
 *
 * @mixin \Eloquent
 */
class ScoreUrl extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'score_id',
        'url',
        'label',
        'comment',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'url' => 'encrypted',
            'label' => MusicUrlLabel::class,
        ];
    }

    /**
     * A score link is printed on the public page, so it can carry someone else's
     * work as surely as an uploaded file can. Adding, editing or removing one on a
     * published score re-enters the review queue.
     */
    protected static function booted(): void
    {
        static::saved(function (ScoreUrl $scoreUrl): void {
            if (! $scoreUrl->wasChanged(['url', 'label']) && ! $scoreUrl->wasRecentlyCreated) {
                return;
            }

            app(\App\Services\ScorePublicationWatcher::class)->scoreChanged($scoreUrl->score);
        });

        static::deleted(function (ScoreUrl $scoreUrl): void {
            app(\App\Services\ScorePublicationWatcher::class)->scoreChanged($scoreUrl->score);
        });
    }

    public function score(): BelongsTo
    {
        return $this->belongsTo(Score::class);
    }
}
