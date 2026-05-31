<?php

namespace App\Services;

use App\Enums\NotificationType;
use App\Models\Notification;
use App\Models\NotificationReply;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class NotificationService
{
    /**
     * Create an error report notification.
     */
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

            // Dispatch event for real-time updates (optional)
            // event(new \App\Events\NotificationCreated($notification));

            return $notification;
        });
    }

    /**
     * Determine recipients for an error report.
     */
    protected function getRecipientsForErrorReport(Model $resource): array
    {
        $recipients = [];

        // Resource owner
        if ($resource->user_id && $owner = User::find($resource->user_id)) {
            $recipients[$owner->id] = ['created_at' => now()];
        }

        // Admin users (where is_admin is true)
        $admins = User::all()->filter(fn (User $user) => $user->is_admin);
        foreach ($admins as $admin) {
            $recipients[$admin->id] = ['created_at' => now()];
        }

        return $recipients;
    }

    /**
     * Add a reply to a notification and deliver it to everyone else in the thread.
     */
    public function reply(Notification $notification, User $author, string $body): NotificationReply
    {
        return DB::transaction(function () use ($notification, $author, $body) {
            $reply = $notification->replies()->create([
                'user_id' => $author->id,
                'body' => $body,
            ]);

            // Make sure the author is part of the conversation, with their own copy read.
            $notification->recipients()->syncWithoutDetaching([
                $author->id => ['created_at' => now()],
            ]);
            $notification->recipients()->updateExistingPivot($author->id, [
                'read_at' => now(),
            ]);

            // Make sure the original reporter is part of the conversation.
            if ($notification->reporter_id && $notification->reporter_id !== $author->id) {
                $notification->recipients()->syncWithoutDetaching([
                    $notification->reporter_id => ['created_at' => now()],
                ]);
            }

            // Notify everyone else in the thread by marking their copy unread.
            $notification->recipients()
                ->newPivotStatement()
                ->where('notification_id', $notification->id)
                ->where('user_id', '!=', $author->id)
                ->update(['read_at' => null]);

            return $reply;
        });
    }

    /**
     * Mark a notification as read for a user.
     */
    public function markAsRead(Notification $notification, User $user): void
    {
        $notification->recipients()->updateExistingPivot($user->id, [
            'read_at' => now(),
        ]);
    }

    /**
     * Mark all unread notifications as read for a user.
     */
    public function markAllAsRead(User $user): void
    {
        \Illuminate\Support\Facades\DB::table('notification_user')
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    /**
     * Get the count of unread notifications for a user.
     */
    public function getUnreadCount(User $user): int
    {
        return $user->unreadNotifications()->count();
    }

    /**
     * Get paginated notifications for a user.
     */
    public function getNotificationsForUser(User $user, int $limit = 50)
    {
        return $user->receivedNotifications()
            ->with(['reporter', 'notifiable', 'recipients', 'replies.user.firstName', 'replies.user.city'])
            ->orderByPivot('created_at', 'desc')
            ->paginate($limit);
    }

    /**
     * Delete a notification (soft delete if implemented).
     */
    public function delete(Notification $notification): bool
    {
        return $notification->delete();
    }

    /**
     * Create a contact message notification.
     */
    public function createContactMessage(User $sender, string $subject, string $message): Notification
    {
        return DB::transaction(function () use ($sender, $subject, $message) {
            $notification = Notification::create([
                'type' => NotificationType::CONTACT_MESSAGE,
                'message' => $subject.': '.$message,
                'reporter_id' => $sender->id,
                'notifiable_id' => null,
                'notifiable_type' => null,
            ]);

            $recipients = $this->getRecipientsForContactMessage();
            $notification->recipients()->attach($recipients);

            return $notification;
        });
    }

    /**
     * Determine recipients for a contact message (admin users).
     */
    protected function getRecipientsForContactMessage(): array
    {
        $recipients = [];

        // Admin users (where is_admin is true)
        $admins = User::all()->filter(fn (User $user) => $user->is_admin);
        foreach ($admins as $admin) {
            $recipients[$admin->id] = ['created_at' => now()];
        }

        return $recipients;
    }
}
