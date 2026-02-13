<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use OwenIt\Auditing\Contracts\Auditable;

class Music extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

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
        'custom_id',
    ];

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
     * Scope for searching by title or custom ID.
     */
    public function scopeSearch($query, string $search): void
    {
        $query->where('title', 'ilike', "%{$search}%")
            ->orWhere('custom_id', 'ilike', "%{$search}%");
    }

    /**
     * Scope for music belonging to the current user's realm.
     */
    public function scopeForCurrentRealm($query)
    {
        $realmId = Auth::user()?->current_realm_id;
        if ($realmId) {
            $query->whereHas('realms', function ($q) use ($realmId) {
                $q->where('realms.id', $realmId);
            });
        } else {
            // If no realm ID, ensure no results
            $query->whereRaw('1 = 0');
        }
    }
}
