<?php

use App\Livewire\Pages\FolderEditor;
use App\Livewire\Pages\FolderView;
use App\Models\Folder;
use App\Models\User;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('returns 404 for an unknown folder token', function () {
    get(route('folder.share', ['token' => 'nonexistenttoken12345678901234']))->assertNotFound();
});

it('redirects the owner to the edit screen', function () {
    $user = User::factory()->create();
    $folder = Folder::factory()->create(['user_id' => $user->id, 'share_token' => 'ownertoken1234567890123456789012']);

    actingAs($user);

    Livewire::test(FolderView::class, ['token' => $folder->share_token])
        ->assertRedirect(route('folders.edit', ['folder' => $folder->id]));
});

it('shows read-only view to a guest', function () {
    $owner = User::factory()->create();
    $folder = Folder::factory()->create(['user_id' => $owner->id, 'share_token' => 'guesttoken12345678901234567890ab']);

    get(route('folder.share', ['token' => $folder->share_token]))->assertOk();
});

it('shows read-only view to another authenticated user', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $folder = Folder::factory()->create(['user_id' => $owner->id, 'share_token' => 'othertoken1234567890123456789012', 'name' => 'Advent']);

    actingAs($other);

    Livewire::test(FolderView::class, ['token' => $folder->share_token])
        ->assertSet('name', 'Advent');
});

it('owner can generate a secret link', function () {
    $user = User::factory()->create();
    $folder = Folder::factory()->create(['user_id' => $user->id]);
    actingAs($user);

    expect($folder->share_token)->toBeNull();

    Livewire::test(FolderEditor::class, ['folder' => $folder])
        ->call('generateSecretLink')
        ->assertHasNoErrors();

    expect($folder->fresh()->share_token)->not->toBeNull()->toHaveLength(32);
});

it('secret link resolves to a valid URL after generation', function () {
    $user = User::factory()->create();
    $folder = Folder::factory()->create(['user_id' => $user->id]);
    actingAs($user);

    Livewire::test(FolderEditor::class, ['folder' => $folder])
        ->call('generateSecretLink');

    $token = $folder->fresh()->share_token;
    expect($token)->not->toBeNull();

    \Illuminate\Support\Facades\Auth::logout();
    get(route('folder.share', ['token' => $token]))->assertOk();
});

it('owner can delete the secret link', function () {
    $user = User::factory()->create();
    $folder = Folder::factory()->create(['user_id' => $user->id, 'share_token' => 'deletetoken12345678901234567890']);
    actingAs($user);

    Livewire::test(FolderEditor::class, ['folder' => $folder])
        ->call('deleteSecretLink')
        ->assertHasNoErrors();

    expect($folder->fresh()->share_token)->toBeNull();
});

it('deleted secret link returns 404', function () {
    $user = User::factory()->create();
    $folder = Folder::factory()->create(['user_id' => $user->id, 'share_token' => 'deletedtoken1234567890123456789']);
    $token = $folder->share_token;
    actingAs($user);

    Livewire::test(FolderEditor::class, ['folder' => $folder])
        ->call('deleteSecretLink');

    get(route('folder.share', ['token' => $token]))->assertNotFound();
});

it('another user cannot open FolderEditor for someone elses folder', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $folder = Folder::factory()->create(['user_id' => $owner->id]);
    actingAs($other);

    Livewire::test(FolderEditor::class, ['folder' => $folder])
        ->assertForbidden();
});

it('read-only view has noindex meta tag', function () {
    $owner = User::factory()->create();
    $folder = Folder::factory()->create(['user_id' => $owner->id, 'share_token' => 'noindextoken12345678901234567890']);

    get(route('folder.share', ['token' => $folder->share_token]))
        ->assertOk()
        ->assertSee('noindex, nofollow', false);
});
