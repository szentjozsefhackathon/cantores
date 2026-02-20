<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Class MusicPlanSlotAssignment
 *
 * Represents an assignment of a music track to a specific slot within a music plan.
 */
class MusicPlanSlotAssignment extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     * IMPORTANT: music_plan_slot_plan_id identifies the specific slot instance within the music plan,
     * while music_sequence is identifying the position of the music within the slot.
     * This allows for multiple tracks to be assigned to the same slot in a specific order.
     * The music_plan_id and music_plan_slot_id are kept for compatibility and referential integrity.
     *
     * @var list<string>
     */
    protected $fillable = [
        'music_plan_slot_plan_id',
        'music_plan_id',
        'music_plan_slot_id',
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
     * Get the music plan that owns this assignment.
     */
    public function musicPlan(): BelongsTo
    {
        return $this->belongsTo(MusicPlan::class);
    }

    /**
     * Get the music plan slot that owns this assignment.
     */
    public function musicPlanSlot(): BelongsTo
    {
        return $this->belongsTo(MusicPlanSlot::class);
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
        $query->where('music_plan_id', $musicPlan->id);
    }

    /**
     * Scope for assignments in a specific slot.
     */
    public function scopeForSlot($query, MusicPlanSlot $slot): void
    {
        $query->where('music_plan_slot_id', $slot->id);
    }
}
