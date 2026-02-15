<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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
    public function musicPlans(): BelongsToMany
    {
        return $this->belongsToMany(MusicPlan::class, 'celebration_music_plan')
            ->withTimestamps();
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

        return $days[$this->day] ?? 'ismeretlen';
    }
}
