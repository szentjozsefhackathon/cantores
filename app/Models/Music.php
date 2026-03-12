<?php

namespace App\Models;

use App\Concerns\HasVisibilityScoping;
use App\Support\CacheKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Laravel\Scout\Attributes\SearchUsingFullText;
use Laravel\Scout\Searchable;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property int $id
 * @property string $title
 * @property string|null $custom_id
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property int|null $user_id
 * @property string|null $subtitle
 * @property bool $is_private
 * @property int|null $import_batch_number
 * @property string|null $titles
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \OwenIt\Auditing\Models\Audit> $audits
 * @property-read int|null $audits_count
 * @property-read \App\Models\MusicRelation|\App\Models\MusicCollection|\App\Models\AuthorMusic|null $pivot
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Author> $authors
 * @property-read int|null $authors_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Collection> $collections
 * @property-read int|null $collections_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Genre> $genres
 * @property-read int|null $genres_count
 * @property-read bool $is_verified
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\MusicPlanSlotAssignment> $musicPlanSlotAssignments
 * @property-read int|null $music_plan_slot_assignments_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\MusicRelation> $directMusicRelations
 * @property-read int|null $direct_music_relations_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\MusicRelation> $inverseMusicRelations
 * @property-read int|null $inverse_music_relations_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\MusicTag> $tags
 * @property-read int|null $tags_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\MusicUrl> $urls
 * @property-read int|null $urls_count
 * @property-read \App\Models\User|null $user
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\MusicVerification> $verifications
 * @property-read int|null $verifications_count
 * @method static \Database\Factories\MusicFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Music forCurrentGenre()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Music newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Music newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Music private()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Music public()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Music query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Music visibleTo(?\App\Models\User $user = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Music whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Music whereCustomId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Music whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Music whereImportBatchNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Music whereIsPrivate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Music whereSubtitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Music whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Music whereTitles($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Music whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Music whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Music withVisibleRelation(string $relation, ?\App\Models\User $user = null)
 * @mixin \Eloquent
 */
class Music extends Model implements Auditable
{
    use HasFactory;
    use HasVisibilityScoping;
    use \OwenIt\Auditing\Auditable;
    use Searchable;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'musics';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'subtitle',
        'custom_id',
        'user_id',
        'is_private',
        'import_batch_number',
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
            'import_batch_number' => 'integer',
        ];
    }

    /**
     * Get the user who owns this music.
     */
    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the collections that include this music.
     */
    public function collections(): BelongsToMany
    {
        return $this->belongsToMany(Collection::class, 'music_collection')
            ->using(MusicCollection::class)
            ->withPivot(['user_id', 'page_number', 'order_number'])
            ->withTimestamps();
    }

    /**
     * Get the authors associated with this music.
     */
    public function authors(): BelongsToMany
    {
        return $this->belongsToMany(Author::class, 'author_music')
            ->using(AuthorMusic::class)
            ->withPivot(['user_id'])
            ->withTimestamps();
    }

    /**
     * Get the genres associated with this music.
     */
    public function genres(): BelongsToMany
    {
        return $this->belongsToMany(Genre::class, 'music_genre');
    }

    /**
     * Get the tags associated with this music.
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(MusicTag::class, 'music_music_tag');
    }

    /**
     * Get the direct relations (where this music is the first side).
     */
    public function directMusicRelations(): HasMany
    {
        return $this->hasMany(MusicRelation::class, 'music_id');
    }

    /**
     * Get the inverse relations (where this music is the second side).
     */
    public function inverseMusicRelations(): HasMany
    {
        return $this->hasMany(MusicRelation::class, 'related_music_id');
    }

    /**
     * Get all music relations (both directions) as a collection.
     * This is not a true Eloquent relationship, but a merged collection.
     */
    public function allMusicRelations(): \Illuminate\Support\Collection
    {
        return $this->directMusicRelations->merge($this->inverseMusicRelations);
    }

    /**
     * Get the public URLs for this music.
     */
    public function urls(): HasMany
    {
        return $this->hasMany(MusicUrl::class);
    }

    /**
     * Get the music plan slot assignments for this music.
     */
    public function musicPlanSlotAssignments(): HasMany
    {
        return $this->hasMany(MusicPlanSlotAssignment::class);
    }

    /**
     * Get the verification records for this music.
     */
    public function verifications(): HasMany
    {
        return $this->hasMany(MusicVerification::class);
    }

    /**
     * Scope for music belonging to the current user's genre.
     */
    public function scopeForCurrentGenre($query)
    {
        $genreId = \App\Facades\GenreContext::getId();

        if ($genreId !== null) {
            // Filter by current genre (including music without genres)
            $query->where(function ($q) use ($genreId) {
                $q->whereHas('genres', function ($subQ) use ($genreId) {
                    $subQ->where('genres.id', $genreId);
                })->orWhereDoesntHave('genres');
            });
        }
        // If $genreId is null, no filtering applied (show all music)
    }

    #[SearchUsingFullText(['titles'], ['language' => 'hungarian'])]
    public function toSearchableArray(): array
    {
        return [
            'titles' => $this->titles,
            // DO NOT include custom_id in the full-text columns (we want it as ILIKE)
            'custom_id' => $this->custom_id,
        ];
    }

    /**
     * Helper to compute the denormalized titles field.
     */
    public function computeTitles(): string
    {
        return trim(implode(' ', array_filter([
            $this->title,
            $this->subtitle,
        ])));
    }

    protected static function booted(): void
    {
        static::saving(function (Music $music) {
            $music->titles = $music->computeTitles();
        });
    }

    public function getIsVerifiedAttribute(): bool
    {
        if (isset($this->verified_verifications_count)) {
            return $this->verified_verifications_count > 0;
        } else {
            // Fallback if the count is not loaded (e.g., not eager loaded)
            return $this->verifications()->where('status', 'verified')->exists();
        }
    }

    /**
     * Find a music by ID with caching (TTL: 5 minutes).
     */
    public static function findCached(int $id): ?self
    {
        $key = CacheKey::forModel('music', 'id', ['id' => $id]);

        return Cache::remember($key, 300, function () use ($id) {
            return static::with(['collections', 'authors', 'genres', 'user'])->find($id);
        });
    }

    /**
     * Get all music with caching (TTL: 5 minutes).
     */
    public static function allCached(): \Illuminate\Database\Eloquent\Collection
    {
        $key = CacheKey::forModel('music', 'all');

        return Cache::remember($key, 300, function () {
            return static::with(['collections', 'authors', 'genres', 'user'])->orderBy('title')->get();
        });
    }
}
