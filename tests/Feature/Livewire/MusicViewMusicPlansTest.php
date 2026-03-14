<?php

use App\Models\Music;
use App\Models\MusicPlan;
use App\Models\MusicPlanSlot;
use App\Models\MusicPlanSlotAssignment;
use App\Models\MusicPlanSlotPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->music = Music::factory()->create(['user_id' => $this->user->id]);
    $this->actingAs($this->user);
});

function attachMusicToplan(Music $music, MusicPlan $plan): void
{
    $slot = MusicPlanSlot::factory()->create(['priority' => 1]);
    $plan->slots()->attach($slot, ['sequence' => 1]);
    $pivot = MusicPlanSlotPlan::where('music_plan_id', $plan->id)
        ->where('music_plan_slot_id', $slot->id)
        ->first();

    MusicPlanSlotAssignment::factory()->create([
        'music_plan_slot_plan_id' => $pivot->id,
        'music_id' => $music->id,
        'music_sequence' => 1,
    ]);
}

test('music plans section is hidden when no plans contain this music', function () {
    Livewire::test(\App\Livewire\Pages\MusicView::class, ['music' => $this->music])
        ->assertDontSee(__('Music Plans'));
});

test('music plans section shows plans that contain this music', function () {
    $plan = MusicPlan::factory()->create(['user_id' => $this->user->id, 'is_private' => false]);
    attachMusicToplan($this->music, $plan);

    Livewire::test(\App\Livewire\Pages\MusicView::class, ['music' => $this->music])
        ->assertSee(__('Music Plans'));
});

test('private music plans owned by the current user are shown', function () {
    $plan = MusicPlan::factory()->create(['user_id' => $this->user->id, 'is_private' => true]);
    attachMusicToplan($this->music, $plan);

    Livewire::test(\App\Livewire\Pages\MusicView::class, ['music' => $this->music])
        ->assertSee(__('Music Plans'));
});

test('private music plans owned by another user are hidden', function () {
    $otherUser = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $otherUser->id, 'is_private' => true]);
    attachMusicToplan($this->music, $plan);

    Livewire::test(\App\Livewire\Pages\MusicView::class, ['music' => $this->music])
        ->assertDontSee(__('Music Plans'));
});

test('plans from other music are not shown', function () {
    $otherMusic = Music::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $this->user->id, 'is_private' => false]);
    attachMusicToplan($otherMusic, $plan);

    Livewire::test(\App\Livewire\Pages\MusicView::class, ['music' => $this->music])
        ->assertDontSee(__('Music Plans'));
});
