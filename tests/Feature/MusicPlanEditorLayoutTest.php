<?php

use App\Models\MusicPlan;
use App\Models\User;
use Livewire\Livewire;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('the editor columns are independently scrollable', function () {
    $user = User::factory()->create();
    $musicPlan = MusicPlan::factory()->create(['user_id' => $user->id]);

    $html = Livewire::actingAs($user)
        ->test('pages::music-plan.music-plan-editor', ['musicPlan' => $musicPlan])
        ->html();

    expect($html)
        ->toContain('grid grid-cols-1 gap-6 lg:h-[calc(100vh-8rem)] lg:grid-cols-2')
        ->and($html)->toContain('space-y-4 lg:col-span-1 lg:min-h-0 lg:overflow-y-auto lg:pr-2')
        ->and($html)->toContain('space-y-4 lg:min-h-0 lg:overflow-y-auto lg:pr-2');
});
