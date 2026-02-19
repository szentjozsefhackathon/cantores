<?php

namespace App\Models;

use App\Concerns\HasVisibilityScoping;
use App\Support\CacheKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Cache;
use Laravel\Scout\Attributes\SearchUsingFullText;
use Laravel\Scout\Searchable;
use OwenIt\Auditing\Contracts\Auditable;

class Author extends Model implements Auditable
{
    use HasFactory;
    use HasVisibilityScoping;
    use \OwenIt\Auditing\Auditable;
    use Searchable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
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
     * Get the user who owns this author.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the music items by this author.
     */
    public function music(): BelongsToMany
    {
        return $this->belongsToMany(Music::class, 'author_music')
            ->withTimestamps();
    }

    /**
     * Scope for searching by name.
     */
    public function scopeSearch($query, string $search): void
    {
        $query->where('name', 'ilike', "%{$search}%");
    }

    /**
     * Get the indexable data array for the model.
     *
     * @return array<string, mixed>
     */
    #[SearchUsingFullText(['name'])]
    public function toSearchableArray(): array
    {
        return [
            'name' => $this->name,
        ];
    }

    /**
     * Find an author by ID with caching (TTL: 1 hour).
     */
    public static function findCached(int $id): ?self
    {
        $key = CacheKey::forModel('author', 'id', ['id' => $id]);

        return Cache::remember($key, 3600, function () use ($id) {
            return static::with(['user'])->find($id);
        });
    }

    /**
     * Get all authors with caching (TTL: 1 hour).
     */
    public static function allCached(): \Illuminate\Database\Eloquent\Collection
    {
        $key = CacheKey::forModel('author', 'all');

        return Cache::remember($key, 3600, function () {
            return static::with(['user'])->orderBy('name')->get();
        });
    }
}
