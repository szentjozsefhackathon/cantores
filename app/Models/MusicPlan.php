<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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
        'celebration_name',
        'actual_date',
        'setting',
        'season',
        'season_text',
        'week',
        'day',
        'readings_code',
        'year_letter',
        'year_parity',
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
            'actual_date' => 'date',
            'is_published' => 'boolean',
            'season' => 'integer',
            'season_text' => 'string',
            'week' => 'integer',
            'day' => 'integer',
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
     * Get the slots for this music plan.
     */
    public function slots(): BelongsToMany
    {
        return $this->belongsToMany(MusicPlanSlot::class, 'music_plan_slot_plan')
            ->withPivot('sequence')
            ->orderByPivot('sequence');
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
     * Get the day name for the liturgical day number.
     */
    public function getDayNameAttribute(): string
    {
        $days = [
            0 => 'vasárnap',
            1 => 'hétfő',
            2 => 'kedd',
            3 => 'szerda',
            4 => 'csütörtök',
            5 => 'péntek',
            6 => 'szombat',
        ];

        return $days[$this->day] ?? 'ismeretlen';
    }
}
