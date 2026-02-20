<?php

use App\Models\Notification;
use App\Models\User;
use App\Services\NotificationService;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('notification bell shows unread count', function () {
    Notification::factory()->count(3)->create()->each(function ($notification) {
        $notification->recipients()->attach($this->user, ['read_at' => null]);
    });
    Notification::factory()->create()->recipients()->attach($this->user, ['read_at' => now()]);

    Livewire::test('notification-bell')
        ->assertSet('unreadCount', 3)
        ->assertSee('3');
});

test('notification bell increments on notification-created event', function () {
    Livewire::test('notification-bell')
        ->set('unreadCount', 5)
        ->dispatch('notification-created')
        ->assertSet('unreadCount', 6);
});

test('notification bell resets on notifications-read event', function () {
    Livewire::test('notification-bell')
        ->set('unreadCount', 5)
        ->dispatch('notifications-read')
        ->assertSet('unreadCount', 0);
});

test('notification bell mounts with zero for guest', function () {
    auth()->logout();

    Livewire::test('notification-bell')
        ->assertSet('unreadCount', 0);
});

test('notification bell uses notification service', function () {
    $this->mock(NotificationService::class, function ($mock) {
        $mock->shouldReceive('getUnreadCount')
            ->with($this->user)
            ->once()
            ->andReturn(7);
    });

    Livewire::test('notification-bell')
        ->assertSet('unreadCount', 7);
});
