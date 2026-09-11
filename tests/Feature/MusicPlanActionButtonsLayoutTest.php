<?php

use App\Models\MusicPlan;
use App\Models\User;
use Livewire\Livewire;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('the view page action buttons wrap instead of overflowing', function () {
    $user = User::factory()->create();
    $musicPlan = MusicPlan::factory()->create(['user_id' => $user->id]);

    $html = Livewire::actingAs($user)
        ->test('pages::music-plan.music-plan-view', ['musicPlan' => $musicPlan])
        ->html();

    expect($html)->toContain('flex flex-col sm:flex-row sm:flex-wrap sm:items-center gap-3 pt-4');
});

test('the editor page action buttons wrap instead of overflowing', function () {
    $user = User::factory()->create();
    $musicPlan = MusicPlan::factory()->create(['user_id' => $user->id]);

    $html = Livewire::actingAs($user)
        ->test('pages::music-plan.music-plan-editor', ['musicPlan' => $musicPlan])
        ->html();

    expect($html)->toContain('flex flex-col sm:flex-row sm:flex-wrap sm:items-center gap-3 pt-4');
});
