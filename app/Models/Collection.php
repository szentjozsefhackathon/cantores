<?php

namespace App\Models;

use App\Concerns\HasVisibilityScoping;
use App\Support\CacheKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use OwenIt\Auditing\Contracts\Auditable;

class Collection extends Model implements Auditable
{
    use HasFactory;
    use HasVisibilityScoping;
    use \OwenIt\Auditing\Auditable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'abbreviation',
        'author',
        'user_id',
        'is_private',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_private' => 'boolean',
        ];
    }

    /**
     * Get the user who owns this collection.
     */
    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the music items in this collection.
     */
    public function music(): BelongsToMany
    {
        return $this->belongsToMany(Music::class, 'music_collection')
            ->using(MusicCollection::class)
            ->withPivot(['page_number', 'order_number'])
            ->withTimestamps()
            ->orderByPivot('order_number');
    }

    /**
     * Get the genres associated with this collection.
     */
    public function genres(): BelongsToMany
    {
        return $this->belongsToMany(Genre::class, 'collection_genre');
    }

    /**
     * Scope for searching by title or abbreviation.
     */
    public function scopeSearch($query, string $search): void
    {
        $query->where('title', 'ilike', "%{$search}%")
            ->orWhere('abbreviation', 'ilike', "%{$search}%")
            ->orWhere('author', 'ilike', "%{$search}%");
    }

    /**
     * Scope for collections belonging to the current user's genre.
     * Empty collection genre association means it belongs to all genres, so we include those as well.
     */
    public function scopeForCurrentGenre($query)
    {
        $genreId = \App\Facades\GenreContext::getId();

        if ($genreId !== null) {
            // Filter by current genre (including collections without genres)
            $query->where(function ($q) use ($genreId) {
                $q->whereHas('genres', function ($subQ) use ($genreId) {
                    $subQ->where('genres.id', $genreId);
                })->orWhereDoesntHave('genres');
            });
        }
        // If $genreId is null, no filtering applied (show all collections)
    }

    /**
     * Format the collection with pivot data for display.
     */
    public function formatWithPivot(?\Illuminate\Database\Eloquent\Relations\Pivot $pivot = null): string
    {
        $base = $this->abbreviation ?: Str::limit($this->title, 12, '...');

        $parts = [];

        if ($pivot && $pivot->order_number) {
            $parts[] = $pivot->order_number;
        }

        $formatted = trim($base.($parts ? ' '.implode(' ', $parts) : ''));

        if ($pivot && $pivot->page_number) {
            $formatted .= ' '.__('(p.:page)', ['page' => $pivot->page_number]);
        }

        return $formatted;
    }

    /**
     * Find a collection by ID with caching (TTL: 1 hour).
     */
    public static function findCached(int $id): ?self
    {
        $key = CacheKey::forModel('collection', 'id', ['id' => $id]);

        return Cache::remember($key, 3600, function () use ($id) {
            return static::with(['genres', 'user'])->find($id);
        });
    }

    /**
     * Get all collections with caching (TTL: 1 hour).
     */
    public static function allCached(): \Illuminate\Database\Eloquent\Collection
    {
        $key = CacheKey::forModel('collection', 'all');

        return Cache::remember($key, 3600, function () {
            return static::with(['genres', 'user'])->orderBy('title')->get();
        });
    }

    /**
     * Get collections as options for dropdowns with caching (TTL: 1 hour).
     */
    public static function optionsCached(): array
    {
        $key = CacheKey::forModel('collection', 'options');

        return Cache::remember($key, 3600, function () {
            return static::visibleTo(auth()->user())
                ->orderBy('title')
                ->get()
                ->mapWithKeys(fn ($collection) => [$collection->id => $collection->title])
                ->toArray();
        });
    }
}
