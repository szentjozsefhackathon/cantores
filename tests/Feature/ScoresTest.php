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

it('pre-populates music when mounted with raw numeric id from route', function () {
    $user = User::factory()->create();
    $music = Music::factory()->create(['title' => 'Public Hymn']);

    actingAs($user);

    Livewire::test(ScoreEditor::class, ['music' => $music->id])
        ->assertSet('musicId', $music->id)
        ->assertSet('title', $music->title);
});

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
        ->assertSee(__('Create Score'))
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

it('persists per-ratio preview settings when saving a score', function () {
    $user = User::factory()->create();

    actingAs($user);

    Livewire::test(ScoreEditor::class)
        ->set('title', 'Settings Score')
        ->set('format', ScoreFormat::Gabc->value)
        ->set('content', "name: x;\n%%\n(c4) Ky(e)")
        ->call('save', ['zoom' => 150, 'lyricSize' => 24, 'staffSize' => 120], '16/9')
        ->assertHasNoErrors();

    $score = Score::query()->firstWhere('title', 'Settings Score');

    expect($score->settings)->toBe([
        'gabc' => [
            '16/9' => ['zoom' => 150, 'lyricSize' => 24, 'staffSize' => 120],
        ],
    ]);
});

it('merges new ratio settings into existing score settings', function () {
    $user = User::factory()->create();
    $score = Score::factory()->gabc()->unattached()->create([
        'user_id' => $user->id,
        'settings' => ['gabc' => ['auto' => ['lyricSize' => 16]]],
    ]);

    actingAs($user);

    Livewire::test(ScoreEditor::class, ['score' => $score])
        ->call('save', ['lyricSize' => 30], '16/9')
        ->assertHasNoErrors();

    expect($score->fresh()->settings)->toBe([
        'gabc' => [
            'auto' => ['lyricSize' => 16],
            '16/9' => ['lyricSize' => 30],
        ],
    ]);
});

it('saves per-ratio defaults onto the authenticated user', function () {
    $user = User::factory()->create();

    actingAs($user);

    Livewire::test(ScoreEditor::class)
        ->call('saveAsDefault', ['abcScale' => 1.5, 'abcTranspose' => 2], '4/3', ScoreFormat::Abc->value);

    expect($user->fresh()->score_settings)->toBe([
        'abc' => [
            '4/3' => ['abcScale' => 1.5, 'abcTranspose' => 2],
        ],
    ]);
});

it('persists abc per-ratio settings including lyric options', function () {
    $user = User::factory()->create();

    actingAs($user);

    Livewire::test(ScoreEditor::class)
        ->set('title', 'ABC Settings Score')
        ->set('format', ScoreFormat::Abc->value)
        ->set('content', "X:1\nT:Test\nK:C\nC D E F|")
        ->call('save', [
            'abcScale' => 1.5,
            'abcTranspose' => 0,
            'abcLyricSize' => 14,
            'abcLyricFont' => 'Palatino',
        ], '16/9')
        ->assertHasNoErrors();

    $score = Score::query()->firstWhere('title', 'ABC Settings Score');

    expect($score->settings)->toBe([
        'abc' => [
            '16/9' => [
                'abcScale' => 1.5,
                'abcTranspose' => 0,
                'abcLyricSize' => 14,
                'abcLyricFont' => 'Palatino',
            ],
        ],
    ]);
});

it('creates an unattached chordpro score', function () {
    $user = User::factory()->create();

    actingAs($user);

    Livewire::test(ScoreEditor::class)
        ->set('title', 'My ChordPro Score')
        ->set('format', ScoreFormat::ChordPro->value)
        ->set('content', "{title: My ChordPro Score}\n\n[G]Amazing [C]grace how [G]sweet the sound")
        ->call('save')
        ->assertHasNoErrors();

    assertDatabaseHas('scores', [
        'user_id' => $user->id,
        'music_id' => null,
        'title' => 'My ChordPro Score',
        'format' => ScoreFormat::ChordPro->value,
    ]);
});

it('renders reset to defaults button in each format toolbar', function () {
    $user = User::factory()->create();

    actingAs($user);

    Livewire::test(ScoreEditor::class)
        ->assertSeeHtml('resetToDefaults()');
});

it('renders the Aretino custom editor while keeping the shared textarea for other formats', function () {
    $user = User::factory()->create();

    actingAs($user);

    Livewire::test(ScoreEditor::class)
        ->assertSeeHtml('<aretino-editor')
        ->assertSeeHtml('score-editor-aretino-source')
        ->assertSeeHtml('handleEditorContentInput($event.detail.value)')
        ->assertSeeHtml('wire:model="content"');
});

it('renders the Aretino file download action', function () {
    $user = User::factory()->create();

    actingAs($user);

    Livewire::test(ScoreEditor::class)
        ->assertSee(__('Save as .aretino file'))
        ->assertSeeHtml('saveAretinoFile()');
});

it('sets the Aretino CodeMirror editor font size', function () {
    $source = file_get_contents(resource_path('js/score-editor.js'));

    expect($source)
        ->toContain('function applyAretinoCodeMirrorFontSize(editor)')
        ->toContain('.editor-pane .cm-content')
        ->toContain('font-size: ${ARETINO_CODEMIRROR_FONT_SIZE} !important;');
});

it('makes the Aretino CodeMirror editor smaller and vertically resizable', function () {
    $styles = file_get_contents(resource_path('css/app.css'));

    expect($styles)
        ->toContain('aretino-editor.score-editor-aretino-source')
        ->toContain('height: clamp(14rem, 35vh, 22rem);')
        ->toContain('min-height: 12rem;')
        ->toContain('max-height: 80vh;')
        ->toContain('overflow: auto;')
        ->toContain('resize: vertical;');
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
