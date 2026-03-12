<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $music_id
 * @property int $related_music_id
 * @property string|null $relationship_type
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property int|null $user_id
 * @property-read \App\Models\Music $music
 * @property-read \App\Models\Music $relatedMusic
 * @property-read \App\Models\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicRelation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicRelation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicRelation query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicRelation whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicRelation whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicRelation whereMusicId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicRelation whereRelatedMusicId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicRelation whereRelationshipType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicRelation whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicRelation whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicRelation forMusic(int|Music $music)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicRelation between(int $id1, int $id2)
 * @mixin \Eloquent
 */
class MusicRelation extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'music_related';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'music_id',
        'related_music_id',
        'relationship_type',
        'user_id',
    ];

    /**
     * Get the first music in the relation pair.
     */
    public function music(): BelongsTo
    {
        return $this->belongsTo(Music::class, 'music_id');
    }

    /**
     * Get the second music in the relation pair.
     */
    public function relatedMusic(): BelongsTo
    {
        return $this->belongsTo(Music::class, 'related_music_id');
    }

    /**
     * Get the user who created this relation.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the partner music from this relation, given one side.
     *
     * Returns the "other" music in the pair based on the given context.
     */
    public function partnerFor(Music $music): Music
    {
        return $this->music_id === $music->id ? $this->relatedMusic : $this->music;
    }

    /**
     * Scope: all relations that involve a given music (either direction).
     */
    public function scopeForMusic(Builder $query, int|Music $music): Builder
    {
        $id = $music instanceof Music ? $music->id : $music;
        return $query->where('music_id', $id)->orWhere('related_music_id', $id);
    }

    /**
     * Scope: the single relation record between two specific musics (either order).
     */
    public function scopeBetween(Builder $query, int $id1, int $id2): Builder
    {
        return $query->where(function ($q) use ($id1, $id2) {
            $q->where('music_id', $id1)->where('related_music_id', $id2);
        })->orWhere(function ($q) use ($id1, $id2) {
            $q->where('music_id', $id2)->where('related_music_id', $id1);
        });
    }
}
