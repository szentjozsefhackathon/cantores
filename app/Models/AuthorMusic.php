<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property int $id
 * @property int $author_id
 * @property int $music_id
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property int|null $user_id
 * @property-read \App\Models\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthorMusic newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthorMusic newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthorMusic query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthorMusic whereAuthorId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthorMusic whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthorMusic whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthorMusic whereMusicId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthorMusic whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthorMusic whereUserId($value)
 * @mixin \Eloquent
 */
class AuthorMusic extends Pivot
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'author_music';

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
        'author_id',
        'music_id',
        'user_id',
    ];

    /**
     * Get the user who added this author relation.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
