<?php

use App\Livewire\MusicPlanShareModal;
use App\Models\Music;
use App\Models\MusicPlan;
use App\Models\MusicPlanSlotAssignment;
use App\Models\MusicPlanSlotPlan;
use App\Models\MusicUrl;
use App\Models\User;
use App\MusicUrlLabel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('share modal includes music URLs in share text', function () {
    $musicPlan = MusicPlan::factory()->create(['user_id' => $this->user->id]);

    // Create a music plan slot plan
    $slotPlan = MusicPlanSlotPlan::factory()->create(['music_plan_id' => $musicPlan->id]);

    // Create music with URLs
    $music = Music::factory()->create();
    MusicUrl::factory()->create([
        'music_id' => $music->id,
        'url' => 'https://example.com/sheet-music.pdf',
        'label' => MusicUrlLabel::SheetMusic->value,
    ]);
    MusicUrl::factory()->create([
        'music_id' => $music->id,
        'url' => 'https://example.com/audio.mp3',
        'label' => MusicUrlLabel::Audio->value,
    ]);

    // Create an assignment
    MusicPlanSlotAssignment::factory()->create([
        'music_plan_slot_plan_id' => $slotPlan->id,
        'music_id' => $music->id,
    ]);

    $component = Livewire::test(MusicPlanShareModal::class, ['musicPlan' => $musicPlan]);
    $component->call('openModal');

    $shareText = $component->instance()->shareText;

    expect($shareText)->toContain($music->title);
    expect($shareText)->toContain('https://example.com/sheet-music.pdf');
    expect($shareText)->toContain(__('Sheet Music'));
    expect($shareText)->toContain('https://example.com/audio.mp3');
    expect($shareText)->toContain(__('Audio'));
    expect($shareText)->toContain('🔗');
});

test('share modal works with music that has no URLs', function () {
    $musicPlan = MusicPlan::factory()->create(['user_id' => $this->user->id]);

    // Create a music plan slot plan
    $slotPlan = MusicPlanSlotPlan::factory()->create(['music_plan_id' => $musicPlan->id]);

    // Create music without URLs
    $music = Music::factory()->create();

    // Create an assignment
    MusicPlanSlotAssignment::factory()->create([
        'music_plan_slot_plan_id' => $slotPlan->id,
        'music_id' => $music->id,
    ]);

    $component = Livewire::test(MusicPlanShareModal::class, ['musicPlan' => $musicPlan]);
    $component->call('openModal');

    $shareText = $component->instance()->shareText;

    expect($shareText)->toContain($music->title);
    expect($shareText)->not->toContain('🔗');
});
