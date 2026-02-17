<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Laravel\Scout\Attributes\SearchUsingFullText;
use Laravel\Scout\Searchable;
use OwenIt\Auditing\Contracts\Auditable;

class Author extends Model implements Auditable
{
    use HasFactory;
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
     * Scope for public authors (not private).
     */
    public function scopePublic($query)
    {
        return $query->where('is_private', false);
    }

    /**
     * Scope for private authors.
     */
    public function scopePrivate($query)
    {
        return $query->where('is_private', true);
    }

    /**
     * Scope for authors visible to a given user.
     * Shows public authors plus user's own private authors.
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
    #[SearchUsingFullText(['name'])]
    public function toSearchableArray(): array
    {
        return [
            'name' => $this->name,
        ];
    }
}
