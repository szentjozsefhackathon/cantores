<?php

namespace App\Models;

use App\Contracts\PlanAddedMusic;
use Carbon\CarbonImmutable;
use Database\Factories\ProjectionMusicFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A music this deck holds and its plan does not.
 *
 * @see PlanAddedMusic
 *
 * @property int $id
 * @property int $projection_id
 * @property int $music_id
 * @property int|null $music_plan_slot_plan_id
 * @property int $sequence
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Projection $projection
 * @property-read Music $music
 * @property-read MusicPlanSlotPlan|null $slotPlan
 * @property-read Collection<int, ProjectionSlide> $entries
 *
 * @method static ProjectionMusicFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectionMusic newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectionMusic newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectionMusic query()
 *
 * @mixin \Eloquent
 */
class ProjectionMusic extends Model implements PlanAddedMusic
{
    /** @use HasFactory<ProjectionMusicFactory> */
    use HasFactory;

    /**
     * Named outright: "music" is uncountable to the pluralizer.
     */
    protected $table = 'projection_musics';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'projection_id',
        'music_id',
        'music_plan_slot_plan_id',
        'sequence',
    ];

    public function projection(): BelongsTo
    {
        return $this->belongsTo(Projection::class);
    }

    public function music(): BelongsTo
    {
        return $this->belongsTo(Music::class);
    }

    public function slotPlan(): BelongsTo
    {
        return $this->belongsTo(MusicPlanSlotPlan::class, 'music_plan_slot_plan_id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(ProjectionSlide::class, 'added_music_id')->orderBy('sequence');
    }
}
