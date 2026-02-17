<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MusicAssignmentFlag extends Model
{
    /** @use HasFactory<\Database\Factories\MusicAssignmentFlagFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = ['name'];

    /**
     * Get the music plan slot assignments associated with this flag.
     */
    public function musicPlanSlotAssignments(): BelongsToMany
    {
        return $this->belongsToMany(
            MusicPlanSlotAssignment::class,
            'music_plan_slot_assignment_music_assignment_flag'
        );
    }

    /**
     * Get the display label for this flag.
     */
    public function label(): string
    {
        return match ($this->name) {
            'important' => __('Important'),
            'alternative' => __('Alternative'),
            'low_priority' => __('Low Priority'),
            default => $this->name,
        };
    }

    /**
     * Get the icon name for this flag.
     */
    public function icon(): string
    {
        return match ($this->name) {
            'important' => 'star',
            'alternative' => 'refresh-cw',
            'low_priority' => 'arrow-down',
            default => 'flag',
        };
    }

    /**
     * Get the color for this flag.
     */
    public function color(): string
    {
        return match ($this->name) {
            'important' => 'amber',
            'alternative' => 'blue',
            'low_priority' => 'gray',
            default => 'slate',
        };
    }

    /**
     * Get all flags as options for select inputs.
     */
    public static function options(): array
    {
        return self::all()->mapWithKeys(fn ($flag) => [
            $flag->id => $flag->label(),
        ])->toArray();
    }
}
