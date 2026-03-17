<?php

namespace App\Models;

use App\Concerns\HasVisibilityScoping;
use App\Support\CacheKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Laravel\Scout\Attributes\SearchUsingFullText;
use Laravel\Scout\Searchable;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property int $id
 * @property string $name
 * @property int|null $user_id
 * @property bool $is_private
 * @property bool $is_verified
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \OwenIt\Auditing\Models\Audit> $audits
 * @property-read int|null $audits_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Music> $music
 * @property-read int|null $music_count
 * @property-read \App\Models\User|null $user
 *
 * @method static \Database\Factories\AuthorFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Author newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Author newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Author private()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Author public()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Author query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Author search(string $search)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Author visibleTo(?\App\Models\User $user = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Author whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Author whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Author whereIsPrivate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Author whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Author whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Author whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Author withVisibleRelation(string $relation, ?\App\Models\User $user = null)
 *
 * @mixin \Eloquent
 */
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
        'is_verified',
        'avatar',
        'photo_license',
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
            'is_verified' => 'boolean',
        ];
    }

    /**
     * Get the URL for the full-size avatar (256×256).
     */
    public function avatarUrl(): ?string
    {
        if (! $this->avatar) {
            return null;
        }

        if (str_contains($this->avatar, '/')) {
            return Storage::disk('public')->url("authors/{$this->id}/avatar.jpg");
        }

        return Storage::disk('public')->url("authors/{$this->id}/avatar_{$this->avatar}.jpg");
    }

    /**
     * Get the URL for the thumbnail avatar (64×64).
     */
    public function avatarThumbUrl(): ?string
    {
        if (! $this->avatar) {
            return null;
        }

        if (str_contains($this->avatar, '/')) {
            return Storage::disk('public')->url("authors/{$this->id}/avatar_thumb.jpg");
        }

        return Storage::disk('public')->url("authors/{$this->id}/avatar_thumb_{$this->avatar}.jpg");
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
