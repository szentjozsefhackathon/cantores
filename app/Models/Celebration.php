<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Celebration extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
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
