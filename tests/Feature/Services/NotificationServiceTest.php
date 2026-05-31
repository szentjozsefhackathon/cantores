<?php

use App\Enums\NotificationType;
use App\Mail\InSystemNotificationMail;
use App\Models\Music;
use App\Models\Notification;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Ensure admin role exists
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
});

test('create error report creates notification with recipients', function () {
    $reporter = User::factory()->create();
    $resource = Music::factory()->create(['user_id' => null]); // no owner
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $service = new NotificationService;
    $notification = $service->createErrorReport($reporter, $resource, 'Test error message');

    expect($notification)->toBeInstanceOf(Notification::class);
    expect($notification->type)->toBe(NotificationType::ERROR_REPORT);
    expect($notification->message)->toBe('Test error message');
    expect($notification->reporter_id)->toBe($reporter->id);
    expect($notification->notifiable_id)->toBe($resource->id);
    expect($notification->notifiable_type)->toBe(Music::class);

    // Should have recipient: admin (owner not present) - includes seeded admin
    expect($notification->recipients)->toHaveCount(2);
    expect($notification->recipients->pluck('id')->toArray())->toContain($admin->id);
});

test('create error report includes resource owner as recipient', function () {
    $reporter = User::factory()->create();
    $owner = User::factory()->create();
    $resource = Music::factory()->create(['user_id' => $owner->id]);
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $service = new NotificationService;
    $notification = $service->createErrorReport($reporter, $resource, 'Test error');

    $recipientIds = $notification->recipients->pluck('id')->toArray();
    expect($recipientIds)->toContain($owner->id);
    expect($recipientIds)->toContain($admin->id);
    // Includes seeded admin as well
    expect($notification->recipients)->toHaveCount(3);
});

test('create error report queues email notifications for opted-in recipients', function () {
    Mail::fake();

    $reporter = User::factory()->create();
    $owner = User::factory()->create(['email_notifications_enabled' => false]);
    $resource = Music::factory()->create(['user_id' => $owner->id]);
    $admin = User::factory()->create(['email_notifications_enabled' => true]);
    $admin->assignRole('admin');

    $service = new NotificationService;
    $notification = $service->createErrorReport($reporter, $resource, 'Test error');

    Mail::assertQueued(InSystemNotificationMail::class, function (InSystemNotificationMail $mail) use ($admin, $notification) {
        return $mail->hasTo($admin->email)
            && $mail->notification->is($notification)
            && $mail->recipient->is($admin)
            && $mail->messageSender->is($notification->reporter)
            && $mail->reply === null;
    });

    Mail::assertNotQueued(InSystemNotificationMail::class, function (InSystemNotificationMail $mail) use ($owner) {
        return $mail->hasTo($owner->email);
    });
});

test('create error report does not queue email notifications to the message sender', function () {
    Mail::fake();

    $reporter = User::factory()->create(['email_notifications_enabled' => true]);
    $reporter->assignRole('admin');
    $resource = Music::factory()->create(['user_id' => $reporter->id]);
    $admin = User::factory()->create(['email_notifications_enabled' => true]);
    $admin->assignRole('admin');

    $service = new NotificationService;
    $notification = $service->createErrorReport($reporter, $resource, 'Test error');

    expect($notification->recipients->pluck('id')->toArray())->toContain($reporter->id);

    Mail::assertQueued(InSystemNotificationMail::class, function (InSystemNotificationMail $mail) use ($admin) {
        return $mail->hasTo($admin->email);
    });

    Mail::assertNotQueued(InSystemNotificationMail::class, function (InSystemNotificationMail $mail) use ($reporter) {
        return $mail->hasTo($reporter->email);
    });
});

test('create contact message does not queue email notifications to the message sender', function () {
    Mail::fake();

    $sender = User::factory()->create(['email_notifications_enabled' => true]);
    $sender->assignRole('admin');
    $admin = User::factory()->create(['email_notifications_enabled' => true]);
    $admin->assignRole('admin');

    $service = new NotificationService;
    $notification = $service->createContactMessage($sender, 'Question', 'Can you help?');

    expect($notification->recipients->pluck('id')->toArray())->toContain($sender->id);

    Mail::assertQueued(InSystemNotificationMail::class, function (InSystemNotificationMail $mail) use ($admin, $sender, $notification) {
        return $mail->hasTo($admin->email)
            && $mail->notification->is($notification)
            && $mail->messageSender->is($sender);
    });

    Mail::assertNotQueued(InSystemNotificationMail::class, function (InSystemNotificationMail $mail) use ($sender) {
        return $mail->hasTo($sender->email);
    });
});

test('reply queues email notifications for other opted-in thread recipients', function () {
    Mail::fake();

    $notification = Notification::factory()->create();
    $author = User::factory()->create();
    $recipient = User::factory()->create(['email_notifications_enabled' => true]);
    $mutedRecipient = User::factory()->create(['email_notifications_enabled' => false]);

    $notification->recipients()->attach([
        $author->id => ['read_at' => null],
        $recipient->id => ['read_at' => null],
        $mutedRecipient->id => ['read_at' => null],
    ]);

    $service = new NotificationService;
    $reply = $service->reply($notification, $author, 'Thanks for the report.');

    Mail::assertQueued(InSystemNotificationMail::class, function (InSystemNotificationMail $mail) use ($recipient, $notification, $reply) {
        return $mail->hasTo($recipient->email)
            && $mail->notification->is($notification)
            && $mail->recipient->is($recipient)
            && $mail->messageSender->is($reply->user)
            && $mail->reply->is($reply);
    });

    Mail::assertNotQueued(InSystemNotificationMail::class, function (InSystemNotificationMail $mail) use ($author, $mutedRecipient) {
        return $mail->hasTo($author->email) || $mail->hasTo($mutedRecipient->email);
    });
});

test('reply email renders the reply author as the from user', function () {
    $threadStarter = User::factory()->create();
    $replyAuthor = User::factory()->create();
    $recipient = User::factory()->create();
    $notification = Notification::factory()->reportedBy($threadStarter)->create([
        'message' => 'Original thread message',
    ]);
    $reply = $notification->replies()->create([
        'user_id' => $replyAuthor->id,
        'body' => 'Latest reply message',
    ]);

    $html = (new InSystemNotificationMail($notification, $recipient, $replyAuthor, $reply))->render();

    expect($html)
        ->toContain($replyAuthor->display_name)
        ->not->toContain($threadStarter->display_name);
});

test('reply email view falls back to the reply author when message sender is unavailable', function () {
    $threadStarter = User::factory()->create();
    $replyAuthor = User::factory()->create();
    $recipient = User::factory()->create();
    $notification = Notification::factory()->reportedBy($threadStarter)->create([
        'message' => 'Original thread message',
    ]);
    $reply = $notification->replies()->create([
        'user_id' => $replyAuthor->id,
        'body' => 'Latest reply message',
    ]);

    $html = view('mail.in-system-notification', [
        'notification' => $notification,
        'recipient' => $recipient,
        'reply' => $reply,
    ])->render();

    expect($html)
        ->toContain($replyAuthor->display_name)
        ->not->toContain($threadStarter->display_name);
});

test('create error report runs in transaction', function () {
    $reporter = User::factory()->create();
    $resource = Music::factory()->create();
    $service = new NotificationService;

    DB::shouldReceive('transaction')->once()->andReturnUsing(fn ($callback) => $callback());

    $notification = $service->createErrorReport($reporter, $resource, 'Test');
    // If we reach here, transaction was called (mocked)
})->skip('Mocking DB transaction is complex; we can rely on integration test');

test('mark as read updates pivot', function () {
    $notification = Notification::factory()->create();
    $user = User::factory()->create();
    $notification->recipients()->attach($user, ['read_at' => null]);

    $service = new NotificationService;
    $service->markAsRead($notification, $user);

    expect($notification->isReadBy($user))->toBeTrue();
});

test('mark all as read updates all unread notifications for user', function () {
    $user = User::factory()->create();
    $notification1 = Notification::factory()->create();
    $notification2 = Notification::factory()->create();
    $notification1->recipients()->attach($user, ['read_at' => null]);
    $notification2->recipients()->attach($user, ['read_at' => null]);

    $service = new NotificationService;
    $service->markAllAsRead($user);

    expect($user->unreadNotifications)->toHaveCount(0);
});

test('get unread count returns correct number', function () {
    $user = User::factory()->create();
    Notification::factory()->count(3)->create()->each(function ($notification) use ($user) {
        $notification->recipients()->attach($user, ['read_at' => null]);
    });
    Notification::factory()->create()->recipients()->attach($user, ['read_at' => now()]);

    $service = new NotificationService;
    $count = $service->getUnreadCount($user);

    expect($count)->toBe(3);
});

test('get notifications for user returns paginated results', function () {
    $user = User::factory()->create();
    $notifications = Notification::factory()->count(5)->create();
    foreach ($notifications as $notification) {
        $notification->recipients()->attach($user);
    }

    $service = new NotificationService;
    $paginator = $service->getNotificationsForUser($user, 2);

    expect($paginator)->toBeInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class);
    expect($paginator->count())->toBe(2);
    expect($paginator->total())->toBe(5);
});

test('delete notification soft deletes if implemented', function () {
    $notification = Notification::factory()->create();
    $service = new NotificationService;

    $result = $service->delete($notification);
    expect($result)->toBeTrue();
    expect(Notification::find($notification->id))->toBeNull();
});
