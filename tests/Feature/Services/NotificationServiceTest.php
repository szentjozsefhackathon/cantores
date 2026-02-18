<?php

use App\Enums\NotificationType;
use App\Models\Music;
use App\Models\Notification;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    // Should have recipient: admin (owner not present)
    expect($notification->recipients)->toHaveCount(1);
    expect($notification->recipients->first()->id)->toBe($admin->id);
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
    expect($notification->recipients)->toHaveCount(2);
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
