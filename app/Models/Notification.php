<?php

namespace App\Models;

use App\Enums\NotificationType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Notification extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'type',
        'message',
        'reporter_id',
        'notifiable_id',
        'notifiable_type',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => NotificationType::class,
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the user who reported the error.
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    /**
     * Get the notifiable resource (music, collection, author).
     */
    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the users who received this notification.
     */
    public function recipients(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'notification_user')
            ->withPivot('read_at')
            ->withTimestamps();
    }

    /**
     * Scope a query to only include error reports.
     */
    public function scopeErrorReports($query)
    {
        return $query->where('type', NotificationType::ERROR_REPORT);
    }

    /**
     * Get the resource type as a simple string.
     */
    public function getResourceTypeAttribute(): string
    {
        return match ($this->notifiable_type) {
            Music::class => 'music',
            Collection::class => 'collection',
            Author::class => 'author',
            default => class_basename($this->notifiable_type),
        };
    }

    /**
     * Get the title/name of the resource.
     */
    public function getResourceTitleAttribute(): ?string
    {
        return $this->notifiable?->title ?? $this->notifiable?->name ?? null;
    }

    /**
     * Determine if a specific user has read this notification.
     */
    public function isReadBy(User $user): bool
    {
        return $this->recipients()
            ->where('user_id', $user->id)
            ->whereNotNull('read_at')
            ->exists();
    }

    /**
     * Mark the notification as read for a user.
     */
    public function markAsReadFor(User $user): void
    {
        $this->recipients()->updateExistingPivot($user->id, [
            'read_at' => now(),
        ]);
    }
}
