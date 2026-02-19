<?php

use App\Models\Author;
use App\Models\Collection;
use App\Models\Music;
use App\Models\MusicPlan;
use App\Models\User;

it('public model is visible to any user including guest', function () {
    $publicMusic = Music::factory()->create(['is_private' => false]);

    // Guest can see public music via scope
    $visibleToGuest = Music::visibleTo(null)->get();
    expect($visibleToGuest->pluck('id'))->toContain($publicMusic->id);

    // Authenticated user can see it
    $user = User::factory()->create();
    $visibleToUser = Music::visibleTo($user)->get();
    expect($visibleToUser->pluck('id'))->toContain($publicMusic->id);
});

it('private model is only visible to its owner', function () {
    $owner = User::factory()->create();
    $privateMusic = Music::factory()->create(['is_private' => true, 'user_id' => $owner->id]);

    // Owner can see it
    $visibleToOwner = Music::visibleTo($owner)->get();
    expect($visibleToOwner->pluck('id'))->toContain($privateMusic->id);

    // Other user cannot see it
    $otherUser = User::factory()->create();
    $visibleToOther = Music::visibleTo($otherUser)->get();
    expect($visibleToOther->pluck('id'))->not->toContain($privateMusic->id);

    // Guest cannot see it
    $visibleToGuest = Music::visibleTo(null)->get();
    expect($visibleToGuest->pluck('id'))->not->toContain($privateMusic->id);
});

it('private model is not visible to other authenticated users', function () {
    $owner = User::factory()->create();
    $privateAuthor = Author::factory()->create(['is_private' => true, 'user_id' => $owner->id]);

    $otherUser = User::factory()->create();
    $visible = Author::visibleTo($otherUser)->get();
    expect($visible->pluck('id'))->not->toContain($privateAuthor->id);
});

it('MusicPlan with is_private field works correctly', function () {
    $owner = User::factory()->create();
    $publicPlan = MusicPlan::factory()->create(['is_private' => false, 'user_id' => $owner->id]);
    $privatePlan = MusicPlan::factory()->create(['is_private' => true, 'user_id' => $owner->id]);

    // Guest can see public plan
    $visibleToGuest = MusicPlan::visibleTo(null)->get();
    expect($visibleToGuest->pluck('id'))->toContain($publicPlan->id);
    expect($visibleToGuest->pluck('id'))->not->toContain($privatePlan->id);

    // Owner can see both (because owner can see private)
    $visibleToOwner = MusicPlan::visibleTo($owner)->get();
    expect($visibleToOwner->pluck('id'))->toContain($publicPlan->id);
    expect($visibleToOwner->pluck('id'))->toContain($privatePlan->id);

    // Other user can only see public
    $otherUser = User::factory()->create();
    $visibleToOther = MusicPlan::visibleTo($otherUser)->get();
    expect($visibleToOther->pluck('id'))->toContain($publicPlan->id);
    expect($visibleToOther->pluck('id'))->not->toContain($privatePlan->id);
});

it('cascading visibility: public Music with private Author excludes Author for non-owner', function () {
    $owner = User::factory()->create();
    $privateAuthor = Author::factory()->create(['is_private' => true, 'user_id' => $owner->id]);
    $publicMusic = Music::factory()->create(['is_private' => false, 'user_id' => $owner->id]);
    $publicMusic->authors()->attach($privateAuthor);

    // For owner, author should be visible
    $authorsForOwner = $publicMusic->authors()->visibleTo($owner)->get();
    expect($authorsForOwner->pluck('id'))->toContain($privateAuthor->id);

    // For other user, author should NOT be visible
    $otherUser = User::factory()->create();
    $authorsForOther = $publicMusic->authors()->visibleTo($otherUser)->get();
    expect($authorsForOther->pluck('id'))->not->toContain($privateAuthor->id);

    // For guest, author should NOT be visible
    $authorsForGuest = $publicMusic->authors()->visibleTo(null)->get();
    expect($authorsForGuest->pluck('id'))->not->toContain($privateAuthor->id);
});

it('cascading visibility works for Collection as well', function () {
    $owner = User::factory()->create();
    $privateCollection = Collection::factory()->create(['is_private' => true, 'user_id' => $owner->id]);
    $publicMusic = Music::factory()->create(['is_private' => false, 'user_id' => $owner->id]);
    $publicMusic->collections()->attach($privateCollection);

    // For owner, collection visible
    $collectionsForOwner = $publicMusic->collections()->visibleTo($owner)->get();
    expect($collectionsForOwner->pluck('id'))->toContain($privateCollection->id);

    // For other user, collection not visible
    $otherUser = User::factory()->create();
    $collectionsForOther = $publicMusic->collections()->visibleTo($otherUser)->get();
    expect($collectionsForOther->pluck('id'))->not->toContain($privateCollection->id);
});

it('scopeWithVisibleRelation filters parent models based on visible related models', function () {
    $owner = User::factory()->create();
    $privateAuthor = Author::factory()->create(['is_private' => true, 'user_id' => $owner->id]);
    $publicAuthor = Author::factory()->create(['is_private' => false, 'user_id' => $owner->id]);
    $musicWithBoth = Music::factory()->create(['is_private' => false, 'user_id' => $owner->id]);
    $musicWithBoth->authors()->attach([$privateAuthor->id, $publicAuthor->id]);

    // Music with only private author
    $musicWithPrivateOnly = Music::factory()->create(['is_private' => false, 'user_id' => $owner->id]);
    $musicWithPrivateOnly->authors()->attach($privateAuthor);

    // For owner, both musics should be returned (owner can see private author)
    $musicsForOwner = Music::withVisibleRelation('authors', $owner)->get();
    expect($musicsForOwner->pluck('id'))->toContain($musicWithBoth->id);
    expect($musicsForOwner->pluck('id'))->toContain($musicWithPrivateOnly->id);

    // For other user, only music with public author should be returned
    $otherUser = User::factory()->create();
    $musicsForOther = Music::withVisibleRelation('authors', $otherUser)->get();
    expect($musicsForOther->pluck('id'))->toContain($musicWithBoth->id);
    expect($musicsForOther->pluck('id'))->not->toContain($musicWithPrivateOnly->id);

    // For guest, same as other user (only public)
    $musicsForGuest = Music::withVisibleRelation('authors', null)->get();
    expect($musicsForGuest->pluck('id'))->toContain($musicWithBoth->id);
    expect($musicsForGuest->pluck('id'))->not->toContain($musicWithPrivateOnly->id);
});

it('isVisibleTo method works correctly', function () {
    $owner = User::factory()->create();
    $privateMusic = Music::factory()->create(['is_private' => true, 'user_id' => $owner->id]);
    $publicMusic = Music::factory()->create(['is_private' => false, 'user_id' => $owner->id]);

    expect($privateMusic->isVisibleTo($owner))->toBeTrue();
    expect($privateMusic->isVisibleTo(null))->toBeFalse();
    expect($privateMusic->isVisibleTo(User::factory()->create()))->toBeFalse();

    expect($publicMusic->isVisibleTo($owner))->toBeTrue();
    expect($publicMusic->isVisibleTo(null))->toBeTrue();
    expect($publicMusic->isVisibleTo(User::factory()->create()))->toBeTrue();
});
