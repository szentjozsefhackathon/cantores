<?php

use App\Livewire\Pages\ScoreEditor;
use App\Livewire\Pages\ScoreView;
use App\Models\Loan;
use App\Models\Score;
use App\Models\ScoreUrl;
use App\Models\User;
use App\MusicUrlLabel;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

it('stores the url encrypted in the database', function () {
    $user = User::factory()->create();
    $score = Score::factory()->unattached()->create(['user_id' => $user->id]);

    actingAs($user);

    Livewire::test(ScoreEditor::class, ['score' => $score])
        ->set('newUrl', 'https://drive.google.com/test')
        ->call('addUrl')
        ->assertHasNoErrors();

    $raw = DB::table('score_urls')->where('score_id', $score->id)->value('url');

    expect($raw)->not->toBe('https://drive.google.com/test');
    expect(ScoreUrl::query()->first()->url)->toBe('https://drive.google.com/test');
});

it('can add a url with label and comment', function () {
    $user = User::factory()->create();
    $score = Score::factory()->unattached()->create(['user_id' => $user->id]);

    actingAs($user);

    Livewire::test(ScoreEditor::class, ['score' => $score])
        ->set('newUrl', 'https://www.youtube.com/watch?v=abc')
        ->set('newUrlLabel', MusicUrlLabel::Video->value)
        ->set('newUrlComment', 'Live recording')
        ->call('addUrl')
        ->assertHasNoErrors();

    $scoreUrl = ScoreUrl::query()->first();

    expect($scoreUrl->url)->toBe('https://www.youtube.com/watch?v=abc')
        ->and($scoreUrl->label)->toBe(MusicUrlLabel::Video)
        ->and($scoreUrl->comment)->toBe('Live recording');
});

it('rejects an invalid url', function () {
    $user = User::factory()->create();
    $score = Score::factory()->unattached()->create(['user_id' => $user->id]);

    actingAs($user);

    Livewire::test(ScoreEditor::class, ['score' => $score])
        ->set('newUrl', 'not-a-url')
        ->call('addUrl')
        ->assertHasErrors(['newUrl']);

    expect(ScoreUrl::query()->count())->toBe(0);
});

it('clears the form after adding a url', function () {
    $user = User::factory()->create();
    $score = Score::factory()->unattached()->create(['user_id' => $user->id]);

    actingAs($user);

    Livewire::test(ScoreEditor::class, ['score' => $score])
        ->set('newUrl', 'https://example.com')
        ->set('newUrlLabel', MusicUrlLabel::Audio->value)
        ->set('newUrlComment', 'Some comment')
        ->call('addUrl')
        ->assertSet('newUrl', '')
        ->assertSet('newUrlLabel', null)
        ->assertSet('newUrlComment', '');
});

it('can delete a url belonging to the score', function () {
    $user = User::factory()->create();
    $score = Score::factory()->unattached()->create(['user_id' => $user->id]);
    $scoreUrl = ScoreUrl::create(['score_id' => $score->id, 'url' => 'https://example.com']);

    actingAs($user);

    Livewire::test(ScoreEditor::class, ['score' => $score])
        ->call('deleteUrl', $scoreUrl->id)
        ->assertHasNoErrors();

    expect(ScoreUrl::query()->find($scoreUrl->id))->toBeNull();
});

it('prevents deleting a url belonging to another score', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $score = Score::factory()->unattached()->create(['user_id' => $user->id]);
    $otherScore = Score::factory()->unattached()->create(['user_id' => $otherUser->id]);
    $otherUrl = ScoreUrl::create(['score_id' => $otherScore->id, 'url' => 'https://example.com']);

    actingAs($user);

    Livewire::test(ScoreEditor::class, ['score' => $score])
        ->call('deleteUrl', $otherUrl->id)
        ->assertNotFound();
});

it('displays urls on the read-only view page', function () {
    $score = Score::factory()->unattached()->create();
    $loan = Loan::factory()->of($score)->create();
    ScoreUrl::create([
        'score_id' => $score->id,
        'url' => 'https://drive.google.com/my-file',
        'label' => MusicUrlLabel::SheetMusic->value,
        'comment' => 'Full score PDF',
    ]);

    Livewire::test(ScoreView::class, ['token' => $loan->token])
        ->assertSee('Full score PDF')
        ->assertSee('https://drive.google.com/my-file');
});

it('keeps the add-link form in a dialog and closes it once the link is added', function () {
    $user = User::factory()->create();
    $score = Score::factory()->unattached()->create(['user_id' => $user->id]);

    actingAs($user);

    Livewire::test(ScoreEditor::class, ['score' => $score])
        ->assertSeeHtml('data-modal="score-url-add"')
        ->assertSeeHtml("fluxModal('score-url-add'")
        ->set('newUrl', 'https://example.com/score')
        ->call('addUrl')
        ->assertHasNoErrors()
        ->assertDispatched('score-url-added');
});

it('keeps the dialog open when the link does not validate', function () {
    $user = User::factory()->create();
    $score = Score::factory()->unattached()->create(['user_id' => $user->id]);

    actingAs($user);

    Livewire::test(ScoreEditor::class, ['score' => $score])
        ->set('newUrl', 'not-a-url')
        ->call('addUrl')
        ->assertHasErrors('newUrl')
        ->assertNotDispatched('score-url-added');
});

it('empties the add-link form when the dialog is dismissed', function () {
    $user = User::factory()->create();
    $score = Score::factory()->unattached()->create(['user_id' => $user->id]);

    actingAs($user);

    Livewire::test(ScoreEditor::class, ['score' => $score])
        ->set('newUrl', 'not-a-url')
        ->call('addUrl')
        ->call('cancelUrlAdd')
        ->assertSet('newUrl', '')
        ->assertSet('newUrlComment', '')
        ->assertHasNoErrors();
});
