<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create();
    $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $this->admin->assignRole($adminRole);

    $this->targetUser = User::factory()->create(['blocked' => false, 'blocked_at' => null]);
});

test('admin can access users page', function () {
    Livewire::actingAs($this->admin)
        ->test('pages::admin.users')
        ->assertStatus(200)
        ->assertSee(__('Users'));
});

test('non-admin cannot access users page', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::admin.users')
        ->assertForbidden();
});

test('guest cannot access users page', function () {
    Livewire::test('pages::admin.users')
        ->assertForbidden();
});

test('admin can block a user', function () {
    Livewire::actingAs($this->admin)
        ->test('pages::admin.users')
        ->call('blockUser', $this->targetUser->id)
        ->assertDispatched('success');

    expect($this->targetUser->fresh()->blocked)->toBeTrue();
    expect($this->targetUser->fresh()->blocked_at)->not->toBeNull();
});

test('admin can unblock a blocked user', function () {
    $this->targetUser->update(['blocked' => true, 'blocked_at' => now()]);

    Livewire::actingAs($this->admin)
        ->test('pages::admin.users')
        ->call('unblockUser', $this->targetUser->id)
        ->assertDispatched('success');

    expect($this->targetUser->fresh()->blocked)->toBeFalse();
    expect($this->targetUser->fresh()->blocked_at)->toBeNull();
});

test('admin cannot block themselves', function () {
    Livewire::actingAs($this->admin)
        ->test('pages::admin.users')
        ->call('blockUser', $this->admin->id)
        ->assertDispatched('error');

    expect($this->admin->fresh()->blocked)->toBeFalse();
});

test('users table shows blocked status', function () {
    User::factory()->create(['blocked' => true, 'blocked_at' => now()]);

    Livewire::actingAs($this->admin)
        ->test('pages::admin.users')
        ->assertSee(__('Blocked'))
        ->assertSee(__('Active'));
});
