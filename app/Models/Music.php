<?php

namespace App\Models;

use App\Services\MusicSearchService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
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
    ];

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
            ->withPivot(['page_number', 'order_number'])
            ->withTimestamps();
    }

    /**
     * Get the realms associated with this music.
     */
    public function realms(): BelongsToMany
    {
        return $this->belongsToMany(Realm::class, 'music_realm');
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
     * Scope for music belonging to the current user's realm.
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
            $query->where(function ($q) use ($realmId) {
                $q->whereHas('realms', function ($subQ) use ($realmId) {
                    $subQ->where('realms.id', $realmId);
                })->orWhereDoesntHave('realms');
            });
        }
        // If no realm ID, show all music (no filtering)
    }
}
