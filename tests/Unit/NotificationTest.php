<?php

use App\Enums\NotificationType;
use App\Models\Author;
use App\Models\Collection;
use App\Models\Music;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

test('notification can be created', function () {
    $user = User::factory()->create();
    $music = Music::factory()->create(['user_id' => $user->id]);

    $notification = Notification::create([
        'type' => NotificationType::ERROR_REPORT,
        'message' => 'Test error message',
        'reporter_id' => $user->id,
        'notifiable_id' => $music->id,
        'notifiable_type' => Music::class,
    ]);

    expect($notification)->toBeInstanceOf(Notification::class);
    expect($notification->type)->toBe(NotificationType::ERROR_REPORT);
    expect($notification->message)->toBe('Test error message');
    expect($notification->reporter_id)->toBe($user->id);
    expect($notification->notifiable_id)->toBe($music->id);
    expect($notification->notifiable_type)->toBe(Music::class);
});

test('notification belongs to reporter', function () {
    $user = User::factory()->create();
    $music = Music::factory()->create();

    $notification = Notification::factory()->create([
        'reporter_id' => $user->id,
        'notifiable_id' => $music->id,
        'notifiable_type' => Music::class,
    ]);

    expect($notification->reporter)->toBeInstanceOf(User::class);
    expect($notification->reporter->id)->toBe($user->id);
});

test('notification morphs to notifiable', function () {
    $music = Music::factory()->create();
    $notification = Notification::factory()->create([
        'notifiable_id' => $music->id,
        'notifiable_type' => Music::class,
    ]);

    expect($notification->notifiable)->toBeInstanceOf(Music::class);
    expect($notification->notifiable->id)->toBe($music->id);
});

test('notification has recipients', function () {
    $notification = Notification::factory()->create();
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $notification->recipients()->attach($user1, ['read_at' => null]);
    $notification->recipients()->attach($user2, ['read_at' => now()]);

    expect($notification->recipients)->toHaveCount(2);
    expect($notification->recipients->pluck('id')->toArray())->toContain($user1->id, $user2->id);
});

test('notification resource type attribute', function () {
    $music = Music::factory()->create();
    $notification = Notification::factory()->create([
        'notifiable_id' => $music->id,
        'notifiable_type' => Music::class,
    ]);

    expect($notification->resource_type)->toBe('music');

    $collection = Collection::factory()->create();
    $notification2 = Notification::factory()->create([
        'notifiable_id' => $collection->id,
        'notifiable_type' => Collection::class,
    ]);

    expect($notification2->resource_type)->toBe('collection');

    $author = Author::factory()->create();
    $notification3 = Notification::factory()->create([
        'notifiable_id' => $author->id,
        'notifiable_type' => Author::class,
    ]);

    expect($notification3->resource_type)->toBe('author');
});

test('notification is read by user', function () {
    $notification = Notification::factory()->create();
    $user = User::factory()->create();

    $notification->recipients()->attach($user, ['read_at' => now()]);

    expect($notification->isReadBy($user))->toBeTrue();

    $user2 = User::factory()->create();
    expect($notification->isReadBy($user2))->toBeFalse();
});

test('notification scope error reports', function () {
    Notification::factory()->count(3)->create(['type' => NotificationType::ERROR_REPORT]);
    // Only ERROR_REPORT type exists, so scope should return all notifications
    $errorReports = Notification::errorReports()->get();
    expect($errorReports)->toHaveCount(3);
    expect($errorReports->pluck('type')->unique()->toArray())->toEqual([NotificationType::ERROR_REPORT]);
});

test('notification resource title attribute', function () {
    $music = Music::factory()->create(['title' => 'Test Music Title']);
    $notification = Notification::factory()->create([
        'notifiable_id' => $music->id,
        'notifiable_type' => Music::class,
    ]);

    expect($notification->resource_title)->toBe('Test Music Title');

    $collection = Collection::factory()->create(['title' => 'Test Collection']);
    $notification2 = Notification::factory()->create([
        'notifiable_id' => $collection->id,
        'notifiable_type' => Collection::class,
    ]);

    expect($notification2->resource_title)->toBe('Test Collection');

    $author = Author::factory()->create(['name' => 'Test Author']);
    $notification3 = Notification::factory()->create([
        'notifiable_id' => $author->id,
        'notifiable_type' => Author::class,
    ]);

    expect($notification3->resource_title)->toBe('Test Author');
});

test('notification mark as read for user', function () {
    $notification = Notification::factory()->create();
    $user = User::factory()->create();
    $notification->recipients()->attach($user, ['read_at' => null]);

    expect($notification->isReadBy($user))->toBeFalse();

    $notification->markAsReadFor($user);

    expect($notification->isReadBy($user))->toBeTrue();
    expect($notification->recipients()->where('user_id', $user->id)->first()->pivot->read_at)->not->toBeNull();
});

test('notification type casting', function () {
    $notification = Notification::factory()->create(['type' => NotificationType::ERROR_REPORT]);

    expect($notification->type)->toBeInstanceOf(NotificationType::class);
    expect($notification->type)->toBe(NotificationType::ERROR_REPORT);
});

test('notification fillable attributes', function () {
    $notification = new Notification;
    $fillable = $notification->getFillable();

    expect($fillable)->toBe([
        'type',
        'message',
        'reporter_id',
        'notifiable_id',
        'notifiable_type',
    ]);
});

test('user reported notifications relationship', function () {
    $user = User::factory()->create();
    $notification = Notification::factory()->create(['reporter_id' => $user->id]);

    expect($user->reportedNotifications)->toHaveCount(1);
    expect($user->reportedNotifications->first()->id)->toBe($notification->id);
});

test('user received notifications relationship', function () {
    $user = User::factory()->create();
    $notification = Notification::factory()->create();
    $notification->recipients()->attach($user);

    expect($user->receivedNotifications)->toHaveCount(1);
    expect($user->receivedNotifications->first()->id)->toBe($notification->id);
});

test('user unread notifications relationship', function () {
    $user = User::factory()->create();
    $notification1 = Notification::factory()->create();
    $notification2 = Notification::factory()->create();
    $notification1->recipients()->attach($user, ['read_at' => null]);
    $notification2->recipients()->attach($user, ['read_at' => now()]);

    expect($user->unreadNotifications)->toHaveCount(1);
    expect($user->unreadNotifications->first()->id)->toBe($notification1->id);
});

test('user unread notifications count attribute', function () {
    $user = User::factory()->create();
    $notification1 = Notification::factory()->create();
    $notification2 = Notification::factory()->create();
    $notification1->recipients()->attach($user, ['read_at' => null]);
    $notification2->recipients()->attach($user, ['read_at' => null]);

    expect($user->unread_notifications_count)->toBe(2);
});
