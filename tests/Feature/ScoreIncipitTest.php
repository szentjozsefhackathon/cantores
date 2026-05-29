<?php

use App\Enums\ScoreFormat;
use App\Livewire\Pages\ScoreEditor;
use App\Models\Score;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function () {
    Storage::fake();
});

it('stores an incipit image when saving a score with a valid png data url', function () {
    $user = User::factory()->create();
    actingAs($user);

    $pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
    $dataUrl = 'data:image/png;base64,'.base64_encode($pngBytes);

    Livewire::test(ScoreEditor::class)
        ->set('title', 'Incipit Score')
        ->set('format', ScoreFormat::Abc->value)
        ->set('content', "X:1\nK:C\nC D E F|")
        ->call('save', null, null, $dataUrl)
        ->assertHasNoErrors();

    $score = Score::query()->firstWhere('title', 'Incipit Score');
    expect($score)->not->toBeNull();
    expect($score->incipit_path)->toBe("incipits/{$score->id}.png");
    Storage::assertExists($score->incipit_path);
});

it('does not store an incipit file when saving without incipit data', function () {
    $user = User::factory()->create();
    actingAs($user);

    Livewire::test(ScoreEditor::class)
        ->set('title', 'No Incipit Score')
        ->set('format', ScoreFormat::Abc->value)
        ->set('content', "X:1\nK:C\nC D E F|")
        ->call('save')
        ->assertHasNoErrors();

    $score = Score::query()->firstWhere('title', 'No Incipit Score');
    expect($score)->not->toBeNull();
    Storage::assertMissing($score->incipit_path);
});

it('ignores a malformed incipit data url', function () {
    $user = User::factory()->create();
    actingAs($user);

    Livewire::test(ScoreEditor::class)
        ->set('title', 'Bad Incipit Score')
        ->set('format', ScoreFormat::Abc->value)
        ->set('content', "X:1\nK:C\nC D E F|")
        ->call('save', null, null, 'data:image/jpeg;base64,notvalid')
        ->assertHasNoErrors();

    $score = Score::query()->firstWhere('title', 'Bad Incipit Score');
    expect($score)->not->toBeNull();
    Storage::assertMissing($score->incipit_path);
});

it('serves the incipit image to the score owner', function () {
    $user = User::factory()->create();
    $score = Score::factory()->unattached()->create(['user_id' => $user->id]);
    Storage::put($score->incipit_path, 'fake-png-data');

    actingAs($user);

    get(route('scores.incipit', $score))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');
});

it('returns 403 when another user tries to access the incipit', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $score = Score::factory()->unattached()->create(['user_id' => $owner->id]);
    Storage::put($score->incipit_path, 'fake-png-data');

    actingAs($other);

    get(route('scores.incipit', $score))->assertForbidden();
});

it('returns 404 when the incipit file does not exist', function () {
    $user = User::factory()->create();
    $score = Score::factory()->unattached()->create(['user_id' => $user->id]);

    actingAs($user);

    get(route('scores.incipit', $score))->assertNotFound();
});
