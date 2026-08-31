<?php

use App\Livewire\Pages\FolderEditor;
use App\Livewire\Pages\FolderView;
use App\Models\Folder;
use App\Models\Share;
use App\Models\User;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('returns 404 for an unknown folder token', function () {
    get(route('folder.share', ['token' => 'nonexistenttoken12345678901234']))->assertNotFound();
});

it('redirects the owner to the edit screen', function () {
    $user = User::factory()->create();
    $folder = Folder::factory()->create(['user_id' => $user->id]);
    $share = Share::factory()->of($folder)->create();

    actingAs($user);

    Livewire::test(FolderView::class, ['token' => $share->token])
        ->assertRedirect(route('folders.edit', ['folder' => $folder->id]));
});

it('shows read-only view to a guest', function () {
    $owner = User::factory()->create();
    $folder = Folder::factory()->create(['user_id' => $owner->id]);
    $share = Share::factory()->of($folder)->create();

    get(route('folder.share', ['token' => $share->token]))->assertOk();
});

it('shows read-only view to another authenticated user', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $folder = Folder::factory()->create(['user_id' => $owner->id, 'name' => 'Advent']);
    $share = Share::factory()->of($folder)->create();

    actingAs($other);

    Livewire::test(FolderView::class, ['token' => $share->token])
        ->assertSet('name', 'Advent');
});

it('owner can generate a secret link', function () {
    $user = User::factory()->create();
    $folder = Folder::factory()->create(['user_id' => $user->id]);
    actingAs($user);

    expect($folder->shareToken())->toBeNull();

    Livewire::test(FolderEditor::class, ['folder' => $folder])
        ->call('generateSecretLink')
        ->assertHasNoErrors();

    expect($folder->fresh()->shareToken())->not->toBeNull()->toHaveLength(32);
});

it('secret link resolves to a valid URL after generation', function () {
    $user = User::factory()->create();
    $folder = Folder::factory()->create(['user_id' => $user->id]);
    actingAs($user);

    Livewire::test(FolderEditor::class, ['folder' => $folder])
        ->call('generateSecretLink');

    $token = $folder->fresh()->shareToken();
    expect($token)->not->toBeNull();

    \Illuminate\Support\Facades\Auth::logout();
    get(route('folder.share', ['token' => $token]))->assertOk();
});

it('owner can delete the secret link', function () {
    $user = User::factory()->create();
    $folder = Folder::factory()->create(['user_id' => $user->id]);
    Share::factory()->of($folder)->create();
    actingAs($user);

    Livewire::test(FolderEditor::class, ['folder' => $folder])
        ->call('deleteSecretLink')
        ->assertHasNoErrors();

    expect($folder->fresh()->shareToken())->toBeNull();
});

it('deleted secret link returns 404', function () {
    $user = User::factory()->create();
    $folder = Folder::factory()->create(['user_id' => $user->id]);
    $token = Share::factory()->of($folder)->create()->token;
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
    $folder = Folder::factory()->create(['user_id' => $owner->id]);
    $share = Share::factory()->of($folder)->create();

    get(route('folder.share', ['token' => $share->token]))
        ->assertOk()
        ->assertSee('noindex, nofollow', false);
});
