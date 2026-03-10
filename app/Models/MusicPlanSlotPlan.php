<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $music_plan_id
 * @property int $music_plan_slot_id
 * @property int $sequence
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\MusicPlanSlotAssignment> $musicAssignments
 * @property-read int|null $music_assignments_count
 * @property-read \App\Models\MusicPlan $musicPlan
 * @property-read \App\Models\MusicPlanSlot $musicPlanSlot
 * @method static \Database\Factories\MusicPlanSlotPlanFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicPlanSlotPlan newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicPlanSlotPlan newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicPlanSlotPlan query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicPlanSlotPlan whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicPlanSlotPlan whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicPlanSlotPlan whereMusicPlanId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicPlanSlotPlan whereMusicPlanSlotId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicPlanSlotPlan whereSequence($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicPlanSlotPlan whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class MusicPlanSlotPlan extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'music_plan_slot_plan';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'music_plan_id',
        'music_plan_slot_id',
        'sequence',
    ];

    /**
     * Get the music plan that owns this pivot.
     */
    public function musicPlan(): BelongsTo
    {
        return $this->belongsTo(MusicPlan::class);
    }

    /**
     * Get the music plan slot that owns this pivot.
     */
    public function musicPlanSlot(): BelongsTo
    {
        return $this->belongsTo(MusicPlanSlot::class);
    }

    /**
     * Get the music assignments for this pivot.
     */
    public function musicAssignments(): HasMany
    {
        return $this->hasMany(MusicPlanSlotAssignment::class);
    }
}
