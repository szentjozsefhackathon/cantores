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

    public function score(): BelongsTo
    {
        return $this->belongsTo(Score::class);
    }
}
