<?php

use App\Models\Music;
use App\Models\Notification;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'editor'] as $role) {
        if (! Role::where('name', $role)->exists()) {
            Role::create(['name' => $role, 'guard_name' => 'web']);
        }
    }

    $this->reporter = User::factory()->create();
    $this->editor = User::factory()->create();
    $this->editor->assignRole('editor');
});

test('editor can reply to a notification and it reaches the reporter', function () {
    $music = Music::factory()->create();
    $notification = Notification::factory()->forMusic($music)->reportedBy($this->reporter)->create();

    $this->actingAs($this->editor);

    Livewire::test(\App\Livewire\Pages\Notifications::class)
        ->set("replyBodies.{$notification->id}", 'Thanks, we fixed it.')
        ->call('reply', $notification->id)
        ->assertHasNoErrors();

    $notification->refresh();

    expect($notification->replies)->toHaveCount(1);
    expect($notification->replies->first()->body)->toBe('Thanks, we fixed it.');
    expect($notification->replies->first()->user_id)->toBe($this->editor->id);

    expect($this->reporter->fresh()->unreadNotifications()->where('notifications.id', $notification->id)->exists())
        ->toBeTrue();
});

test('an empty reply is rejected', function () {
    $music = Music::factory()->create();
    $notification = Notification::factory()->forMusic($music)->reportedBy($this->reporter)->create();

    $this->actingAs($this->editor);

    Livewire::test(\App\Livewire\Pages\Notifications::class)
        ->set("replyBodies.{$notification->id}", '   ')
        ->call('reply', $notification->id)
        ->assertHasErrors("replyBodies.{$notification->id}");

    expect($notification->replies()->count())->toBe(0);
});

test('a non-editor non-admin user cannot reply', function () {
    $music = Music::factory()->create();
    $notification = Notification::factory()->forMusic($music)->reportedBy($this->reporter)->create();

    $regular = User::factory()->create();
    $this->actingAs($regular);

    Livewire::test(\App\Livewire\Pages\Notifications::class)
        ->set("replyBodies.{$notification->id}", 'Let me try.')
        ->call('reply', $notification->id)
        ->assertForbidden();

    expect($notification->replies()->count())->toBe(0);
});

test('a normal user who received the notification can reply back', function () {
    $music = Music::factory()->create();
    $notification = Notification::factory()->forMusic($music)->reportedBy($this->reporter)->create();
    $notification->recipients()->attach($this->reporter->id, ['created_at' => now()]);

    $this->actingAs($this->reporter);

    Livewire::test(\App\Livewire\Pages\Notifications::class)
        ->set("replyBodies.{$notification->id}", 'Thanks for looking into it.')
        ->call('reply', $notification->id)
        ->assertHasNoErrors();

    $notification->refresh();

    expect($notification->replies)->toHaveCount(1);
    expect($notification->replies->first()->user_id)->toBe($this->reporter->id);
});

test('a reporter reply reaches the editor who replied', function () {
    $music = Music::factory()->create();
    $notification = Notification::factory()->forMusic($music)->reportedBy($this->reporter)->create();
    $notification->recipients()->attach($this->editor->id, ['created_at' => now(), 'read_at' => now()]);

    $this->actingAs($this->reporter);

    Livewire::test(\App\Livewire\Pages\Notifications::class)
        ->set("replyBodies.{$notification->id}", 'Here is more detail.')
        ->call('reply', $notification->id)
        ->assertHasNoErrors();

    expect($this->editor->fresh()->unreadNotifications()->where('notifications.id', $notification->id)->exists())
        ->toBeTrue();
    expect($this->reporter->fresh()->unreadNotifications()->where('notifications.id', $notification->id)->exists())
        ->toBeFalse();
});

test('admins can also reply', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $music = Music::factory()->create();
    $notification = Notification::factory()->forMusic($music)->reportedBy($this->reporter)->create();

    $this->actingAs($admin);

    Livewire::test(\App\Livewire\Pages\Notifications::class)
        ->set("replyBodies.{$notification->id}", 'Looking into it.')
        ->call('reply', $notification->id)
        ->assertHasNoErrors();

    expect($notification->replies()->count())->toBe(1);
});
