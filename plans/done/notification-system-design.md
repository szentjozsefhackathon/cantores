# Notification System Design

## Overview
Design for a notification system supporting error reports on music, collection, and author resources. Users can report errors with a short message (max 160 characters). Notifications are sent to the resource owner and all admin users.

## Database Schema

### 1. `notifications` Table
Stores the core notification/error report data.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | `bigint` | Primary key, auto-increment | Unique identifier |
| `type` | `varchar(50)` | Not null, default: 'error_report' | Notification type (enum) |
| `message` | `varchar(160)` | Not null | Error description (max 160 chars) |
| `reporter_id` | `bigint` | Foreign key to `users.id`, nullable | User who reported the error |
| `notifiable_id` | `bigint` | Not null | Polymorphic resource ID |
| `notifiable_type` | `varchar(255)` | Not null | Polymorphic resource class (App\Models\Music, etc.) |
| `created_at` | `timestamp` | Nullable | Creation timestamp |
| `updated_at` | `timestamp` | Nullable | Update timestamp |

**Indexes:**
- `notifications_notifiable_index` (`notifiable_type`, `notifiable_id`) – for polymorphic queries
- `notifications_reporter_id_index` (`reporter_id`) – for reporter lookups
- `notifications_created_at_index` (`created_at`) – for sorting

**Foreign Keys:**
- `reporter_id` references `users.id` ON DELETE SET NULL

### 2. `notification_user` Table
Tracks which users have received/read each notification.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | `bigint` | Primary key, auto-increment | Unique identifier |
| `notification_id` | `bigint` | Foreign key to `notifications.id` | Reference to notification |
| `user_id` | `bigint` | Foreign key to `users.id` | Recipient user |
| `read_at` | `timestamp` | Nullable | When the user read the notification |
| `created_at` | `timestamp` | Nullable | Entry creation time |

**Indexes:**
- `notification_user_notification_id_user_id_unique` (`notification_id`, `user_id`) – unique constraint
- `notification_user_user_id_read_at_index` (`user_id`, `read_at`) – for unread queries
- `notification_user_user_id_index` (`user_id`) – for user-specific lookups

**Foreign Keys:**
- `notification_id` references `notifications.id` ON DELETE CASCADE
- `user_id` references `users.id` ON DELETE CASCADE

## Models

### 1. `Notification` Model
Located at `app/Models/Notification.php`

```php
<?php

namespace App\Models;

use App\Enums\NotificationType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Notification extends Model
{
    protected $fillable = [
        'type',
        'message',
        'reporter_id',
        'notifiable_id',
        'notifiable_type',
    ];

    protected function casts(): array
    {
        return [
            'type' => NotificationType::class,
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }

    public function recipients(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'notification_user')
            ->withPivot('read_at')
            ->withTimestamps();
    }

    public function scopeErrorReports($query)
    {
        return $query->where('type', NotificationType::ERROR_REPORT);
    }

    public function getResourceTypeAttribute(): string
    {
        return match ($this->notifiable_type) {
            Music::class => 'music',
            Collection::class => 'collection',
            Author::class => 'author',
            default => class_basename($this->notifiable_type),
        };
    }

    public function getResourceTitleAttribute(): ?string
    {
        return $this->notifiable?->title ?? $this->notifiable?->name ?? null;
    }
}
```

### 2. `User` Model Additions
Add relationships to `app/Models/User.php`:

```php
public function reportedNotifications(): HasMany
{
    return $this->hasMany(Notification::class, 'reporter_id');
}

public function receivedNotifications(): BelongsToMany
{
    return $this->belongsToMany(Notification::class, 'notification_user')
        ->withPivot('read_at')
        ->withTimestamps()
        ->orderByPivot('created_at', 'desc');
}

public function unreadNotifications(): BelongsToMany
{
    return $this->receivedNotifications()->wherePivotNull('read_at');
}
```

## Enums

### `NotificationType` Enum
Located at `app/Enums/NotificationType.php`

```php
<?php

namespace App\Enums;

enum NotificationType: string
{
    case ERROR_REPORT = 'error_report';
    // Future expansion: SYSTEM = 'system', MENTION = 'mention', etc.
}
```

## Service Class

### `NotificationService`
Located at `app/Services/NotificationService.php`

```php
<?php

namespace App\Services;

use App\Enums\NotificationType;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class NotificationService
{
    public function createErrorReport(User $reporter, Model $resource, string $message): Notification
    {
        return DB::transaction(function () use ($reporter, $resource, $message) {
            $notification = Notification::create([
                'type' => NotificationType::ERROR_REPORT,
                'message' => $message,
                'reporter_id' => $reporter->id,
                'notifiable_id' => $resource->id,
                'notifiable_type' => $resource->getMorphClass(),
            ]);

            $recipients = $this->getRecipientsForErrorReport($resource);
            $notification->recipients()->attach($recipients);

            // Dispatch event for real-time updates
            event(new \App\Events\NotificationCreated($notification));

            return $notification;
        });
    }

    protected function getRecipientsForErrorReport(Model $resource): array
    {
        $recipients = [];

        // Resource owner
        if ($resource->user_id && $owner = User::find($resource->user_id)) {
            $recipients[$owner->id] = ['created_at' => now()];
        }

        // Admin users (assuming admin role exists)
        $admins = User::role('admin')->get();
        foreach ($admins as $admin) {
            $recipients[$admin->id] = ['created_at' => now()];
        }

        return $recipients;
    }

    public function markAsRead(Notification $notification, User $user): void
    {
        $notification->recipients()->updateExistingPivot($user->id, [
            'read_at' => now(),
        ]);
    }

    public function markAllAsRead(User $user): void
    {
        $user->unreadNotifications()->update(['read_at' => now()]);
    }

    public function getUnreadCount(User $user): int
    {
        return $user->unreadNotifications()->count();
    }

    public function getNotificationsForUser(User $user, int $limit = 50)
    {
        return $user->receivedNotifications()
            ->with(['reporter', 'notifiable'])
            ->orderByPivot('created_at', 'desc')
            ->paginate($limit);
    }
}
```

## Migration Strategy

### 1. Create Migrations
Run `php artisan make:migration create_notifications_table` and `php artisan make:migration create_notification_user_table`.

### 2. Migration Files Content

**`create_notifications_table`:**
```php
public function up(): void
{
    Schema::create('notifications', function (Blueprint $table) {
        $table->id();
        $table->string('type', 50)->default('error_report');
        $table->string('message', 160);
        $table->foreignId('reporter_id')->nullable()->constrained('users')->nullOnDelete();
        $table->unsignedBigInteger('notifiable_id');
        $table->string('notifiable_type');
        $table->timestamps();

        $table->index(['notifiable_type', 'notifiable_id']);
        $table->index('reporter_id');
        $table->index('created_at');
    });
}
```

**`create_notification_user_table`:**
```php
public function up(): void
{
    Schema::create('notification_user', function (Blueprint $table) {
        $table->id();
        $table->foreignId('notification_id')->constrained()->cascadeOnDelete();
        $table->foreignId('user_id')->constrained()->cascadeOnDelete();
        $table->timestamp('read_at')->nullable();
        $table->timestamps();

        $table->unique(['notification_id', 'user_id']);
        $table->index(['user_id', 'read_at']);
        $table->index('user_id');
    });
}
```

### 3. Run Migrations
Execute `php artisan migrate`.

## Frontend Components

### Livewire Notification Bell Component
A real‑time notification bell that shows unread count and dispatches events.

**Location:** `app/Livewire/Components/NotificationBell.php`

**Features:**
- Displays unread count
- List of recent notifications
- Mark as read on click
- Real‑time updates via Livewire events

### Notification List Page
A dedicated page (`/notifications`) to view all notifications with pagination.

## Testing Strategy

### Unit Tests
- `NotificationTest`: Test model relationships and scopes.
- `NotificationServiceTest`: Test creation, recipient assignment, marking as read.

### Feature Tests
- `NotificationControllerTest`: Test API endpoints (if any).
- `NotificationLivewireTest`: Test Livewire components.

## Performance Considerations

1. **Indexes:** All foreign keys and query columns are indexed.
2. **Pagination:** Notifications are paginated to avoid loading large datasets.
3. **Eager Loading:** Use `with(['reporter', 'notifiable'])` to prevent N+1 queries.
4. **Real‑time Updates:** Use Livewire events for minimal overhead.

## Future Extensions

1. **Notification Preferences:** Allow users to opt‑out of certain notification types.
2. **Email Notifications:** Queue emails for error reports.
3. **Notification Categories:** Support system announcements, mentions, etc.
4. **Archiving:** Soft‑delete old notifications after a retention period.

## Implementation Steps

1. Create migrations and run them.
2. Create `NotificationType` enum.
3. Create `Notification` model with relationships.
4. Update `User` model with notification relationships.
5. Create `NotificationService`.
6. Create Livewire components for UI.
7. Add "Report Error" button on resource pages.
8. Create notification list page.
9. Write tests.

## Dependencies
- Laravel 12
- Livewire 4 (for frontend components)
- Spatie Permission (for admin role detection)
- PostgreSQL (current database)

This design is ready for implementation in Code mode.