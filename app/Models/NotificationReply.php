<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $notification_id
 * @property int $user_id
 * @property string $body
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \App\Models\Notification $notification
 * @property-read \App\Models\User $user
 *
 * @method static \Database\Factories\NotificationReplyFactory factory($count = null, $state = [])
 *
 * @mixin \Eloquent
 */
class NotificationReply extends Model
{
    /** @use HasFactory<\Database\Factories\NotificationReplyFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'notification_id',
        'user_id',
        'body',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the notification this reply belongs to.
     */
    public function notification(): BelongsTo
    {
        return $this->belongsTo(Notification::class);
    }

    /**
     * Get the user who wrote the reply.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
