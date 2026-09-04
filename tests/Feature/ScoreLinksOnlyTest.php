<?php

use App\Livewire\Pages\ScoreEditor;
use App\Livewire\Pages\Scores;
use App\Livewire\Pages\ScoreView;
use App\Models\Score;
use App\Models\ScoreUrl;
use App\Models\Share;
use App\Models\User;
use App\MusicUrlLabel;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

it('creates a links-only score with a staged link and no notation', function () {
    $user = User::factory()->create();

    actingAs($user);

    Livewire::test(ScoreEditor::class)
        ->set('linksOnly', true)
        ->set('title', 'My Link Collection')
        ->set('newUrl', 'https://www.youtube.com/watch?v=abc')
        ->set('newUrlLabel', MusicUrlLabel::Video->value)
        ->call('addUrl')
        ->assertHasNoErrors()
        ->call('save')
        ->assertHasNoErrors();

    $score = Score::query()->firstWhere('title', 'My Link Collection');

    expect($score)
        ->not->toBeNull()
        ->format->toBeNull()
        ->content->toBeNull()
        ->user_id->toBe($user->id);

    expect($score->urls()->get())
        ->toHaveCount(1)
        ->first()->url->toBe('https://www.youtube.com/watch?v=abc');
});

it('rejects a links-only score that has no links', function () {
    $user = User::factory()->create();

    actingAs($user);

    Livewire::test(ScoreEditor::class)
        ->set('linksOnly', true)
        ->set('title', 'Nothing here')
        ->call('save')
        ->assertHasErrors(['newUrl']);

    expect(Score::query()->count())->toBe(0);
});

it('still requires notation content for a notation score', function () {
    $user = User::factory()->create();

    actingAs($user);

    Livewire::test(ScoreEditor::class)
        ->set('linksOnly', false)
        ->set('title', 'No content')
        ->set('content', '')
        ->call('save')
        ->assertHasErrors(['content']);

    expect(Score::query()->count())->toBe(0);
});

it('stages multiple links during creation and can remove a pending one', function () {
    $user = User::factory()->create();

    actingAs($user);

    $component = Livewire::test(ScoreEditor::class)
        ->set('linksOnly', true)
        ->set('title', 'Staged links')
        ->set('newUrl', 'https://example.com/a')
        ->call('addUrl')
        ->set('newUrl', 'https://example.com/b')
        ->call('addUrl');

    expect($component->get('pendingUrls'))->toHaveCount(2);

    $component->call('removePendingUrl', 0);

    expect($component->get('pendingUrls'))->toHaveCount(1);

    $component->call('save')->assertHasNoErrors();

    $score = Score::query()->firstWhere('title', 'Staged links');

    expect($score->urls()->get()->pluck('url')->all())->toBe(['https://example.com/b']);
});

it('picks the links-and-files format from the format row', function () {
    $user = User::factory()->create();

    actingAs($user);

    Livewire::test(ScoreEditor::class)
        ->assertSee(__('Links and files'))
        ->call('selectLinksOnly')
        ->assertSet('linksOnly', true);
});

it('switches back to notation and sets the format when a format is selected', function () {
    $user = User::factory()->create();

    actingAs($user);

    Livewire::test(ScoreEditor::class)
        ->set('linksOnly', true)
        ->call('selectFormat', 'gabc')
        ->assertSet('linksOnly', false)
        ->assertSet('format', 'gabc');
});

it('ignores an invalid format when selecting', function () {
    $user = User::factory()->create();

    actingAs($user);

    Livewire::test(ScoreEditor::class)
        ->call('selectFormat', 'not-a-format')
        ->assertSet('format', 'abc');
});

it('initializes linksOnly when editing a links-only score', function () {
    $user = User::factory()->create();
    $score = Score::factory()->unattached()->linksOnly()->create(['user_id' => $user->id]);
    ScoreUrl::create(['score_id' => $score->id, 'url' => 'https://example.com']);

    actingAs($user);

    Livewire::test(ScoreEditor::class, ['score' => $score])
        ->assertSet('linksOnly', true);
});

it('shows a Links badge for a links-only score in the list', function () {
    $user = User::factory()->create();
    Score::factory()->unattached()->linksOnly()->create(['user_id' => $user->id, 'title' => 'Link Score']);
    Score::factory()->unattached()->abc()->create(['user_id' => $user->id, 'title' => 'Notation Score']);

    actingAs($user);

    Livewire::test(Scores::class)
        ->assertSee('Link Score')
        ->assertSee('Notation Score')
        ->assertSee(__('Links'))
        ->assertSee(__('ABC'));
});

it('renders a links-only score on the read-only share page', function () {
    $score = Score::factory()->unattached()->linksOnly()->create();
    $share = Share::factory()->of($score)->create();
    ScoreUrl::create([
        'score_id' => $score->id,
        'url' => 'https://drive.google.com/links-only',
        'label' => MusicUrlLabel::SheetMusic->value,
    ]);

    Livewire::test(ScoreView::class, ['token' => $share->token])
        ->assertHasNoErrors()
        ->assertSee('https://drive.google.com/links-only');
});
