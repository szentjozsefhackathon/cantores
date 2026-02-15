<?php

use App\Models\Celebration;
use App\Models\MusicPlan;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('can create liturgical celebration', function () {
    $celebration = Celebration::factory()->liturgical()->create([
        'celebration_key' => 1,
        'actual_date' => '2026-12-25',
        'name' => 'Christmas Day',
    ]);

    expect($celebration->is_custom)->toBeFalse()
        ->and($celebration->user_id)->toBeNull()
        ->and($celebration->name)->toBe('Christmas Day');
});

test('can create custom celebration', function () {
    $celebration = Celebration::factory()->custom()->create([
        'celebration_key' => 0,
        'actual_date' => '2026-05-10',
        'name' => 'Birthday Mass for my father',
        'user_id' => $this->user->id,
    ]);

    expect($celebration->is_custom)->toBeTrue()
        ->and($celebration->user_id)->toBe($this->user->id)
        ->and($celebration->name)->toBe('Birthday Mass for my father')
        ->and($celebration->season)->toBeNull();
});

test('liturgical scope excludes custom celebrations', function () {
    Celebration::factory()->liturgical()->create();
    Celebration::factory()->custom()->create(['user_id' => $this->user->id]);

    $liturgical = Celebration::liturgical()->get();
    $custom = Celebration::custom()->get();

    expect($liturgical)->toHaveCount(1)
        ->and($custom)->toHaveCount(1)
        ->and($liturgical->first()->is_custom)->toBeFalse()
        ->and($custom->first()->is_custom)->toBeTrue();
});

test('forUser scope returns custom celebrations for that user', function () {
    $otherUser = User::factory()->create();
    Celebration::factory()->custom()->create(['user_id' => $this->user->id]);
    Celebration::factory()->custom()->create(['user_id' => $otherUser->id]);
    Celebration::factory()->liturgical()->create(); // no user

    $forUser = Celebration::forUser($this->user)->get();
    expect($forUser)->toHaveCount(1)
        ->and($forUser->first()->user_id)->toBe($this->user->id);
});

test('custom celebration can be attached to music plan', function () {
    $celebration = Celebration::factory()->custom()->create(['user_id' => $this->user->id]);
    $musicPlan = MusicPlan::factory()->create(['user_id' => $this->user->id]);

    $musicPlan->celebrations()->attach($celebration);

    expect($musicPlan->celebrations)->toHaveCount(1)
        ->and($musicPlan->customCelebrations)->toHaveCount(1)
        ->and($musicPlan->liturgicalCelebrations)->toHaveCount(0);
});

test('liturgical celebration can be attached to music plan', function () {
    $celebration = Celebration::factory()->liturgical()->create();
    $musicPlan = MusicPlan::factory()->create(['user_id' => $this->user->id]);

    $musicPlan->celebrations()->attach($celebration);

    expect($musicPlan->celebrations)->toHaveCount(1)
        ->and($musicPlan->liturgicalCelebrations)->toHaveCount(1)
        ->and($musicPlan->customCelebrations)->toHaveCount(0);
});
