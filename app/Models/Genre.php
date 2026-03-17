<?php

namespace App\Models;

use App\Support\CacheKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Cache;

/**
 * @property int $id
 * @property string $name
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Collection> $collections
 * @property-read int|null $collections_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Music> $music
 * @property-read int|null $music_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\MusicPlan> $musicPlans
 * @property-read int|null $music_plans_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $users
 * @property-read int|null $users_count
 *
 * @method static \Database\Factories\GenreFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Genre newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Genre newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Genre query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Genre whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Genre whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Genre whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Genre whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
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
        return self::optionsCached();
    }

    /**
     * Get all genres, cached forever.
     *
     * @return \Illuminate\Support\Collection<int, self>
     */
    public static function allCached(): SupportCollection
    {
        $key = CacheKey::forModel('genre', 'all');

        return Cache::rememberForever($key, function () {
            return self::all();
        });
    }

    /**
     * Get genre options, cached forever.
     *
     * @return array<int, string>
     */
    public static function optionsCached(): array
    {
        $key = CacheKey::forModel('genre', 'options');

        return Cache::rememberForever($key, function () {
            return self::allCached()->mapWithKeys(fn ($genre) => [
                $genre->id => $genre->label(),
            ])->toArray();
        });
    }

    /**
     * Find a genre by ID, cached forever.
     */
    public static function findCached(int $id): ?self
    {
        $key = CacheKey::forModel('genre', 'id', ['id' => $id]);

        return Cache::rememberForever($key, function () use ($id) {
            return self::find($id);
        });
    }
}
