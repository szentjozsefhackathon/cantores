<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $music_plan_slot_assignment_id
 * @property \App\Enums\MusicScopeType $scope_type
 * @property int|null $scope_number
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \App\Models\MusicPlanSlotAssignment $assignment
 * @property-read string $label
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicPlanSlotAssignmentScope newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicPlanSlotAssignmentScope newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicPlanSlotAssignmentScope query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicPlanSlotAssignmentScope whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicPlanSlotAssignmentScope whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicPlanSlotAssignmentScope whereMusicPlanSlotAssignmentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicPlanSlotAssignmentScope whereScopeNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicPlanSlotAssignmentScope whereScopeType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicPlanSlotAssignmentScope whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class MusicPlanSlotAssignmentScope extends Model
{
    protected $fillable = [
        'music_plan_slot_assignment_id',
        'scope_type',
        'scope_number',
    ];

    protected function casts(): array
    {
        return [
            'scope_type' => \App\Enums\MusicScopeType::class,
        ];
    }

    public function getLabelAttribute(): string
    {
        return $this->scope_number
            ? $this->scope_type->label().' '.$this->scope_number
            : $this->scope_type->label();
    }

    public function assignment(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(MusicPlanSlotAssignment::class, 'music_plan_slot_assignment_id');
    }
}
