<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

/**
 * Class MusicPlanSlotAssignment
 *
 * Represents an assignment of a music track to a specific slot within a music plan.
 *
 * @property int $id
 * @property int $music_id
 * @property string|null $notes
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property int $music_sequence
 * @property int $music_plan_slot_plan_id
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\MusicAssignmentFlag> $flags
 * @property-read int|null $flags_count
 * @property-read string $scope_label
 * @property-read \App\Models\Music $music
 * @property-read \App\Models\MusicPlan|null $musicPlan
 * @property-read \App\Models\MusicPlanSlot|null $musicPlanSlot
 * @property-read \App\Models\MusicPlanSlotPlan $musicPlanSlotPlan
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\MusicPlanSlotAssignmentScope> $scopes
 * @property-read int|null $scopes_count
 *
 * @method static \Database\Factories\MusicPlanSlotAssignmentFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicPlanSlotAssignment forMusicPlan(\App\Models\MusicPlan $musicPlan)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicPlanSlotAssignment forSlot(\App\Models\MusicPlanSlot $slot)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicPlanSlotAssignment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicPlanSlotAssignment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicPlanSlotAssignment query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicPlanSlotAssignment whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicPlanSlotAssignment whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicPlanSlotAssignment whereMusicId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicPlanSlotAssignment whereMusicPlanSlotPlanId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicPlanSlotAssignment whereMusicSequence($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicPlanSlotAssignment whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicPlanSlotAssignment whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class MusicPlanSlotAssignment extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     * music_plan_slot_plan_id identifies the specific slot occurrence within the music plan,
     * while music_sequence identifies the position of the music track within that slot occurrence.
     *
     * @var list<string>
     */
    protected $fillable = [
        'music_plan_slot_plan_id',
        'music_id',
        'music_sequence',
        'notes',
    ];

    /**
     * Get the casts array.
     *
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [];
    }

    /**
     * Get the formatted scope label.
     */
    public function getScopeLabelAttribute(): string
    {
        if ($this->scopes->isEmpty()) {
            return '';
        }

        $grouped = $this->scopes->groupBy(fn ($scope) => $scope->scope_type->value);

        $parts = [];
        foreach ($grouped as $type => $scopes) {
            $numbers = $scopes->pluck('scope_number')->filter()->sort()->unique()->values();
            $typeLabel = $scopes->first()->scope_type->label();
            if ($numbers->isEmpty()) {
                $parts[] = $typeLabel;
            } else {
                $parts[] = $typeLabel.' '.$numbers->join(',');
            }
        }

        return implode(', ', $parts);
    }

    /**
     * Get the music plan for this assignment (via MusicPlanSlotPlan).
     */
    public function musicPlan(): HasOneThrough
    {
        return $this->hasOneThrough(
            MusicPlan::class,
            MusicPlanSlotPlan::class,
            'id',                      // PK on MusicPlanSlotPlan matched by local key below
            'id',                      // PK on MusicPlan
            'music_plan_slot_plan_id', // local key on this model
            'music_plan_id'            // key on MusicPlanSlotPlan pointing to MusicPlan
        );
    }

    /**
     * Get the music plan slot for this assignment (via MusicPlanSlotPlan).
     */
    public function musicPlanSlot(): HasOneThrough
    {
        return $this->hasOneThrough(
            MusicPlanSlot::class,
            MusicPlanSlotPlan::class,
            'id',                      // PK on MusicPlanSlotPlan matched by local key below
            'id',                      // PK on MusicPlanSlot
            'music_plan_slot_plan_id', // local key on this model
            'music_plan_slot_id'       // key on MusicPlanSlotPlan pointing to MusicPlanSlot
        );
    }

    /**
     * Get the music plan slot plan (pivot) that owns this assignment.
     */
    public function musicPlanSlotPlan(): BelongsTo
    {
        return $this->belongsTo(MusicPlanSlotPlan::class);
    }

    /**
     * Get the music that is assigned.
     */
    public function music(): BelongsTo
    {
        return $this->belongsTo(Music::class);
    }

    /**
     * Get the flags associated with this assignment.
     */
    public function flags(): BelongsToMany
    {
        return $this->belongsToMany(
            MusicAssignmentFlag::class,
            'music_plan_slot_assignment_music_assignment_flag'
        );
    }

    /**
     * Get the scopes associated with this assignment.
     */
    public function scopes(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(MusicPlanSlotAssignmentScope::class, 'music_plan_slot_assignment_id');
    }

    /**
     * Scope for assignments in a specific music plan.
     */
    public function scopeForMusicPlan($query, MusicPlan $musicPlan): void
    {
        $query->whereHas('musicPlanSlotPlan', fn ($q) => $q->where('music_plan_id', $musicPlan->id));
    }

    /**
     * Scope for assignments in a specific slot.
     */
    public function scopeForSlot($query, MusicPlanSlot $slot): void
    {
        $query->whereHas('musicPlanSlotPlan', fn ($q) => $q->where('music_plan_slot_id', $slot->id));
    }
}
