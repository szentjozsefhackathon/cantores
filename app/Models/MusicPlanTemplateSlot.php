<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property int $id
 * @property int $music_plan_template_id
 * @property int $music_plan_slot_id
 * @property int $sequence
 * @property bool $is_included_by_default
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicPlanTemplateSlot newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicPlanTemplateSlot newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicPlanTemplateSlot query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicPlanTemplateSlot whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicPlanTemplateSlot whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicPlanTemplateSlot whereIsIncludedByDefault($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicPlanTemplateSlot whereMusicPlanSlotId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicPlanTemplateSlot whereMusicPlanTemplateId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicPlanTemplateSlot whereSequence($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicPlanTemplateSlot whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
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
