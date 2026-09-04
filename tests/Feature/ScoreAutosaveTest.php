<?php

use App\Enums\ScoreFormat;
use App\Livewire\Pages\ScoreEditor;
use App\Models\Music;
use App\Models\Score;
use App\Models\ScoreUrl;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function () {
    Storage::fake();
});

it('creates an untitled draft and opens it when the create page is visited', function () {
    $user = User::factory()->create();
    actingAs($user);

    expect(Score::query()->count())->toBe(0);

    $response = get(route('scores.create'));

    $draft = Score::query()->sole();

    expect($draft->user_id)->toBe($user->id)
        ->and($draft->title)->toBe(__('Untitled score'))
        ->and($draft->content)->toBeNull();

    $response->assertRedirect(route('scores.edit', $draft));
});

it('names the draft after the music it is created for', function () {
    $user = User::factory()->create();
    $music = Music::factory()->create(['title' => 'Public Hymn']);
    actingAs($user);

    get(route('scores.create', ['music' => $music->id]));

    $draft = Score::query()->sole();

    expect($draft->title)->toBe('Public Hymn')
        ->and($draft->music_id)->toBe($music->id);
});

it('reopens the untouched draft instead of piling up new ones', function () {
    $user = User::factory()->create();
    actingAs($user);

    get(route('scores.create'));
    $draft = Score::query()->sole();

    get(route('scores.create'))->assertRedirect(route('scores.edit', $draft));

    expect(Score::query()->count())->toBe(1);
});

it('starts a new draft once the previous one has been worked on', function () {
    $user = User::factory()->create();
    actingAs($user);

    get(route('scores.create'));
    Score::query()->sole()->update(['content' => "X:1\nK:C\nC|"]);

    get(route('scores.create'));

    expect(Score::query()->count())->toBe(2);
});

it('leaves another users untouched draft alone', function () {
    $otherUser = User::factory()->create();
    Score::factory()->create([
        'user_id' => $otherUser->id,
        'title' => __('Untitled score'),
        'content' => null,
        'music_id' => null,
    ]);

    actingAs(User::factory()->create());

    get(route('scores.create'));

    expect(Score::query()->count())->toBe(2);
});

it('does not create a draft on the public preview page', function () {
    actingAs(User::factory()->create());

    get(route('score.preview'))->assertOk();

    expect(Score::query()->count())->toBe(0);
});

it('writes edits back to the score without a toast or a redirect', function () {
    $user = User::factory()->create();
    $score = Score::factory()->create([
        'user_id' => $user->id,
        'title' => __('Untitled score'),
        'format' => ScoreFormat::Abc,
        'content' => null,
    ]);

    actingAs($user);

    Livewire::test(ScoreEditor::class, ['score' => $score])
        ->set('title', 'Kyrie')
        ->set('content', "X:1\nK:C\nC D E F|")
        ->call('autosave')
        ->assertHasNoErrors()
        ->assertNoRedirect()
        ->assertNotDispatched('toast')
        ->assertDispatched('score-autosaved');

    expect($score->fresh())
        ->title->toBe('Kyrie')
        ->content->toBe("X:1\nK:C\nC D E F|");
});

it('keeps the stored title when the title field is emptied mid-edit', function () {
    $user = User::factory()->create();
    $score = Score::factory()->create(['user_id' => $user->id, 'title' => 'Kyrie']);

    actingAs($user);

    Livewire::test(ScoreEditor::class, ['score' => $score])
        ->set('title', '   ')
        ->call('autosave');

    expect($score->fresh()->title)->toBe('Kyrie');
});

it('does not report validation errors while the score is still incomplete', function () {
    $user = User::factory()->create();
    $score = Score::factory()->create(['user_id' => $user->id, 'content' => 'something']);

    actingAs($user);

    Livewire::test(ScoreEditor::class, ['score' => $score])
        ->set('content', '')
        ->call('autosave')
        ->assertHasNoErrors();

    expect($score->fresh()->content)->toBe('');
});

it('never clears the typed source when the links-only switch is on', function () {
    $user = User::factory()->create();
    $score = Score::factory()->create([
        'user_id' => $user->id,
        'format' => ScoreFormat::Abc,
        'content' => "X:1\nK:C\nC|",
    ]);
    ScoreUrl::query()->create(['score_id' => $score->id, 'url' => 'https://example.com/pdf']);

    actingAs($user);

    Livewire::test(ScoreEditor::class, ['score' => $score])
        ->set('linksOnly', true)
        ->call('autosave');

    expect($score->fresh())
        ->content->toBe("X:1\nK:C\nC|")
        ->format->toBe(ScoreFormat::Abc);
});

it('merges the ratio settings sent by the editor into the stored settings', function () {
    $user = User::factory()->create();
    $score = Score::factory()->create([
        'user_id' => $user->id,
        'format' => ScoreFormat::Abc,
        'content' => "X:1\nK:C\nC|",
        'settings' => ['abc' => ['a4' => ['abcZoom' => 1]]],
    ]);

    actingAs($user);

    Livewire::test(ScoreEditor::class, ['score' => $score])
        ->call('autosave', ['a5' => ['abcZoom' => 2]]);

    expect($score->fresh()->settings)->toBe([
        'abc' => ['a4' => ['abcZoom' => 1], 'a5' => ['abcZoom' => 2]],
    ]);
});

it('stores the incipit handed to it by an autosave', function () {
    $user = User::factory()->create();
    $score = Score::factory()->create([
        'user_id' => $user->id,
        'format' => ScoreFormat::Abc,
        'content' => "X:1\nK:C\nC|",
    ]);

    $pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');

    actingAs($user);

    Livewire::test(ScoreEditor::class, ['score' => $score])
        ->call('autosave', null, 'data:image/png;base64,'.base64_encode($pngBytes));

    Storage::assertExists($score->incipit_path);
});

it('writes the score back when a format is picked', function () {
    $user = User::factory()->create();
    $score = Score::factory()->create([
        'user_id' => $user->id,
        'format' => ScoreFormat::Abc,
        'content' => 'name: Kyrie;',
    ]);

    actingAs($user);

    Livewire::test(ScoreEditor::class, ['score' => $score])
        ->call('selectFormat', ScoreFormat::Gabc->value);

    expect($score->fresh()->format)->toBe(ScoreFormat::Gabc);
});

it('writes the score back when the public preview box is ticked', function () {
    $user = User::factory()->create();
    $music = Music::factory()->create();
    $score = Score::factory()->create([
        'user_id' => $user->id,
        'music_id' => $music->id,
        'format' => ScoreFormat::Abc,
        'content' => "X:1\nK:C\nC|",
        'public_preview' => false,
    ]);

    actingAs($user);

    Livewire::test(ScoreEditor::class, ['score' => $score])
        ->set('publicPreview', true);

    expect($score->fresh()->public_preview)->toBeTrue();
});

it('refuses to autosave another users score', function () {
    $score = Score::factory()->create(['user_id' => User::factory()->create()->id]);

    actingAs(User::factory()->create());

    Livewire::test(ScoreEditor::class)
        ->set('score', $score)
        ->call('autosave')
        ->assertForbidden();
});

it('turns autosave on for a saved score and off on the guest preview page', function () {
    $user = User::factory()->create();
    $score = Score::factory()->create([
        'user_id' => $user->id,
        'format' => ScoreFormat::Abc,
        'content' => "X:1\nK:C\nC|",
    ]);

    actingAs($user);

    get(route('scores.edit', $score))
        ->assertOk()
        ->assertSee('autosave: true', false);

    get(route('score.preview'))
        ->assertOk()
        ->assertSee('autosave: false', false);
});

it('lists a draft in the library the moment it is created', function () {
    $user = User::factory()->create();
    actingAs($user);

    get(route('scores.create'));

    get(route('scores'))
        ->assertOk()
        ->assertSee(__('Untitled score'));
});
