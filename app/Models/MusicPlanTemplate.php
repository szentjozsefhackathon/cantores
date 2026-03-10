<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string|null $name
 * @property string|null $description
 * @property bool $is_active
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property \Carbon\CarbonImmutable|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\MusicPlanSlot> $slots
 * @property-read int|null $slots_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicPlanTemplate active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicPlanTemplate newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicPlanTemplate newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicPlanTemplate onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicPlanTemplate query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicPlanTemplate whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicPlanTemplate whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicPlanTemplate whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicPlanTemplate whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicPlanTemplate whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicPlanTemplate whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicPlanTemplate whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicPlanTemplate withSlots()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicPlanTemplate withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicPlanTemplate withoutTrashed()
 * @mixin \Eloquent
 */
class MusicPlanTemplate extends Model
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
        'is_active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Get the slots for this template with ordering and inclusion info.
     */
    public function slots(): BelongsToMany
    {
        return $this->belongsToMany(MusicPlanSlot::class, 'music_plan_template_slots')
            ->withPivot(['sequence', 'is_included_by_default'])
            ->orderByPivot('sequence');
    }

    /**
     * Get only slots included by default.
     */
    public function defaultSlots()
    {
        return $this->slots()->wherePivot('is_included_by_default', true);
    }

    /**
     * Get only advanced (optional) slots.
     */
    public function advancedSlots()
    {
        return $this->slots()->wherePivot('is_included_by_default', false);
    }

    /**
     * Scope for active templates.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for including slots.
     */
    public function scopeWithSlots($query)
    {
        return $query->with('slots');
    }

    /**
     * Attach a slot to the template with sequence and inclusion flag.
     */
    public function attachSlot(MusicPlanSlot $slot, int $sequence, bool $isIncludedByDefault = true): void
    {
        $this->slots()->attach($slot->id, [
            'sequence' => $sequence,
            'is_included_by_default' => $isIncludedByDefault,
        ]);
    }

    /**
     * Update a slot's sequence and inclusion flag.
     */
    public function updateSlot(MusicPlanSlot $slot, int $sequence, bool $isIncludedByDefault): void
    {
        $this->slots()->updateExistingPivot($slot->id, [
            'sequence' => $sequence,
            'is_included_by_default' => $isIncludedByDefault,
        ]);
    }

    /**
     * Detach a slot from the template.
     */
    public function detachSlot(MusicPlanSlot $slot): void
    {
        $this->slots()->detach($slot->id);
    }
}
