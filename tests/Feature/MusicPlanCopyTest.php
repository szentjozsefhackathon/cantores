<?php

use App\Models\Music;
use App\Models\MusicAssignmentFlag;
use App\Models\MusicPlan;
use App\Models\MusicPlanSlot;
use App\Models\MusicPlanSlotAssignment;
use App\Models\User;

test('owner can copy their music plan', function () {
    $user = User::factory()->create();
    $musicPlan = MusicPlan::factory()->create(['user_id' => $user->id]);
    $initialCount = MusicPlan::count();

    $response = $this->actingAs($user)->post(route('music-plans.copy', $musicPlan));

    $response->assertRedirect();
    expect(MusicPlan::count())->toBe($initialCount + 1);
});

test('copied music plan is private', function () {
    $user = User::factory()->create();
    $musicPlan = MusicPlan::factory()->create([
        'user_id' => $user->id,
        'is_private' => false,
    ]);
    $originalCount = MusicPlan::count();

    $this->actingAs($user)->post(route('music-plans.copy', $musicPlan));

    $copiedPlan = MusicPlan::where('user_id', $user->id)->where('id', '!=', $musicPlan->id)->latest()->first();
    expect($copiedPlan->is_private)->toBeTrue();
});

test('copied music plan has same genre', function () {
    $user = User::factory()->create();
    $musicPlan = MusicPlan::factory()->create([
        'user_id' => $user->id,
        'genre_id' => 1,
    ]);

    $this->actingAs($user)->post(route('music-plans.copy', $musicPlan));

    $copiedPlan = MusicPlan::where('id', '!=', $musicPlan->id)->latest()->first();
    expect($copiedPlan->genre_id)->toBe($musicPlan->genre_id);
});

test('copied music plan has same private notes', function () {
    $user = User::factory()->create();
    $notes = 'Test private notes';
    $musicPlan = MusicPlan::factory()->create([
        'user_id' => $user->id,
        'private_notes' => $notes,
    ]);

    $this->actingAs($user)->post(route('music-plans.copy', $musicPlan));

    $copiedPlan = MusicPlan::where('id', '!=', $musicPlan->id)->latest()->first();
    expect($copiedPlan->private_notes)->toBe($notes);
});

test('copied music plan includes all celebrations', function () {
    $user = User::factory()->create();
    $musicPlan = MusicPlan::factory()->create(['user_id' => $user->id]);
    $celebration = $musicPlan->createCustomCelebration('Test Celebration');

    $this->actingAs($user)->post(route('music-plans.copy', $musicPlan));

    $copiedPlan = MusicPlan::where('id', '!=', $musicPlan->id)->latest()->first();
    expect($copiedPlan->celebrations()->count())->toBe(1);
    expect($copiedPlan->celebrations()->first()->name)->toBe('Test Celebration');
});

test('copied music plan includes all slots', function () {
    $user = User::factory()->create();
    $musicPlan = MusicPlan::factory()->create(['user_id' => $user->id]);

    $globalSlot = MusicPlanSlot::factory()->create(['is_custom' => false]);
    $musicPlan->slots()->attach($globalSlot, ['sequence' => 1]);

    $this->actingAs($user)->post(route('music-plans.copy', $musicPlan));

    $copiedPlan = MusicPlan::where('id', '!=', $musicPlan->id)->latest()->first();
    expect($copiedPlan->slots()->count())->toBe(1);
    expect($copiedPlan->slots()->first()->id)->toBe($globalSlot->id);
});

test('copied music plan includes custom slots', function () {
    $user = User::factory()->create();
    $musicPlan = MusicPlan::factory()->create(['user_id' => $user->id]);

    $customSlot = $musicPlan->createCustomSlot([
        'name' => 'Custom Slot',
        'description' => 'Test custom slot',
    ]);
    $musicPlan->slots()->attach($customSlot, ['sequence' => 1]);

    $this->actingAs($user)->post(route('music-plans.copy', $musicPlan));

    $copiedPlan = MusicPlan::where('id', '!=', $musicPlan->id)->latest()->first();
    expect($copiedPlan->slots()->count())->toBe(1);

    $copiedSlot = $copiedPlan->slots()->first();
    expect($copiedSlot->name)->toBe('Custom Slot');
    expect($copiedSlot->description)->toBe('Test custom slot');
    expect($copiedSlot->is_custom)->toBeTrue();
    expect($copiedSlot->music_plan_id)->toBe($copiedPlan->id);
});

test('copied music plan includes all music assignments', function () {
    $user = User::factory()->create();
    $musicPlan = MusicPlan::factory()->create(['user_id' => $user->id]);

    $slot = MusicPlanSlot::factory()->create(['is_custom' => false]);
    $musicPlan->slots()->attach($slot, ['sequence' => 1]);

    $music = Music::factory()->create();
    $pivot = $musicPlan->slots()
        ->where('music_plan_slot_id', $slot->id)
        ->withPivot('id')
        ->first();

    MusicPlanSlotAssignment::create([
        'music_plan_slot_plan_id' => $pivot->pivot->id,
        'music_plan_id' => $musicPlan->id,
        'music_plan_slot_id' => $slot->id,
        'music_id' => $music->id,
        'music_sequence' => 1,
        'notes' => 'Test notes',
    ]);

    $this->actingAs($user)->post(route('music-plans.copy', $musicPlan));

    $copiedPlan = MusicPlan::where('id', '!=', $musicPlan->id)->latest()->first();
    expect($copiedPlan->musicAssignments()->count())->toBe(1);

    $assignment = $copiedPlan->musicAssignments()->first();
    expect($assignment->music_id)->toBe($music->id);
    expect($assignment->notes)->toBe('Test notes');
    expect($assignment->music_sequence)->toBe(1);
});

test('copied music plan includes assignment flags', function () {
    $user = User::factory()->create();
    $musicPlan = MusicPlan::factory()->create(['user_id' => $user->id]);

    $slot = MusicPlanSlot::factory()->create(['is_custom' => false]);
    $musicPlan->slots()->attach($slot, ['sequence' => 1]);

    $music = Music::factory()->create();
    $pivot = $musicPlan->slots()
        ->where('music_plan_slot_id', $slot->id)
        ->withPivot('id')
        ->first();

    $assignment = MusicPlanSlotAssignment::create([
        'music_plan_slot_plan_id' => $pivot->pivot->id,
        'music_plan_id' => $musicPlan->id,
        'music_plan_slot_id' => $slot->id,
        'music_id' => $music->id,
        'music_sequence' => 1,
    ]);

    $flag = MusicAssignmentFlag::first() ?? MusicAssignmentFlag::factory()->create();
    $assignment->flags()->attach($flag);

    $this->actingAs($user)->post(route('music-plans.copy', $musicPlan));

    $copiedPlan = MusicPlan::where('id', '!=', $musicPlan->id)->latest()->first();
    $copiedAssignment = $copiedPlan->musicAssignments()->first();

    expect($copiedAssignment->flags()->count())->toBe(1);
    expect($copiedAssignment->flags()->first()->id)->toBe($flag->id);
});

test('copied music plan includes assignment scopes', function () {
    $user = User::factory()->create();
    $musicPlan = MusicPlan::factory()->create(['user_id' => $user->id]);

    $slot = MusicPlanSlot::factory()->create(['is_custom' => false]);
    $musicPlan->slots()->attach($slot, ['sequence' => 1]);

    $music = Music::factory()->create();
    $pivot = $musicPlan->slots()
        ->where('music_plan_slot_id', $slot->id)
        ->withPivot('id')
        ->first();

    $assignment = MusicPlanSlotAssignment::create([
        'music_plan_slot_plan_id' => $pivot->pivot->id,
        'music_plan_id' => $musicPlan->id,
        'music_plan_slot_id' => $slot->id,
        'music_id' => $music->id,
        'music_sequence' => 1,
    ]);

    $assignment->scopes()->create([
        'scope_type' => 'verse',
        'scope_number' => 1,
    ]);

    $this->actingAs($user)->post(route('music-plans.copy', $musicPlan));

    $copiedPlan = MusicPlan::where('id', '!=', $musicPlan->id)->latest()->first();
    $copiedAssignment = $copiedPlan->musicAssignments()->first();

    expect($copiedAssignment->scopes()->count())->toBe(1);
    $scope = $copiedAssignment->scopes()->first();
    expect($scope->scope_type->value)->toBe('verse');
    expect($scope->scope_number)->toBe(1);
});

test('non-owner cannot copy private music plan', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $musicPlan = MusicPlan::factory()->create([
        'user_id' => $owner->id,
        'is_private' => true,
    ]);

    $response = $this->actingAs($intruder)->post(route('music-plans.copy', $musicPlan));

    $response->assertForbidden();
});

test('any user can copy published music plan', function () {
    $owner = User::factory()->create();
    $copier = User::factory()->create();
    $musicPlan = MusicPlan::factory()->create([
        'user_id' => $owner->id,
        'is_private' => false,
    ]);
    $initialCount = MusicPlan::count();

    $response = $this->actingAs($copier)->post(route('music-plans.copy', $musicPlan));

    $response->assertRedirect();
    expect(MusicPlan::count())->toBe($initialCount + 1);
});

test('copied published plan belongs to copier', function () {
    $owner = User::factory()->create();
    $copier = User::factory()->create();
    $musicPlan = MusicPlan::factory()->create([
        'user_id' => $owner->id,
        'is_private' => false,
    ]);

    $this->actingAs($copier)->post(route('music-plans.copy', $musicPlan));

    $copiedPlan = MusicPlan::where('user_id', $copier->id)->latest()->first();
    expect($copiedPlan)->not->toBeNull();
    expect($copiedPlan->user_id)->toBe($copier->id);
});

test('copied published plan does not include private notes', function () {
    $owner = User::factory()->create();
    $copier = User::factory()->create();
    $musicPlan = MusicPlan::factory()->create([
        'user_id' => $owner->id,
        'is_private' => false,
        'private_notes' => 'Secret notes',
    ]);

    $this->actingAs($copier)->post(route('music-plans.copy', $musicPlan));

    $copiedPlan = MusicPlan::where('user_id', $copier->id)->latest()->first();
    expect($copiedPlan->private_notes)->toBeNull();
});

test('copied published plan excludes custom slots', function () {
    $owner = User::factory()->create();
    $copier = User::factory()->create();
    $musicPlan = MusicPlan::factory()->create([
        'user_id' => $owner->id,
        'is_private' => false,
    ]);

    $customSlot = $musicPlan->createCustomSlot([
        'name' => 'Custom Slot',
        'description' => 'Test custom slot',
    ]);
    $musicPlan->slots()->attach($customSlot, ['sequence' => 1]);

    $globalSlot = MusicPlanSlot::factory()->create(['is_custom' => false]);
    $musicPlan->slots()->attach($globalSlot, ['sequence' => 2]);

    $this->actingAs($copier)->post(route('music-plans.copy', $musicPlan));

    $copiedPlan = MusicPlan::where('user_id', $copier->id)->latest()->first();
    expect($copiedPlan->slots()->count())->toBe(1);
    expect($copiedPlan->slots()->first()->id)->toBe($globalSlot->id);
});

test('copied published plan excludes private music', function () {
    $owner = User::factory()->create();
    $copier = User::factory()->create();
    $musicPlan = MusicPlan::factory()->create([
        'user_id' => $owner->id,
        'is_private' => false,
    ]);

    $slot = MusicPlanSlot::factory()->create(['is_custom' => false]);
    $musicPlan->slots()->attach($slot, ['sequence' => 1]);

    $publicMusic = Music::factory()->create(['is_private' => false]);
    $privateMusic = Music::factory()->create(['is_private' => true]);

    $pivot = $musicPlan->slots()
        ->where('music_plan_slot_id', $slot->id)
        ->withPivot('id')
        ->first();

    MusicPlanSlotAssignment::create([
        'music_plan_slot_plan_id' => $pivot->pivot->id,
        'music_plan_id' => $musicPlan->id,
        'music_plan_slot_id' => $slot->id,
        'music_id' => $publicMusic->id,
        'music_sequence' => 1,
    ]);

    MusicPlanSlotAssignment::create([
        'music_plan_slot_plan_id' => $pivot->pivot->id,
        'music_plan_id' => $musicPlan->id,
        'music_plan_slot_id' => $slot->id,
        'music_id' => $privateMusic->id,
        'music_sequence' => 2,
    ]);

    $this->actingAs($copier)->post(route('music-plans.copy', $musicPlan));

    $copiedPlan = MusicPlan::where('user_id', $copier->id)->latest()->first();
    expect($copiedPlan->musicAssignments()->count())->toBe(1);
    expect($copiedPlan->musicAssignments()->first()->music_id)->toBe($publicMusic->id);
});

test('guest cannot copy music plan', function () {
    $musicPlan = MusicPlan::factory()->create();

    $response = $this->post(route('music-plans.copy', $musicPlan));

    $response->assertRedirectToRoute('login');
});
