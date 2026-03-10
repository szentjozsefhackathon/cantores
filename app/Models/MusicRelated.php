<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property int $id
 * @property int $music_id
 * @property int $related_music_id
 * @property string|null $relationship_type
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property int|null $user_id
 * @property-read \App\Models\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicRelated newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicRelated newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicRelated query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicRelated whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicRelated whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicRelated whereMusicId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicRelated whereRelatedMusicId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicRelated whereRelationshipType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicRelated whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicRelated whereUserId($value)
 * @mixin \Eloquent
 */
class MusicRelated extends Pivot
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'music_related';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = true;

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
     * Get the user who added this relation.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
