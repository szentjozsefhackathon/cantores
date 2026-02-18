<?php

use App\Models\Music;
use App\Models\User;
use App\Services\NotificationService;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('error report component can be instantiated', function () {
    // Test basic component loading
    Livewire::test('error-report')
        ->assertSet('showModal', false)
        ->assertSet('message', '');
});


test('close modal resets state', function () {
    Livewire::test('error-report')
        ->set('showModal', true)
        ->set('message', 'test')
        ->call('closeModal')
        ->assertSet('showModal', false)
        ->assertSet('message', '');
});

test('submit validates required message', function () {
    // We'll test validation without resource
    Livewire::test('error-report')
        ->set('message', '')
        ->call('submit')
        ->assertHasErrors(['message' => 'required']);
});

test('submit validates max length 160', function () {
    $longMessage = str_repeat('a', 161);

    Livewire::test('error-report')
        ->set('message', $longMessage)
        ->call('submit')
        ->assertHasErrors(['message' => 'max']);
});


// Integration test: test that the component actually creates notifications
test('component creates notification when submitted with resource', function () {
    $music = Music::factory()->create();
    $admin = User::factory()->create(['email' => \Config::get('admin.email')]);

    // Create a user who will receive the notification (resource owner)
    $owner = User::factory()->create();
    $music->update(['user_id' => $owner->id]);

    // We need to test this as an integration test since mocking is difficult
    // due to app(NotificationService::class) usage
    Livewire::test('error-report')
        ->call('openModal', resourceId: $music->id, resourceType: 'music')
        ->set('message', 'Test error message')
        ->call('submit');

    // Check that a notification was created
    $notification = \App\Models\Notification::first();
    expect($notification)->not->toBeNull()
        ->and($notification->type)->toBe(\App\Enums\NotificationType::ERROR_REPORT)
        ->and($notification->message)->toBe('Test error message')
        ->and($notification->notifiable_type)->toBe(\App\Models\Music::class)
        ->and($notification->notifiable_id)->toBe($music->id);
});
