<?php

use App\Enums\ScoreFormat;
use App\Livewire\Pages\MusicView;
use App\Livewire\Pages\ScoreEditor;
use App\Livewire\Pages\Scores;
use App\Models\Music;
use App\Models\Score;
use App\Models\User;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;
use function Pest\Laravel\get;

it('creates an abc score attached to a visible music piece', function () {
    $user = User::factory()->create();
    $music = Music::factory()->create(['title' => 'Public Hymn']);

    actingAs($user);

    Livewire::test(ScoreEditor::class, ['music' => $music])
        ->assertSet('musicId', $music->id)
        ->set('title', 'My ABC Score')
        ->set('format', ScoreFormat::Abc->value)
        ->set('content', "X:1\nT:My ABC Score\nK:C\nC D E F|")
        ->call('save')
        ->assertHasNoErrors();

    $score = Score::query()->firstWhere('title', 'My ABC Score');

    expect($score)
        ->not->toBeNull()
        ->user_id->toBe($user->id)
        ->music_id->toBe($music->id)
        ->format->toBe(ScoreFormat::Abc);
});

it('creates an unattached gabc score', function () {
    $user = User::factory()->create();

    actingAs($user);

    Livewire::test(ScoreEditor::class)
        ->set('title', 'My GABC Score')
        ->set('format', ScoreFormat::Gabc->value)
        ->set('content', "name: My GABC Score;\n%%\n(c4) Ky(e)ri(f)e(g) (::)")
        ->call('save')
        ->assertHasNoErrors();

    assertDatabaseHas('scores', [
        'user_id' => $user->id,
        'music_id' => null,
        'title' => 'My GABC Score',
        'format' => ScoreFormat::Gabc->value,
    ]);
});

it('only lists the authenticated users own scores', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    Score::factory()->create(['user_id' => $user->id, 'title' => 'Mine Only']);
    Score::factory()->create(['user_id' => $otherUser->id, 'title' => 'Not Mine']);

    actingAs($user);

    Livewire::test(Scores::class)
        ->assertSee('Mine Only')
        ->assertDontSee('Not Mine');
});

it('prevents editing another users score', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $score = Score::factory()->create(['user_id' => $otherUser->id]);

    actingAs($user);

    get(route('scores.edit', $score))->assertForbidden();
});

it('shows only the current users attached scores on the music detail page', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $music = Music::factory()->create(['title' => 'Shared Song']);

    Score::factory()->create([
        'user_id' => $user->id,
        'music_id' => $music->id,
        'title' => 'Visible Private Score',
    ]);

    Score::factory()->create([
        'user_id' => $otherUser->id,
        'music_id' => $music->id,
        'title' => 'Other Private Score',
    ]);

    actingAs($user);

    Livewire::test(MusicView::class, ['music' => $music])
        ->assertSee('Create Score')
        ->assertSee('Visible Private Score')
        ->assertDontSee('Other Private Score');
});

it('auto-populates title when music is selected via the music search modal', function () {
    $user = User::factory()->create();
    $music = Music::factory()->create(['title' => 'Ave Maria']);

    actingAs($user);

    Livewire::test(ScoreEditor::class)
        ->assertSet('title', '')
        ->dispatch('music-selected.score', musicId: $music->id)
        ->assertSet('musicId', $music->id)
        ->assertSet('title', 'Ave Maria');
});

it('does not allow attaching a score to a private music piece the user cannot view', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $music = Music::factory()->private()->create(['user_id' => $otherUser->id]);

    actingAs($user);

    Livewire::test(ScoreEditor::class)
        ->set('title', 'Blocked Attachment')
        ->set('format', ScoreFormat::Abc->value)
        ->set('content', "X:1\nT:Blocked\nK:C\nC|")
        ->set('musicId', $music->id)
        ->call('save')
        ->assertForbidden();

    assertDatabaseMissing('scores', [
        'title' => 'Blocked Attachment',
    ]);
});
