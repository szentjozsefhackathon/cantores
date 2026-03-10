<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property int $id
 * @property int $music_id
 * @property int $collection_id
 * @property int|null $page_number
 * @property string|null $order_number
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property int|null $user_id
 * @property-read \App\Models\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicCollection newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicCollection newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicCollection query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicCollection whereCollectionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicCollection whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicCollection whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicCollection whereMusicId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicCollection whereOrderNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicCollection wherePageNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicCollection whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicCollection whereUserId($value)
 * @mixin \Eloquent
 */
class MusicCollection extends Pivot
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'music_collection';

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
        'collection_id',
        'user_id',
        'page_number',
        'order_number',
    ];

    /**
     * Get the user who added this collection relation.
     */
    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'page_number' => 'integer',
        ];
    }
}
