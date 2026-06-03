<?php

use App\Livewire\Pages\FolderEditor;
use App\Livewire\Pages\Folders;
use App\Livewire\Pages\ScoreEditor;
use App\Models\Folder;
use App\Models\Score;
use App\Models\User;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('only lists the authenticated users own folders', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    Folder::factory()->create(['user_id' => $userA->id, 'name' => 'Advent']);
    Folder::factory()->create(['user_id' => $userB->id, 'name' => 'Christmas']);

    actingAs($userA);

    Livewire::test(Folders::class)
        ->assertSee('Advent')
        ->assertDontSee('Christmas');
});

it('creates a folder', function () {
    $user = User::factory()->create();
    actingAs($user);

    Livewire::test(FolderEditor::class)
        ->set('name', 'Easter')
        ->call('save')
        ->assertHasNoErrors();

    expect(Folder::query()->where('user_id', $user->id)->where('name', 'Easter')->exists())->toBeTrue();
});

it('requires a name to create a folder', function () {
    $user = User::factory()->create();
    actingAs($user);

    Livewire::test(FolderEditor::class)
        ->set('name', '')
        ->call('save')
        ->assertHasErrors(['name']);
});

it('prevents editing another users folder', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $folder = Folder::factory()->create(['user_id' => $owner->id]);

    actingAs($other);

    get(route('folders.edit', $folder))->assertForbidden();
});

it('assigns scores to a folder on save', function () {
    $user = User::factory()->create();
    $scoreA = Score::factory()->create(['user_id' => $user->id]);
    $scoreB = Score::factory()->create(['user_id' => $user->id]);
    $folder = Folder::factory()->create(['user_id' => $user->id]);

    actingAs($user);

    Livewire::test(FolderEditor::class, ['folder' => $folder])
        ->set('scoreIds', [$scoreA->id, $scoreB->id])
        ->call('save')
        ->assertHasNoErrors();

    expect($folder->scores()->pluck('scores.id')->toArray())->toContain($scoreA->id, $scoreB->id);
});

it('removes scores from a folder on save', function () {
    $user = User::factory()->create();
    $score = Score::factory()->create(['user_id' => $user->id]);
    $folder = Folder::factory()->create(['user_id' => $user->id]);
    $folder->scores()->attach($score);

    actingAs($user);

    Livewire::test(FolderEditor::class, ['folder' => $folder])
        ->set('scoreIds', [])
        ->call('save')
        ->assertHasNoErrors();

    expect($folder->scores()->count())->toBe(0);
});

it('toggleFolder adds a score to a folder', function () {
    $user = User::factory()->create();
    $score = Score::factory()->create(['user_id' => $user->id]);
    $folder = Folder::factory()->create(['user_id' => $user->id]);

    actingAs($user);

    Livewire::test(FolderEditor::class, ['folder' => $folder])
        ->call('toggleScore', $score->id)
        ->assertHasNoErrors();

    expect(in_array($score->id, $folder->fresh()->scores()->pluck('scores.id')->toArray()))->toBeTrue();
});

it('toggleFolder removes a score from a folder', function () {
    $user = User::factory()->create();
    $score = Score::factory()->create(['user_id' => $user->id]);
    $folder = Folder::factory()->create(['user_id' => $user->id]);
    $folder->scores()->attach($score);

    actingAs($user);

    Livewire::test(FolderEditor::class, ['folder' => $folder])
        ->call('toggleScore', $score->id)
        ->assertHasNoErrors();

    expect($folder->fresh()->scores()->count())->toBe(0);
});

it('createFolderAndAdd creates folder and attaches score in one step', function () {
    $user = User::factory()->create();
    $score = Score::factory()->create(['user_id' => $user->id]);

    actingAs($user);

    Livewire::test(ScoreEditor::class, ['score' => $score])
        ->call('createFolderAndAdd', 'Karácsony')
        ->assertHasNoErrors();

    $folder = Folder::query()->where('user_id', $user->id)->where('name', 'Karácsony')->first();
    expect($folder)->not->toBeNull();
    expect($folder->scores()->where('score_id', $score->id)->exists())->toBeTrue();
});

it('score editor folders section only shows for saved scores', function () {
    $user = User::factory()->create();
    actingAs($user);

    // Create mode — no score yet, folders button should not render
    Livewire::test(ScoreEditor::class)
        ->assertDontSee(__('Folders'));
});
