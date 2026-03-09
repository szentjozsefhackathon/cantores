<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

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
