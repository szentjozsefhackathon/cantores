<?php

use App\Models\Music;
use App\Models\MusicVerification;
use App\Models\User;
use App\Policies\MusicPolicy;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->policy = new MusicPolicy;
});

it('allows user to change their own non-verified music to private', function () {
    $user = User::factory()->create();
    $music = Music::factory()->create([
        'user_id' => $user->id,
    ]);

    // No verification records, so music is not verified
    // $music->is_verified will be false

    $result = $this->policy->changePublishedToPrivate($user, $music);

    expect($result)->toBeTrue();
});

it('denies user to change verified music to private without permission', function () {
    $user = User::factory()->create();
    $music = Music::factory()->create([
        'user_id' => $user->id,
    ]);

    // Create a verified verification for the title field
    MusicVerification::factory()->for($music)->verified()->forField('title')->create();

    $result = $this->policy->changePublishedToPrivate($user, $music);

    expect($result)->toBeFalse();
});

it('allows user with content.edit.verified permission to change verified music to private', function () {
    $user = User::factory()->create();
    $permission = Permission::firstOrCreate(['name' => 'content.edit.verified']);
    $user->givePermissionTo($permission);

    $music = Music::factory()->create([
        'user_id' => $user->id,
    ]);

    // Create a verified verification for the title field
    MusicVerification::factory()->for($music)->verified()->forField('title')->create();

    $result = $this->policy->changePublishedToPrivate($user, $music);

    expect($result)->toBeTrue();
});

it('denies user to change other users non-verified music to private', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $music = Music::factory()->create([
        'user_id' => $user1->id,
    ]);

    // No verification records, so music is not verified

    $result = $this->policy->changePublishedToPrivate($user2, $music);

    expect($result)->toBeFalse();
});

it('allows user with content.edit.verified permission to change other users music to private', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $permission = Permission::firstOrCreate(['name' => 'content.edit.verified']);
    $user2->givePermissionTo($permission);

    $music = Music::factory()->create([
        'user_id' => $user1->id,
    ]);

    // Create a verified verification for the title field
    MusicVerification::factory()->for($music)->verified()->forField('title')->create();

    $result = $this->policy->changePublishedToPrivate($user2, $music);

    expect($result)->toBeTrue();
});

it('returns false for null user', function () {
    $music = Music::factory()->create();

    $result = $this->policy->changePublishedToPrivate(null, $music);

    expect($result)->toBeFalse();
});
