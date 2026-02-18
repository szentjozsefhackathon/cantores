<?php

use App\Models\Music;
use App\Models\Notification;
use App\Models\User;
use App\Services\NotificationService;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    // Create admin role if it doesn't exist
    if (! \Spatie\Permission\Models\Role::where('name', 'admin')->exists()) {
        \Spatie\Permission\Models\Role::create(['name' => 'admin', 'guard_name' => 'web']);
    }

    // Ensure admin user exists with correct email
    $this->admin = User::firstOrCreate(
        ['email' => config('admin.email')],
        User::factory()->raw(['email' => config('admin.email')])
    );
    $this->admin->assignRole('admin');
});

test('notification bell updates after error report', function () {
    $music = Music::factory()->create();

    $bell = Livewire::test(\App\Livewire\Components\NotificationBell::class);
    $bell->assertSet('unreadCount', 0);

    // Create error report
    $service = new NotificationService;
    $service->createErrorReport($this->user, $music, 'Test');

    // Bell should reflect new unread count (for admin, not for reporter? Actually reporter is also recipient if owner)
    // Since the user is not owner (music has no owner), only admin receives notification.
    // The user is not recipient, so unread count remains 0.
    // Let's test that the bell updates via event
    $bell->dispatch('notification-created')
        ->assertSet('unreadCount', 1);
});

test('notifications page shows reported error', function () {
    $music = Music::factory()->create();

    $service = new NotificationService;
    $notification = $service->createErrorReport($this->user, $music, 'Test error');

    // Attach notification to admin (already done) and also to user if owner
    // Now visit notifications page as admin
    $this->actingAs($this->admin);
    Livewire::test(\App\Livewire\Pages\Notifications::class)
        ->assertViewHas('notifications', function ($notifications) use ($notification) {
            return $notifications->contains('id', $notification->id);
        });
});

test('mark as read removes from unread count', function () {
    $music = Music::factory()->create();

    $service = new NotificationService;
    $notification = $service->createErrorReport($this->user, $music, 'Test');

    $this->actingAs($this->admin);
    $bell = Livewire::test(\App\Livewire\Components\NotificationBell::class);
    $bell->assertSet('unreadCount', 1);

    Livewire::test(\App\Livewire\Pages\Notifications::class)
        ->call('markAsRead', $notification->id);

    $bell->dispatch('notifications-read')
        ->assertSet('unreadCount', 0);
});

test('error report creates notification with correct recipients', function () {
    $music = Music::factory()->create(['user_id' => $this->user->id]);

    $service = new NotificationService;
    $notification = $service->createErrorReport($this->user, $music, 'This music has incorrect title');

    expect($notification)->not->toBeNull();
    expect($notification->type)->toBe(\App\Enums\NotificationType::ERROR_REPORT);
    expect($notification->message)->toBe('This music has incorrect title');
    expect($notification->reporter_id)->toBe($this->user->id);
    expect($notification->notifiable_id)->toBe($music->id);
    expect($notification->notifiable_type)->toBe(Music::class);

    // Recipients: owner (user) and admin
    expect($notification->recipients)->toHaveCount(2);
    expect($notification->recipients->pluck('id')->toArray())->toContain($this->user->id, $this->admin->id);
});

test('error report for resource without owner only notifies admin', function () {
    $music = Music::factory()->create(['user_id' => null]);

    $service = new NotificationService;
    $notification = $service->createErrorReport($this->user, $music, 'Test');

    // Only admin should be recipient
    expect($notification->recipients)->toHaveCount(1);
    expect($notification->recipients->first()->id)->toBe($this->admin->id);
});
