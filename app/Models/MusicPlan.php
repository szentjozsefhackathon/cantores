<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MusicPlan extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'setting',
        'is_published',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
        ];
    }

    /**
     * Get the user that owns the music plan.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the celebrations for this music plan.
     */
    public function celebrations(): BelongsToMany
    {
        return $this->belongsToMany(Celebration::class, 'celebration_music_plan')
            ->withTimestamps();
    }

    /**
     * Get the slots for this music plan.
     */
    public function slots(): BelongsToMany
    {
        return $this->belongsToMany(MusicPlanSlot::class, 'music_plan_slot_plan')
            ->withPivot('sequence')
            ->orderByPivot('sequence');
    }

    /**
     * Get the music assignments for this plan.
     */
    public function musicAssignments(): HasMany
    {
        return $this->hasMany(MusicPlanSlotAssignment::class);
    }

    /**
     * Get the music items assigned to this plan (through assignments).
     */
    public function assignedMusic(): BelongsToMany
    {
        return $this->belongsToMany(Music::class, 'music_plan_slot_assignments')
            ->withPivot(['music_plan_slot_id', 'sequence', 'notes'])
            ->withTimestamps();
    }

    /**
     * Scope for published plans.
     */
    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    /**
     * Scope for private plans.
     */
    public function scopePrivate($query)
    {
        return $query->where('is_published', false);
    }

    /**
     * Scope for plans by setting.
     */
    public function scopeBySetting($query, string $setting)
    {
        return $query->where('setting', $setting);
    }

    /**
     * Get the setting options with icons.
     */
    public static function settingOptions(): array
    {
        return [
            'organist' => __('Organist'),
            'guitarist' => __('Guitarist'),
            'other' => __('Other'),
        ];
    }

    /**
     * Get the liturgical year options.
     */
    public static function yearLetterOptions(): array
    {
        return [
            'A' => __('Year A'),
            'B' => __('Year B'),
            'C' => __('Year C'),
        ];
    }

    /**
     * Get the parity options.
     */
    public static function yearParityOptions(): array
    {
        return [
            'I' => __('I'),
            'II' => __('II'),
        ];
    }

    /**
     * Get the first celebration's day name for the liturgical day number.
     * This is a convenience method to access day name from the first associated celebration.
     */
    public function getDayNameAttribute(): string
    {
        $firstCelebration = $this->celebrations->first();
        if ($firstCelebration) {
            return $firstCelebration->day_name;
        }

        return 'ismeretlen';
    }

    /**
     * Get the first celebration's name.
     * This is a convenience method to access celebration name from the first associated celebration.
     */
    public function getCelebrationNameAttribute(): ?string
    {
        $firstCelebration = $this->celebrations->first();

        return $firstCelebration?->name;
    }

    /**
     * Get the first celebration's actual date.
     * This is a convenience method to access actual date from the first associated celebration.
     */
    public function getActualDateAttribute(): ?string
    {
        $firstCelebration = $this->celebrations->first();

        return $firstCelebration?->actual_date;
    }
}
