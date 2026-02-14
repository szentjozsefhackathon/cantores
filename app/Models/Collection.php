<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Auth;
use OwenIt\Auditing\Contracts\Auditable;

class Collection extends Model implements Auditable
{
    use HasFactory;
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
    ];

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
            ->withPivot(['page_number', 'order_number'])
            ->withTimestamps()
            ->orderByPivot('order_number');
    }

    /**
     * Get the realms associated with this collection.
     */
    public function realms(): BelongsToMany
    {
        return $this->belongsToMany(Realm::class, 'collection_realm');
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
     * Scope for collections belonging to the current user's realm.
     */
    public function scopeForCurrentRealm($query)
    {
        $user = Auth::user();
        if (! $user) {
            // No authenticated user, return empty
            $query->whereRaw('1 = 0');

            return;
        }

        $realmId = $user->current_realm_id;
        if ($realmId) {
            $query->whereHas('realms', function ($q) use ($realmId) {
                $q->where('realms.id', $realmId);
            });
        }
        // If no realm ID, show all collections (no filtering)
    }
}
