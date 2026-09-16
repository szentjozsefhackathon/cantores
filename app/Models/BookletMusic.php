<?php

namespace App\Models;

use App\Contracts\PlanAddedMusic;
use Carbon\CarbonImmutable;
use Database\Factories\BookletMusicFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A music this booklet holds and its plan does not.
 *
 * @see PlanAddedMusic
 *
 * @property int $id
 * @property int $booklet_id
 * @property int $music_id
 * @property int|null $music_plan_slot_plan_id
 * @property int $sequence
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Booklet $booklet
 * @property-read Music $music
 * @property-read MusicPlanSlotPlan|null $slotPlan
 * @property-read Collection<int, BookletScore> $entries
 *
 * @method static BookletMusicFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BookletMusic newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BookletMusic newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BookletMusic query()
 *
 * @mixin \Eloquent
 */
class BookletMusic extends Model implements PlanAddedMusic
{
    /** @use HasFactory<BookletMusicFactory> */
    use HasFactory;

    /**
     * Named outright: "music" is uncountable to the pluralizer.
     */
    protected $table = 'booklet_musics';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'booklet_id',
        'music_id',
        'music_plan_slot_plan_id',
        'sequence',
    ];

    public function booklet(): BelongsTo
    {
        return $this->belongsTo(Booklet::class);
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
        return $this->hasMany(BookletScore::class, 'added_music_id')->orderBy('sequence');
    }
}
