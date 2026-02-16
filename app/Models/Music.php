<?php

namespace App\Models;

use App\Services\MusicSearchService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Scout\Attributes\SearchUsingFullText;
use Laravel\Scout\Attributes\SearchUsingPrefix;
use Laravel\Scout\Searchable;
use OwenIt\Auditing\Contracts\Auditable;

class Music extends Model implements Auditable
{
    use HasFactory;
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
            ->withPivot(['page_number', 'order_number'])
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
     * Get the related music items (variations).
     */
    public function relatedMusic(): BelongsToMany
    {
        return $this->belongsToMany(Music::class, 'music_related', 'music_id', 'related_music_id')
            ->withPivot('relationship_type')
            ->withTimestamps();
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
     * Scope for searching by title, subtitle, custom ID, collection title, collection abbreviation, order number, or page number.
     */
    public function scopeSearch($query, string $search): void
    {
        $service = new MusicSearchService;
        $service->applySearch($query, $search);
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

    /**
     * Scope for public music (not private).
     */
    public function scopePublic($query)
    {
        return $query->where('is_private', false);
    }

    /**
     * Scope for private music.
     */
    public function scopePrivate($query)
    {
        return $query->where('is_private', true);
    }

    /**
     * Scope for music visible to a given user.
     * Shows public music plus user's own private music.
     */
    public function scopeVisibleTo($query, ?\App\Models\User $user = null)
    {
        $userId = $user?->id;

        if (! $userId) {
            // Guest can only see public items
            return $query->where('is_private', false);
        }

        return $query->where(function ($q) use ($userId) {
            $q->where('is_private', false)
                ->orWhere('user_id', $userId);
        });
    }
    
        /**
     * Get the indexable data array for the model.
     *
     * @return array<string, mixed>
     */
    #[SearchUsingFullText(['title', 'subtitle', 'custom_id'])]
    #[SearchUsingPrefix(['collection_abbreviations', 'collection_order_numbers'])]
    public function toSearchableArray(): array
    {
        $array = [];
        $array['title'] = $this->title;
        $array['subtitle'] = $this->subtitle;
        $array['custom_id'] = $this->custom_id;

        // map collection abbrev and order number to a concatenated string for full‑text search
        $collections = $this->collections()->get()->map(function ($collection) {
            return $collection->abbreviation . ' ' . $collection->order_number;
        })->all();
        $array['collections_fulltext'] = implode(' ', $collections);

        return $array;
    }


}
