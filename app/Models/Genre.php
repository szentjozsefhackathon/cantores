<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Genre extends Model
{
    /** @use HasFactory<\Database\Factories\GenreFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = ['name'];

    /**
     * Get the music items associated with this genre.
     */
    public function music(): BelongsToMany
    {
        return $this->belongsToMany(Music::class, 'music_genre');
    }

    /**
     * Get the collections associated with this genre.
     */
    public function collections(): BelongsToMany
    {
        return $this->belongsToMany(Collection::class, 'collection_genre');
    }

    /**
     * Get the users who have this genre as their current genre.
     */
    public function users()
    {
        return $this->hasMany(User::class, 'current_genre_id');
    }

    /**
     * Get the music plans associated with this genre.
     */
    public function musicPlans()
    {
        return $this->hasMany(MusicPlan::class, 'genre_id');
    }

    /**
     * Get the display label for this genre.
     */
    public function label(): string
    {
        return match ($this->name) {
            'organist' => __('Organist'),
            'guitarist' => __('Guitarist'),
            'other' => __('Other'),
            default => $this->name,
        };
    }

    /**
     * Get the icon name for this genre.
     */
    public function icon(): string
    {
        return match ($this->name) {
            'organist' => 'organist',
            'guitarist' => 'guitar',
            default => 'landmark',
        };
    }

    /**
     * Get the color for this genre.
     */
    public function color(): string
    {
        return match ($this->name) {
            'organist' => 'blue',
            'guitarist' => 'green',
            default => 'gray',
        };
    }

    /**
     * Get all genres as options for select inputs.
     */
    public static function options(): array
    {
        return self::all()->mapWithKeys(fn ($genre) => [
            $genre->id => $genre->label(),
        ])->toArray();
    }
}
