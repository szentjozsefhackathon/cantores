<?php

use App\Enums\ScoreFileRenderStatus;
use App\Enums\ScoreFileRights;
use App\Jobs\RenderScoreFileJob;
use App\Livewire\Pages\ScoreEditor;
use App\Models\Folder;
use App\Models\Music;
use App\Models\Score;
use App\Models\ScoreFile;
use App\Models\User;
use App\Services\ScoreFileStorage;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

/**
 * A score with everything an owner sets up on one: siblings' music, a source,
 * render settings, a link and a folder.
 */
function scoreReadyToCopy(User $user, Music $music): Score
{
    $score = Score::factory()->abc()->create([
        'user_id' => $user->id,
        'music_id' => $music->id,
        'title' => 'Ave Maria',
        'variation_name' => 'Kórus',
        'settings' => ['abc' => ['a4' => ['staffWidth' => 700]]],
        'public_preview' => true,
    ]);

    $score->urls()->create(['url' => 'https://example.com/source', 'label' => null, 'comment' => 'Where it came from']);
    $folder = Folder::factory()->create(['user_id' => $user->id]);
    $score->folders()->attach($folder->id);

    return $score;
}

it('copies the score when a variation is added', function () {
    $user = User::factory()->create();
    $music = Music::factory()->create();
    $score = scoreReadyToCopy($user, $music);

    actingAs($user);

    Livewire::test(ScoreEditor::class, ['score' => $score])
        ->call('addVariation')
        ->assertHasNoErrors();

    $copy = Score::query()->where('id', '!=', $score->id)->latest('id')->first();

    expect($copy)->not->toBeNull()
        ->and($copy->user_id)->toBe($user->id)
        ->and($copy->music_id)->toBe($music->id)
        ->and($copy->title)->toBe('Ave Maria')
        ->and($copy->format)->toBe($score->format)
        ->and($copy->content)->toBe($score->content)
        ->and($copy->settings)->toBe($score->settings);
});

it('redirects to the copy so the new variation is what gets edited', function () {
    $user = User::factory()->create();
    $score = scoreReadyToCopy($user, Music::factory()->create());

    actingAs($user);

    $component = Livewire::test(ScoreEditor::class, ['score' => $score])
        ->call('addVariation');

    $copy = Score::query()->where('id', '!=', $score->id)->latest('id')->first();

    expect($copy)->not->toBeNull();

    $component->assertRedirect(route('scores.edit', $copy));
});

it('names the copy apart from the variation it came from', function () {
    $user = User::factory()->create();
    $score = scoreReadyToCopy($user, Music::factory()->create());

    actingAs($user);

    Livewire::test(ScoreEditor::class, ['score' => $score])->call('addVariation');

    $copy = Score::query()->where('id', '!=', $score->id)->latest('id')->first();

    expect($copy->variation_name)->toBe(__(':name (copy)', ['name' => 'Kórus']));
});

it('names the copy after the title when the original has no variation name', function () {
    $user = User::factory()->create();
    $score = Score::factory()->abc()->create([
        'user_id' => $user->id,
        'music_id' => Music::factory()->create()->id,
        'title' => 'Ave Maria',
        'variation_name' => null,
    ]);

    actingAs($user);

    Livewire::test(ScoreEditor::class, ['score' => $score])->call('addVariation');

    $copy = Score::query()->where('id', '!=', $score->id)->latest('id')->first();

    expect($copy->variation_name)->toBe(__(':name (copy)', ['name' => 'Ave Maria']));
});

it('keeps the copied variation name within the column', function () {
    $user = User::factory()->create();
    $score = Score::factory()->abc()->create([
        'user_id' => $user->id,
        'music_id' => Music::factory()->create()->id,
        'variation_name' => str_repeat('a', 120),
    ]);

    actingAs($user);

    Livewire::test(ScoreEditor::class, ['score' => $score])->call('addVariation');

    $copy = Score::query()->where('id', '!=', $score->id)->latest('id')->first();

    expect(mb_strlen((string) $copy->variation_name))->toBeLessThanOrEqual(120);
});

it('copies the links and the folders of the original', function () {
    $user = User::factory()->create();
    $score = scoreReadyToCopy($user, Music::factory()->create());

    actingAs($user);

    Livewire::test(ScoreEditor::class, ['score' => $score])->call('addVariation');

    $copy = Score::query()->where('id', '!=', $score->id)->latest('id')->first();

    expect($copy->urls)->toHaveCount(1)
        ->and($copy->urls->first()->url)->toBe('https://example.com/source')
        ->and($copy->urls->first()->comment)->toBe('Where it came from')
        ->and($copy->folders->pluck('id')->all())->toBe($score->folders->pluck('id')->all());
});

it('leaves the original untouched', function () {
    $user = User::factory()->create();
    $score = scoreReadyToCopy($user, Music::factory()->create());

    actingAs($user);

    Livewire::test(ScoreEditor::class, ['score' => $score])->call('addVariation');

    $score->refresh();

    expect($score->variation_name)->toBe('Kórus')
        ->and($score->urls)->toHaveCount(1)
        ->and($score->public_preview)->toBeTrue();
});

it('does not carry the original public preview over to the copy', function () {
    $user = User::factory()->create();
    $score = scoreReadyToCopy($user, Music::factory()->create());

    actingAs($user);

    Livewire::test(ScoreEditor::class, ['score' => $score])->call('addVariation');

    $copy = Score::query()->where('id', '!=', $score->id)->latest('id')->first();

    expect($copy->public_preview)->toBeFalse()
        ->and($copy->share_token)->toBeNull()
        ->and($copy->publication)->toBeNull();
});

it('copies what is on screen rather than what was last saved', function () {
    $user = User::factory()->create();
    $score = scoreReadyToCopy($user, Music::factory()->create());

    actingAs($user);

    Livewire::test(ScoreEditor::class, ['score' => $score])
        ->set('content', "X:1\nT:Edited on screen\nK:C\nG A B c|")
        ->call('addVariation');

    $copy = Score::query()->where('id', '!=', $score->id)->latest('id')->first();

    expect($copy->content)->toContain('Edited on screen')
        ->and($score->fresh()->content)->toContain('Edited on screen');
});

it('copies the uploaded files with their bytes and render artifacts', function () {
    Storage::fake('private');
    Queue::fake();

    $user = User::factory()->create();
    $score = scoreReadyToCopy($user, Music::factory()->create());

    $file = ScoreFile::factory()->ready(2)->create([
        'score_id' => $score->id,
        'label' => 'A4',
        'rights' => ScoreFileRights::OwnWork,
        'is_published' => true,
    ]);

    $storage = app(ScoreFileStorage::class);
    $storage->put($file->path, 'source bytes');
    $storage->put($file->pagePath(1), 'page one');
    $storage->put($file->thumbPath(), 'thumb');

    actingAs($user);

    Livewire::test(ScoreEditor::class, ['score' => $score])->call('addVariation');

    $copy = Score::query()->where('id', '!=', $score->id)->latest('id')->first();
    $copiedFile = $copy->files()->first();

    expect($copiedFile)->not->toBeNull()
        ->and($copiedFile->original_name)->toBe($file->original_name)
        ->and($copiedFile->label)->toBe('A4')
        ->and($copiedFile->checksum)->toBe($file->checksum)
        ->and($copiedFile->render_status)->toBe(ScoreFileRenderStatus::Ready)
        ->and($copiedFile->page_count)->toBe(2)
        ->and($copiedFile->path)->toBe($copiedFile->directory().'/source.'.$copiedFile->extension())
        ->and($storage->get($copiedFile->path))->toBe('source bytes')
        ->and($storage->get($copiedFile->pagePath(1)))->toBe('page one')
        ->and($storage->get($copiedFile->thumbPath()))->toBe('thumb');

    Queue::assertNothingPushed();
});

it('does not offer a copied file to the public library', function () {
    Storage::fake('private');
    Queue::fake();

    $user = User::factory()->create();
    $score = scoreReadyToCopy($user, Music::factory()->create());

    ScoreFile::factory()->ready()->create(['score_id' => $score->id, 'is_published' => true]);

    actingAs($user);

    Livewire::test(ScoreEditor::class, ['score' => $score])->call('addVariation');

    $copy = Score::query()->where('id', '!=', $score->id)->latest('id')->first();

    expect($copy->files()->first()->is_published)->toBeFalse();
});

it('queues a render for a copied file the renderer had not finished', function () {
    Storage::fake('private');
    Queue::fake();

    $user = User::factory()->create();
    $score = scoreReadyToCopy($user, Music::factory()->create());

    $file = ScoreFile::factory()->create(['score_id' => $score->id]);
    app(ScoreFileStorage::class)->put($file->path, 'source bytes');

    actingAs($user);

    Livewire::test(ScoreEditor::class, ['score' => $score])->call('addVariation');

    $copy = Score::query()->where('id', '!=', $score->id)->latest('id')->first();
    $copiedFile = $copy->files()->first();

    expect($copiedFile->render_status)->toBe(ScoreFileRenderStatus::Pending);

    Queue::assertPushed(RenderScoreFileJob::class, fn (RenderScoreFileJob $job): bool => $job->scoreFile->is($copiedFile));
});

it('copies the incipit so the new variation shows one straight away', function () {
    Storage::fake();

    $user = User::factory()->create();
    $score = scoreReadyToCopy($user, Music::factory()->create());

    Storage::put($score->incipit_path, 'incipit bytes');

    actingAs($user);

    Livewire::test(ScoreEditor::class, ['score' => $score])->call('addVariation');

    $copy = Score::query()->where('id', '!=', $score->id)->latest('id')->first();

    expect(Storage::get($copy->incipit_path))->toBe('incipit bytes');
});

it('copies a links-only score without demanding a source', function () {
    $user = User::factory()->create();
    $score = Score::factory()->linksOnly()->create([
        'user_id' => $user->id,
        'music_id' => Music::factory()->create()->id,
        'title' => 'Only links',
    ]);
    $score->urls()->create(['url' => 'https://example.com/sheet']);

    actingAs($user);

    Livewire::test(ScoreEditor::class, ['score' => $score])
        ->call('addVariation')
        ->assertHasNoErrors();

    $copy = Score::query()->where('id', '!=', $score->id)->latest('id')->first();

    expect($copy->format)->toBeNull()
        ->and($copy->content)->toBeNull()
        ->and($copy->urls)->toHaveCount(1);
});

it('refuses to copy a score belonging to someone else', function () {
    $owner = User::factory()->create();
    $score = scoreReadyToCopy($owner, Music::factory()->create());

    actingAs(User::factory()->create());

    Livewire::test(ScoreEditor::class, ['score' => $score])->assertForbidden();

    expect(Score::query()->where('id', '!=', $score->id)->exists())->toBeFalse();
});

it('offers the copying button once the score exists', function () {
    $user = User::factory()->create();
    $score = scoreReadyToCopy($user, Music::factory()->create());

    actingAs($user);

    Livewire::test(ScoreEditor::class, ['score' => $score])
        ->assertSee(__('Add variation'))
        ->assertSeeHtml('addVariation()');
});
