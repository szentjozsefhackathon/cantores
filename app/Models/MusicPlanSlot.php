<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $priority Defines the order in which slots are displayed in a unified view of a celebration.
 *                         Lower numbers have higher priority (e.g., priority 1 appears before priority 2).
 * @property int|null $music_plan_id The plan this custom slot belongs to (null for global slots)
 * @property int|null $user_id The owner of this custom slot (null for global slots)
 * @property bool $is_custom Whether this is a custom slot (true) or global slot (false)
 */
class MusicPlanSlot extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'description',
        'priority',
        'music_plan_id',
        'user_id',
        'is_custom',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'deleted_at' => 'datetime',
            'priority' => 'integer',
            'is_custom' => 'boolean',
        ];
    }

    /**
     * Get the templates that include this slot.
     */
    public function templates(): BelongsToMany
    {
        return $this->belongsToMany(MusicPlanTemplate::class, 'music_plan_template_slots')
            ->withPivot(['sequence', 'is_included_by_default'])
            ->orderByPivot('sequence');
    }

    /**
     * Get the music assignments for this slot.
     */
    public function musicAssignments(): HasMany
    {
        return $this->hasMany(MusicPlanSlotAssignment::class);
    }

    /**
     * Get the music items assigned to this slot (through assignments).
     * This is a convenience method that goes through the MusicPlanSlotAssignment model.
     */
    public function assignedMusic(): \Illuminate\Database\Eloquent\Relations\HasManyThrough
    {
        return $this->hasManyThrough(
            Music::class,
            MusicPlanSlotAssignment::class,
            'music_plan_slot_id', // Foreign key on MusicPlanSlotAssignment table
            'id', // Foreign key on Music table
            'id', // Local key on MusicPlanSlot table
            'music_id' // Foreign key on MusicPlanSlotAssignment table
        );
    }

    /**
     * Get the music plan this custom slot belongs to.
     */
    public function musicPlan(): BelongsTo
    {
        return $this->belongsTo(MusicPlan::class);
    }

    /**
     * Get the owner of this custom slot.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Scope for global slots (non-custom, available to all plans).
     */
    public function scopeGlobal(Builder $query): Builder
    {
        return $query->where('is_custom', false);
    }

    /**
     * Scope for custom slots (plan-specific).
     */
    public function scopeCustom(Builder $query): Builder
    {
        return $query->where('is_custom', true);
    }

    /**
     * Scope for slots belonging to a specific plan.
     */
    public function scopeForPlan(Builder $query, MusicPlan|int $plan): Builder
    {
        $planId = $plan instanceof MusicPlan ? $plan->id : $plan;

        return $query->where(function (Builder $q) use ($planId) {
            $q->where('is_custom', false) // Global slots
                ->orWhere(function (Builder $subQ) use ($planId) {
                    $subQ->where('is_custom', true)
                        ->where('music_plan_id', $planId);
                });
        });
    }

    /**
     * Scope for slots visible to a specific user.
     * Global slots are visible to all users.
     * Custom slots are visible only to the owner.
     */
    public function scopeVisibleToUser(Builder $query, User|int|null $user = null): Builder
    {
        $userId = $user instanceof User ? $user->id : $user;

        return $query->where(function (Builder $q) use ($userId) {
            $q->where('is_custom', false) // Global slots visible to all
                ->orWhere(function (Builder $subQ) use ($userId) {
                    $subQ->where('is_custom', true)
                        ->where('user_id', $userId);
                });
        });
    }

    /**
     * Check if this slot is a custom slot.
     */
    public function isCustom(): bool
    {
        return $this->is_custom;
    }

    /**
     * Check if this slot is a global slot.
     */
    public function isGlobal(): bool
    {
        return ! $this->is_custom;
    }

    /**
     * Scope for active slots (not soft deleted).
     */
    public function scopeActive($query)
    {
        return $query->whereNull('deleted_at');
    }
}
