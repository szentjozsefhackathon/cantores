<?php

use App\Enums\MusicTagType;
use App\Livewire\Pages\Editor\Musics;
use App\Models\Music;
use App\Models\MusicTag;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->user->assignRole('editor');
    $this->actingAs($this->user);
});

test('tag filter with AND logic returns only music with all selected tags', function () {
    // Create tags
    $tag1 = MusicTag::create([
        'name' => 'Tag 1',
        'type' => MusicTagType::PartOfMass->value,
    ]);
    $tag2 = MusicTag::create([
        'name' => 'Tag 2',
        'type' => MusicTagType::PartOfMass->value,
    ]);
    $tag3 = MusicTag::create([
        'name' => 'Tag 3',
        'type' => MusicTagType::PartOfMass->value,
    ]);

    // Create music pieces
    $musicWithTag1Only = Music::factory()->create(['user_id' => $this->user->id]);
    $musicWithTag1Only->tags()->attach($tag1);

    $musicWithTag1And2 = Music::factory()->create(['user_id' => $this->user->id]);
    $musicWithTag1And2->tags()->attach([$tag1->id, $tag2->id]);

    $musicWithAllTags = Music::factory()->create(['user_id' => $this->user->id]);
    $musicWithAllTags->tags()->attach([$tag1->id, $tag2->id, $tag3->id]);

    $musicWithTag2Only = Music::factory()->create(['user_id' => $this->user->id]);
    $musicWithTag2Only->tags()->attach($tag2);

    // Test: Filter by tag1 only
    Livewire::test(Musics::class)
        ->set('tagFilters', [$tag1->id])
        ->assertSee($musicWithTag1Only->title)
        ->assertSee($musicWithTag1And2->title)
        ->assertSee($musicWithAllTags->title)
        ->assertDontSee($musicWithTag2Only->title);

    // Test: Filter by tag1 AND tag2 (AND logic)
    Livewire::test(Musics::class)
        ->set('tagFilters', [$tag1->id, $tag2->id])
        ->assertSee($musicWithTag1And2->title)
        ->assertSee($musicWithAllTags->title)
        ->assertDontSee($musicWithTag1Only->title)
        ->assertDontSee($musicWithTag2Only->title);

    // Test: Filter by tag1 AND tag2 AND tag3 (AND logic)
    Livewire::test(Musics::class)
        ->set('tagFilters', [$tag1->id, $tag2->id, $tag3->id])
        ->assertSee($musicWithAllTags->title)
        ->assertDontSee($musicWithTag1Only->title)
        ->assertDontSee($musicWithTag1And2->title)
        ->assertDontSee($musicWithTag2Only->title);

    // Test: Empty filter shows all
    Livewire::test(Musics::class)
        ->set('tagFilters', [])
        ->assertSee($musicWithTag1Only->title)
        ->assertSee($musicWithTag1And2->title)
        ->assertSee($musicWithAllTags->title)
        ->assertSee($musicWithTag2Only->title);
});

test('tag filter works with other filters', function () {
    $tag = MusicTag::create([
        'name' => 'Test Tag',
        'type' => MusicTagType::PartOfMass->value,
    ]);

    $publicMusic = Music::factory()->create(['user_id' => $this->user->id, 'is_private' => false]);
    $publicMusic->tags()->attach($tag);

    $privateMusic = Music::factory()->create(['user_id' => $this->user->id, 'is_private' => true]);
    $privateMusic->tags()->attach($tag);

    // Filter by tag and public visibility
    Livewire::test(Musics::class)
        ->set('tagFilters', [$tag->id])
        ->set('filter', 'public')
        ->assertSee($publicMusic->title)
        ->assertDontSee($privateMusic->title);
});
