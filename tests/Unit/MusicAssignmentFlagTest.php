<?php

use App\Models\MusicAssignmentFlag;
use App\Models\MusicPlanSlotAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

test('music assignment flag has name', function () {
    $flag = MusicAssignmentFlag::factory()->create(['name' => 'important']);
    expect($flag->name)->toBe('important');
});

test('music assignment flag has label', function () {
    $flag = MusicAssignmentFlag::factory()->create(['name' => 'important']);
    expect($flag->label())->toBe(__('Important'));

    $flag2 = MusicAssignmentFlag::factory()->create(['name' => 'alternative']);
    expect($flag2->label())->toBe(__('Alternative'));

    $flag3 = MusicAssignmentFlag::factory()->create(['name' => 'low_priority']);
    expect($flag3->label())->toBe(__('Low Priority'));

    $flag4 = MusicAssignmentFlag::factory()->create(['name' => 'unknown']);
    expect($flag4->label())->toBe('unknown');
});

test('music assignment flag has icon', function () {
    $flag = MusicAssignmentFlag::factory()->create(['name' => 'important']);
    expect($flag->icon())->toBe('star');

    $flag2 = MusicAssignmentFlag::factory()->create(['name' => 'alternative']);
    expect($flag2->icon())->toBe('refresh-cw');

    $flag3 = MusicAssignmentFlag::factory()->create(['name' => 'low_priority']);
    expect($flag3->icon())->toBe('arrow-down');

    $flag4 = MusicAssignmentFlag::factory()->create(['name' => 'unknown']);
    expect($flag4->icon())->toBe('flag');
});

test('music assignment flag has color', function () {
    $flag = MusicAssignmentFlag::factory()->create(['name' => 'important']);
    expect($flag->color())->toBe('amber');

    $flag2 = MusicAssignmentFlag::factory()->create(['name' => 'alternative']);
    expect($flag2->color())->toBe('blue');

    $flag3 = MusicAssignmentFlag::factory()->create(['name' => 'low_priority']);
    expect($flag3->color())->toBe('gray');

    $flag4 = MusicAssignmentFlag::factory()->create(['name' => 'unknown']);
    expect($flag4->color())->toBe('slate');
});

test('music assignment flag can have many assignments', function () {
    $flag = MusicAssignmentFlag::factory()->create(['name' => 'important']);
    $assignment1 = MusicPlanSlotAssignment::factory()->create();
    $assignment2 = MusicPlanSlotAssignment::factory()->create();

    $flag->musicPlanSlotAssignments()->attach([$assignment1->id, $assignment2->id]);

    expect($flag->musicPlanSlotAssignments)->toHaveCount(2);
});

test('music assignment flag options returns array', function () {
    // Create flags with unique names
    MusicAssignmentFlag::factory()->create(['name' => 'flag1']);
    MusicAssignmentFlag::factory()->create(['name' => 'flag2']);
    MusicAssignmentFlag::factory()->create(['name' => 'flag3']);
    
    $options = MusicAssignmentFlag::options();

    expect($options)->toBeArray();
    expect($options)->toHaveCount(3);
});
