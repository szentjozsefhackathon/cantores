<?php

use App\Models\Author;
use App\Models\Collection;
use App\Models\Music;
use App\Models\MusicUrl;
use App\Models\User;
use App\Models\WhitelistRule;
use App\Policies\MusicPolicy;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

// ──────────────────────────────────────────────────────────────────────────────
// Policy unit tests
// ──────────────────────────────────────────────────────────────────────────────

describe('attachRelation policy', function () {
    beforeEach(function () {
        $this->policy = new MusicPolicy;
        $this->music = Music::factory()->create();
    });

    it('allows user with content.edit.own to attach relation to any music', function () {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::firstOrCreate(['name' => 'content.edit.own']));

        expect($this->policy->attachRelation($user, $this->music, 'url'))->toBeTrue();
    });

    it('allows user with content.edit.published to attach relation', function () {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::firstOrCreate(['name' => 'content.edit.published']));

        expect($this->policy->attachRelation($user, $this->music, 'url'))->toBeTrue();
    });

    it('denies user with no permissions to attach relation', function () {
        $user = User::factory()->create();
        $user->syncRoles([]);

        expect($this->policy->attachRelation($user, $this->music, 'url'))->toBeFalse();
    });
});

describe('editOrDeleteVerifiedRelation policy with relation owner', function () {
    beforeEach(function () {
        $this->policy = new MusicPolicy;
        $this->musicOwner = User::factory()->create();
        $this->music = Music::factory()->create(['user_id' => $this->musicOwner->id]);
    });

    it('allows relation owner to edit/delete their own relation on others music', function () {
        $relationOwner = User::factory()->create();

        $result = $this->policy->editOrDeleteVerifiedRelation(
            $relationOwner,
            $this->music,
            'url',
            null,
            $relationOwner->id // relationOwnerUserId
        );

        expect($result)->toBeTrue();
    });

    it('denies non-relation-owner without permission to edit/delete relation', function () {
        $otherUser = User::factory()->create();
        $relationOwner = User::factory()->create();

        $result = $this->policy->editOrDeleteVerifiedRelation(
            $otherUser,
            $this->music,
            'url',
            null,
            $relationOwner->id // owned by someone else
        );

        expect($result)->toBeFalse();
    });

    it('denies if relation owner is null and user has no permission', function () {
        $otherUser = User::factory()->create();

        $result = $this->policy->editOrDeleteVerifiedRelation(
            $otherUser,
            $this->music,
            'url',
            null,
            null
        );

        expect($result)->toBeFalse();
    });

    it('allows music owner with content.edit.own to edit relation with null owner', function () {
        $this->musicOwner->givePermissionTo(Permission::firstOrCreate(['name' => 'content.edit.own']));

        $result = $this->policy->editOrDeleteVerifiedRelation(
            $this->musicOwner,
            $this->music,
            'url',
            null,
            null
        );

        expect($result)->toBeTrue();
    });
});

// ──────────────────────────────────────────────────────────────────────────────
// Livewire integration tests
// ──────────────────────────────────────────────────────────────────────────────

describe('relation ownership in music editor', function () {
    beforeEach(function () {
        $this->musicOwner = User::factory()->create();
        $this->music = Music::factory()->create(['user_id' => $this->musicOwner->id]);

        $this->otherUser = User::factory()->create();

        // Ensure a default authenticated user is set so Livewire can mount
        $this->actingAs($this->otherUser);
    });

    // URLs

    it('allows a contributor to add a URL to music they do not own', function () {
        WhitelistRule::factory()->create([
            'hostname' => 'example.com',
            'path_prefix' => '/music',
            'scheme' => 'https',
            'is_active' => true,
        ]);

        Livewire::test('pages::editor.music-editor', ['music' => $this->music->fresh()])
            ->set('newUrlLabel', 'sheet_music')
            ->set('newUrl', 'https://example.com/music/song.pdf')
            ->call('addUrl')
            ->assertHasNoErrors()
            ->assertDispatched('url-added');

        $this->assertDatabaseHas('music_urls', [
            'music_id' => $this->music->id,
            'user_id' => $this->otherUser->id,
            'url' => 'https://example.com/music/song.pdf',
        ]);
    });

    it('allows URL owner to edit their own URL on music they do not own', function () {
        WhitelistRule::factory()->create([
            'hostname' => 'example.com',
            'path_prefix' => '/music',
            'scheme' => 'https',
            'is_active' => true,
        ]);

        $url = MusicUrl::factory()->create([
            'music_id' => $this->music->id,
            'user_id' => $this->otherUser->id,
            'label' => 'sheet_music',
            'url' => 'https://example.com/music/old.pdf',
        ]);

        Livewire::test('pages::editor.music-editor', ['music' => $this->music->fresh()])
            ->call('editUrl', $url->id)
            ->assertSet('editingUrlId', $url->id)
            ->set('editingUrl', 'https://example.com/music/new.pdf')
            ->call('updateUrl')
            ->assertHasNoErrors()
            ->assertDispatched('url-updated');

        $this->assertDatabaseHas('music_urls', [
            'id' => $url->id,
            'url' => 'https://example.com/music/new.pdf',
        ]);
    });

    it('allows URL owner to delete their own URL on music they do not own', function () {
        $url = MusicUrl::factory()->create([
            'music_id' => $this->music->id,
            'user_id' => $this->otherUser->id,
        ]);

        Livewire::test('pages::editor.music-editor', ['music' => $this->music->fresh()])
            ->call('deleteUrl', $url->id)
            ->assertDispatched('url-deleted');

        $this->assertDatabaseMissing('music_urls', ['id' => $url->id]);
    });

    it('denies editing a URL owned by a different user on music you do not own', function () {
        // URL owned by the music owner — otherUser cannot edit it
        $url = MusicUrl::factory()->create([
            'music_id' => $this->music->id,
            'user_id' => $this->musicOwner->id,
        ]);

        Livewire::test('pages::editor.music-editor', ['music' => $this->music->fresh()])
            ->call('editUrl', $url->id)
            ->assertForbidden();
    });

    it('denies deleting a URL owned by a different user on music you do not own', function () {
        // URL owned by the music owner — otherUser cannot delete it
        $url = MusicUrl::factory()->create([
            'music_id' => $this->music->id,
            'user_id' => $this->musicOwner->id,
        ]);

        Livewire::test('pages::editor.music-editor', ['music' => $this->music->fresh()])
            ->call('deleteUrl', $url->id)
            ->assertForbidden();
    });

    // Authors

    it('allows a contributor to add an author to music they do not own', function () {
        $author = Author::factory()->create();

        Livewire::test('pages::editor.music-editor', ['music' => $this->music->fresh()])
            ->set('selectedAuthorId', $author->id)
            ->call('addAuthor')
            ->assertHasNoErrors()
            ->assertDispatched('author-added');

        $this->assertDatabaseHas('author_music', [
            'music_id' => $this->music->id,
            'author_id' => $author->id,
            'user_id' => $this->otherUser->id,
        ]);
    });

    it('allows author-relation owner to remove the author on music they do not own', function () {
        $author = Author::factory()->create();
        $this->music->authors()->attach($author->id, ['user_id' => $this->otherUser->id]);

        Livewire::test('pages::editor.music-editor', ['music' => $this->music->fresh()])
            ->call('removeAuthor', $author->id)
            ->assertDispatched('author-removed');

        $this->assertDatabaseMissing('author_music', [
            'music_id' => $this->music->id,
            'author_id' => $author->id,
        ]);
    });

    it('denies removing an author relation owned by a different user', function () {
        // Relation owned by music owner — otherUser cannot remove it
        $author = Author::factory()->create();
        $this->music->authors()->attach($author->id, ['user_id' => $this->musicOwner->id]);

        Livewire::test('pages::editor.music-editor', ['music' => $this->music->fresh()])
            ->call('removeAuthor', $author->id)
            ->assertForbidden();
    });

    // Collections

    it('allows a contributor to add a collection to music they do not own', function () {
        $collection = Collection::factory()->create();

        Livewire::test('pages::editor.music-editor', ['music' => $this->music->fresh()])
            ->set('selectedCollectionId', $collection->id)
            ->call('addCollection')
            ->assertHasNoErrors()
            ->assertDispatched('collection-added');

        $this->assertDatabaseHas('music_collection', [
            'music_id' => $this->music->id,
            'collection_id' => $collection->id,
            'user_id' => $this->otherUser->id,
        ]);
    });

    it('allows collection-relation owner to remove the collection on music they do not own', function () {
        $collection = Collection::factory()->create();
        $this->music->collections()->attach($collection->id, ['user_id' => $this->otherUser->id]);

        Livewire::test('pages::editor.music-editor', ['music' => $this->music->fresh()])
            ->call('removeCollection', $collection->id)
            ->assertDispatched('collection-removed');

        $this->assertDatabaseMissing('music_collection', [
            'music_id' => $this->music->id,
            'collection_id' => $collection->id,
        ]);
    });

    it('denies removing a collection relation owned by a different user', function () {
        // Relation owned by music owner — otherUser cannot remove it
        $collection = Collection::factory()->create();
        $this->music->collections()->attach($collection->id, ['user_id' => $this->musicOwner->id]);

        Livewire::test('pages::editor.music-editor', ['music' => $this->music->fresh()])
            ->call('removeCollection', $collection->id)
            ->assertForbidden();
    });
});
