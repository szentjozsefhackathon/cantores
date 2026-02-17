<?php

use App\Models\Notification;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('notifications page renders with paginated notifications', function () {
    Notification::factory()->count(15)->create()->each(function ($notification) {
        $notification->recipients()->attach($this->user);
    });

    Livewire::test(\App\Livewire\Pages\Notifications::class)
        ->assertViewHas('notifications', function ($notifications) {
            return $notifications->count() === 15 && $notifications instanceof \Illuminate\Pagination\LengthAwarePaginator;
        });
});

test('mark as read updates notification and dispatches event', function () {
    $notification = Notification::factory()->create();
    $notification->recipients()->attach($this->user, ['read_at' => null]);

    expect($notification->isReadBy($this->user))->toBeFalse();

    Livewire::test(\App\Livewire\Pages\Notifications::class)
        ->call('markAsRead', $notification->id)
        ->assertDispatched('notifications-read');

    expect($notification->fresh()->isReadBy($this->user))->toBeTrue();
});

test('mark all as read updates all unread notifications and dispatches event', function () {
    $notification1 = Notification::factory()->create();
    $notification2 = Notification::factory()->create();
    $notification1->recipients()->attach($this->user, ['read_at' => null]);
    $notification2->recipients()->attach($this->user, ['read_at' => null]);

    expect($this->user->unreadNotifications)->toHaveCount(2);

    Livewire::test(\App\Livewire\Pages\Notifications::class)
        ->call('markAllAsRead')
        ->assertDispatched('notifications-read');

    expect($this->user->fresh()->unreadNotifications)->toHaveCount(0);
});

test('mark as read does nothing for invalid notification', function () {
    Livewire::test(\App\Livewire\Pages\Notifications::class)
        ->call('markAsRead', 999999)
        ->assertNotDispatched('notifications-read');
});

test('mark all as read does nothing for guest', function () {
    auth()->logout();

    Livewire::test(\App\Livewire\Pages\Notifications::class)
        ->call('markAllAsRead')
        ->assertNotDispatched('notifications-read');
});
