<?php

use App\Models\Collection;
use App\Models\Genre;
use App\Models\Music;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * Attach a collection to a music piece with an optional pivot order number.
 */
function attachCollection(Music $music, Collection $collection, ?string $orderNumber = null): void
{
    $music->collections()->attach($collection->id, ['order_number' => $orderNumber]);
}

it('ranks collections by priority, then verified, then size', function () {
    $music = Music::factory()->create();

    $lowPriority = Collection::factory()->create(['priority' => 10, 'is_verified' => false, 'is_private' => false]);
    $defaultVerified = Collection::factory()->create(['priority' => 100, 'is_verified' => true, 'is_private' => false]);
    $defaultUnverified = Collection::factory()->create(['priority' => 100, 'is_verified' => false, 'is_private' => false]);

    attachCollection($music, $defaultUnverified);
    attachCollection($music, $defaultVerified);
    attachCollection($music, $lowPriority);

    $ranked = $music->displayCollections();

    expect($ranked->pluck('id')->all())->toBe([
        $lowPriority->id,       // lowest priority number wins
        $defaultVerified->id,   // verified beats unverified at same priority
        $defaultUnverified->id,
    ]);
});

it('excludes collections from other genres as if they did not contain the music', function () {
    $organist = Genre::firstOrCreate(['name' => 'organist']);
    $guitarist = Genre::firstOrCreate(['name' => 'guitarist']);

    $user = User::factory()->create(['current_genre_id' => $organist->id]);
    $this->actingAs($user);

    $music = Music::factory()->create();

    $organistCollection = Collection::factory()->create(['is_private' => false]);
    $organistCollection->genres()->attach($organist);

    $guitaristCollection = Collection::factory()->create(['is_private' => false]);
    $guitaristCollection->genres()->attach($guitarist);

    $genrelessCollection = Collection::factory()->create(['is_private' => false]);

    attachCollection($music, $organistCollection);
    attachCollection($music, $guitaristCollection);
    attachCollection($music, $genrelessCollection);

    $ranked = $music->displayCollections($user);

    expect($ranked->pluck('id')->all())
        ->toContain($organistCollection->id)
        ->toContain($genrelessCollection->id)
        ->not->toContain($guitaristCollection->id);
});

it('excludes private collections owned by other users', function () {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();

    $music = Music::factory()->create();

    $publicCollection = Collection::factory()->create(['is_private' => false]);
    $ownPrivateCollection = Collection::factory()->create(['is_private' => true, 'user_id' => $viewer->id]);
    $otherPrivateCollection = Collection::factory()->create(['is_private' => true, 'user_id' => $owner->id]);

    attachCollection($music, $publicCollection);
    attachCollection($music, $ownPrivateCollection);
    attachCollection($music, $otherPrivateCollection);

    $ranked = $music->displayCollections($viewer);

    expect($ranked->pluck('id')->all())
        ->toContain($publicCollection->id)
        ->toContain($ownPrivateCollection->id)
        ->not->toContain($otherPrivateCollection->id);
});

it('returns the full ranked list so callers can apply their own limit', function () {
    $music = Music::factory()->create();

    foreach (range(1, 6) as $i) {
        attachCollection($music, Collection::factory()->create(['priority' => $i, 'is_private' => false]));
    }

    expect($music->displayCollections())->toHaveCount(6);
});

it('renders badge triggers above the card overlay so their tooltips stay hoverable', function () {
    $music = Music::factory()->create();

    foreach (range(1, 6) as $i) {
        attachCollection($music, Collection::factory()->create(['priority' => $i, 'is_private' => false]), (string) $i);
    }

    $html = Blade::render('<x-collection-badges :music="$music" />', ['music' => $music->fresh()]);

    expect(substr_count($html, 'relative z-10'))->toBe(4); // 3 shown badges + the "+N" badge
    expect($html)->toContain('+3');
});

it('lets an editor set a collection display priority', function () {
    $permission = Permission::firstOrCreate(['name' => 'content.edit.verified', 'guard_name' => 'web']);
    $editor = User::factory()->create();
    $editor->givePermissionTo($permission);
    $this->actingAs($editor);

    $collection = Collection::factory()->create(['priority' => 100, 'is_verified' => true]);

    Livewire::test(\App\Livewire\Pages\Editor\Collections::class)
        ->call('edit', $collection)
        ->set('priority', 5)
        ->call('update')
        ->assertHasNoErrors();

    expect($collection->fresh()->priority)->toBe(5);
});

it('does not let a non-editor owner change display priority', function () {
    $owner = User::factory()->create();
    $this->actingAs($owner);

    $collection = Collection::factory()->create(['priority' => 100, 'user_id' => $owner->id]);

    Livewire::test(\App\Livewire\Pages\Editor\Collections::class)
        ->call('edit', $collection)
        ->set('priority', 5)
        ->call('update')
        ->assertHasNoErrors();

    expect($collection->fresh()->priority)->toBe(100);
});
