<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class MusicPlanTemplateSlot extends Pivot
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'music_plan_template_slots';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = true;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'template_id',
        'slot_id',
        'sequence',
        'is_included_by_default',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'is_included_by_default' => 'boolean',
        ];
    }
}
