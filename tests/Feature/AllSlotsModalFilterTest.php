<?php

use App\Models\MusicPlan;
use App\Models\MusicPlanSlot;
use App\Models\User;
use Livewire\Livewire;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('allSlotsSearch filters slots by name using ilike', function () {
    $user = User::factory()->create();
    $musicPlan = MusicPlan::factory()->create(['user_id' => $user->id]);

    $matching = MusicPlanSlot::factory()->create(['name' => 'Gloria in Excelsis', 'is_custom' => false]);
    $notMatching = MusicPlanSlot::factory()->create(['name' => 'Kyrie Eleison', 'is_custom' => false]);

    Livewire::actingAs($user)
        ->test('music-plan-editor.slot-search', ['musicPlan' => $musicPlan])
        ->call('showAllSlots')
        ->set('allSlotsSearch', 'gloria')
        ->assertSet('allSlots', fn ($slots) => collect($slots)->pluck('id')->contains($matching->id))
        ->assertSet('allSlots', fn ($slots) => ! collect($slots)->pluck('id')->contains($notMatching->id));
});

test('allSlotsSearch filters slots by description using ilike', function () {
    $user = User::factory()->create();
    $musicPlan = MusicPlan::factory()->create(['user_id' => $user->id]);

    $matching = MusicPlanSlot::factory()->create([
        'name' => 'Opening Rite',
        'description' => 'Processional hymn at the start',
        'is_custom' => false,
    ]);
    $notMatching = MusicPlanSlot::factory()->create([
        'name' => 'Closing Rite',
        'description' => 'Final blessing and dismissal',
        'is_custom' => false,
    ]);

    Livewire::actingAs($user)
        ->test('music-plan-editor.slot-search', ['musicPlan' => $musicPlan])
        ->call('showAllSlots')
        ->set('allSlotsSearch', 'PROCESSIONAL')
        ->assertSet('allSlots', fn ($slots) => collect($slots)->pluck('id')->contains($matching->id))
        ->assertSet('allSlots', fn ($slots) => ! collect($slots)->pluck('id')->contains($notMatching->id));
});

test('allSlotsSearch empty string returns all slots', function () {
    $user = User::factory()->create();
    $musicPlan = MusicPlan::factory()->create(['user_id' => $user->id]);

    $slotA = MusicPlanSlot::factory()->create(['name' => 'Slot Alpha', 'is_custom' => false]);
    $slotB = MusicPlanSlot::factory()->create(['name' => 'Slot Beta', 'is_custom' => false]);

    Livewire::actingAs($user)
        ->test('music-plan-editor.slot-search', ['musicPlan' => $musicPlan])
        ->call('showAllSlots')
        ->set('allSlotsSearch', '')
        ->assertSet('allSlots', fn ($slots) => collect($slots)->pluck('id')->contains($slotA->id)
            && collect($slots)->pluck('id')->contains($slotB->id));
});

test('allSlotsSearch is reset when modal opens', function () {
    $user = User::factory()->create();
    $musicPlan = MusicPlan::factory()->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test('music-plan-editor.slot-search', ['musicPlan' => $musicPlan])
        ->set('allSlotsSearch', 'previous search')
        ->call('showAllSlots')
        ->assertSet('allSlotsSearch', '');
});

test('allSlotsSearch is cleared when modal closes', function () {
    $user = User::factory()->create();
    $musicPlan = MusicPlan::factory()->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test('music-plan-editor.slot-search', ['musicPlan' => $musicPlan])
        ->call('showAllSlots')
        ->set('allSlotsSearch', 'something')
        ->call('closeAllSlotsModal')
        ->assertSet('allSlotsSearch', '');
});
