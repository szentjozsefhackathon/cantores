<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $celebration_key
 * @property \Carbon\CarbonImmutable $actual_date
 * @property string $name
 * @property int|null $season
 * @property string|null $season_text
 * @property int|null $week
 * @property int|null $day
 * @property string|null $readings_code
 * @property string|null $year_letter
 * @property string|null $year_parity
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property int|null $user_id
 * @property bool $is_custom
 * @property-read string $day_name
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\MusicPlan> $musicPlans
 * @property-read int|null $music_plans_count
 * @property-read \App\Models\User|null $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Celebration custom()
 * @method static \Database\Factories\CelebrationFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Celebration forUser($user)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Celebration liturgical()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Celebration newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Celebration newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Celebration query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Celebration whereActualDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Celebration whereCelebrationKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Celebration whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Celebration whereDay($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Celebration whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Celebration whereIsCustom($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Celebration whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Celebration whereReadingsCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Celebration whereSeason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Celebration whereSeasonText($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Celebration whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Celebration whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Celebration whereWeek($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Celebration whereYearLetter($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Celebration whereYearParity($value)
 *
 * @mixin \Eloquent
 */
class Celebration extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     * Celebration key is not an ID, but the sequence number of the celebration on the given date.
     *
     * @var list<string>
     */
    protected $fillable = [
        'celebration_key',
        'actual_date',
        'name',
        'season',
        'season_text',
        'week',
        'day',
        'readings_code',
        'year_letter',
        'year_parity',
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
            'actual_date' => 'date',
            'celebration_key' => 'integer',
            'season' => 'integer',
            'week' => 'integer',
            'day' => 'integer',
            'is_custom' => 'boolean',
        ];
    }

    /**
     * Get the music plans for this celebration.
     */
    public function musicPlans(): HasMany
    {
        return $this->hasMany(MusicPlan::class);
    }

    /**
     * Get the user that owns this custom celebration.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope a query to only include liturgical celebrations (non-custom).
     */
    public function scopeLiturgical($query)
    {
        return $query->where('is_custom', false);
    }

    /**
     * Scope a query to only include custom celebrations.
     */
    public function scopeCustom($query)
    {
        return $query->where('is_custom', true);
    }

    /**
     * Scope a query to only include celebrations belonging to a specific user.
     */
    public function scopeForUser($query, $user)
    {
        $userId = $user instanceof User ? $user->id : $user;

        return $query->where('user_id', $userId);
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

        return $days[$this->day] ?? '-';
    }

    /**
     * Find the next available celebration_key for a custom celebration.
     */
    public static function findNextAvailableKey(int $userId, string $date): int
    {
        $existingKeys = self::where('is_custom', true)
            ->where('user_id', $userId)
            ->where('actual_date', $date)
            ->pluck('celebration_key')
            ->toArray();

        $key = 0;
        while (in_array($key, $existingKeys)) {
            $key++;
        }

        return $key;
    }

    /**
     * Update celebration with automatic key adjustment if needed.
     */
    public function updateWithKeyAdjustment(array $attributes): bool
    {
        if ($this->is_custom && isset($attributes['actual_date'])) {
            $newDate = $attributes['actual_date'];
            $userId = $this->user_id;

            // If date is changing, we need to check for conflicts
            if ($newDate !== $this->actual_date->format('Y-m-d')) {
                $existingKeys = self::where('is_custom', true)
                    ->where('user_id', $userId)
                    ->where('actual_date', $newDate)
                    ->where('id', '!=', $this->id)
                    ->pluck('celebration_key')
                    ->toArray();

                $key = $this->celebration_key;
                if (in_array($key, $existingKeys)) {
                    // Find next available key
                    while (in_array($key, $existingKeys)) {
                        $key++;
                    }
                    $attributes['celebration_key'] = $key;
                }
            }
        }

        return $this->update($attributes);
    }
}
