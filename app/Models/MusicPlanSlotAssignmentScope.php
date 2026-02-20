<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
