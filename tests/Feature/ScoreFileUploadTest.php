<?php

use App\Enums\ScoreFileRenderStatus;
use App\Enums\ScoreFileRights;
use App\Jobs\RenderScoreFileJob;
use App\Livewire\Pages\ScoreEditor;
use App\Models\Loan;
use App\Models\Score;
use App\Models\ScoreFile;
use App\Models\ScoreUrl;
use App\Models\User;
use App\MusicUrlLabel;
use App\Services\MuseScoreMetadata;
use App\Services\MuseScoreRenderer;
use App\Services\PdfPageRasterizer;
use App\Services\ScoreFileIncipitCropper;
use App\Services\ScoreFileStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Js;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

// Some of these reach a file through a lending link. What the link serves is the
// subject here, not the Turnstile gate in front of it, which is tested apart.
beforeEach(function () {
    passHumanCheck();
});

/**
 * A .mscz is a ZIP holding the score's .mscx XML and the thumbnail MuseScore
 * saved with it, so one can be built here without MuseScore being installed.
 */
function makeMscz(?string $title = 'Veni Creator', ?string $thumbnail = null): string
{
    $metaTags = '';
    foreach (['workTitle' => $title, 'composer' => 'Anonymus', 'lyricist' => 'Rabanus Maurus'] as $name => $value) {
        $metaTags .= sprintf('<metaTag name="%s">%s</metaTag>', $name, htmlspecialchars((string) $value));
    }

    $mscx = '<?xml version="1.0" encoding="UTF-8"?><museScore version="4.70"><Score>'
        .$metaTags
        .'</Score></museScore>';

    $path = tempnam(sys_get_temp_dir(), 'mscz-test-');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::OVERWRITE);
    $zip->addFromString('score.mscx', $mscx);
    $zip->addFromString('Thumbnails/thumbnail.png', $thumbnail ?? makePng(362, 512));
    $zip->close();

    $bytes = (string) file_get_contents($path);
    @unlink($path);

    return $bytes;
}

function makePng(int $width, int $height): string
{
    $image = imagecreatetruecolor($width, $height);
    imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));

    ob_start();
    imagepng($image);
    $bytes = (string) ob_get_clean();
    imagedestroy($image);

    return $bytes;
}

function fakeRenderer(int $pageCount = 2): void
{
    test()->mock(MuseScoreRenderer::class)
        ->shouldReceive('render')
        ->andReturn('%PDF-1.7 fake');

    test()->mock(PdfPageRasterizer::class)
        ->shouldReceive('rasterize')
        ->andReturn(array_fill(0, $pageCount, makePng(1240, 1754)))
        ->shouldReceive('rasterizePage')
        ->andReturn(makePng(1654, 2339));
}

it('reads title, composer and thumbnail out of a .mscz', function () {
    $metadata = app(MuseScoreMetadata::class)->read(makeMscz());

    expect($metadata['title'])->toBe('Veni Creator')
        ->and($metadata['composer'])->toBe('Anonymus')
        ->and($metadata['lyricist'])->toBe('Rabanus Maurus')
        ->and($metadata['thumbnail'])->toStartWith("\x89PNG");
});

it('returns nothing for a file that is not a zip', function () {
    expect(app(MuseScoreMetadata::class)->read('not a zip at all'))
        ->toBe(['title' => null, 'composer' => null, 'lyricist' => null, 'thumbnail' => null]);
});

it('rejects a file type the renderer cannot read', function () {
    Storage::fake('private');
    actingAs(User::factory()->create());

    Livewire::test(ScoreEditor::class)
        ->set('linksOnly', true)
        ->set('title', 'Notes')
        ->set('pendingFile', UploadedFile::fake()->createWithContent('notes.txt', 'hello'))
        ->assertHasErrors('pendingFile');
});

it('rejects a file over the size cap', function () {
    Storage::fake('private');
    actingAs(User::factory()->create());

    Livewire::test(ScoreEditor::class)
        ->set('linksOnly', true)
        ->set('title', 'Big')
        ->set('pendingFile', UploadedFile::fake()->create('big.mscz', 25601))
        ->assertHasErrors('pendingFile');
});

it('prefills an empty title from the uploaded .mscz', function () {
    Storage::fake('private');
    actingAs(User::factory()->create());

    Livewire::test(ScoreEditor::class)
        ->set('pendingFile', UploadedFile::fake()->createWithContent('veni.mscz', makeMscz()))
        ->assertSet('title', 'Veni Creator')
        ->assertSet('linksOnly', true);
});

it('saves a links-only score whose only content is an uploaded file', function () {
    Storage::fake('private');
    fakeRenderer();
    $user = User::factory()->create();
    actingAs($user);

    Livewire::test(ScoreEditor::class)
        ->set('linksOnly', true)
        ->set('title', 'Veni Creator')
        ->set('fileRights', ScoreFileRights::OwnWork->value)
        ->set('pendingFile', UploadedFile::fake()->createWithContent('veni.mscz', makeMscz()))
        ->call('save')
        ->assertHasNoErrors();

    $score = Score::query()->where('user_id', $user->id)->firstOrFail();
    $scoreFile = $score->primaryFile();

    expect($scoreFile)->not->toBeNull()
        ->and($scoreFile->original_name)->toBe('veni.mscz')
        ->and($scoreFile->rights)->toBe(ScoreFileRights::OwnWork)
        ->and($scoreFile->path)->toBe("score-files/{$scoreFile->id}/source.mscz");
});

it('stores the uploaded bytes encrypted', function () {
    Storage::fake('private');
    fakeRenderer();
    $user = User::factory()->create();
    actingAs($user);

    $source = makeMscz();

    Livewire::test(ScoreEditor::class)
        ->set('linksOnly', true)
        ->set('title', 'Veni Creator')
        ->set('pendingFile', UploadedFile::fake()->createWithContent('veni.mscz', $source))
        ->call('save')
        ->assertHasNoErrors();

    $scoreFile = Score::query()->where('user_id', $user->id)->firstOrFail()->primaryFile();

    $stored = Storage::disk('private')->get($scoreFile->path);

    expect($stored)->not->toBe($source)
        ->and(app(ScoreFileStorage::class)->get($scoreFile->path))->toBe($source)
        ->and($scoreFile->checksum)->toBe(hash('sha256', $source));
});

it('gives the score an incipit from the embedded thumbnail before rendering finishes', function () {
    Storage::fake('private');
    $user = User::factory()->create();
    $score = Score::factory()->linksOnly()->create(['user_id' => $user->id]);

    // No renderer mock: the job runs synchronously here and fails, which is the
    // point — the interim preview must survive a render that never succeeds.
    $upload = UploadedFile::fake()->createWithContent('veni.mscz', makeMscz());

    try {
        app(\App\Services\ScoreFileUploader::class)->store($score, $upload, ScoreFileRights::OwnWork);
    } catch (\Throwable) {
        // the sync render failure is asserted elsewhere
    }

    $scoreFile = $score->fresh()->primaryFile();

    expect($scoreFile->has_thumbnail)->toBeTrue()
        ->and(Storage::disk('private')->exists($scoreFile->thumbPath()))->toBeTrue()
        ->and($score->fresh()->hasIncipit())->toBeTrue();
});

it('renders an uploaded file into pages, a thumbnail and a page count', function () {
    Storage::fake('private');
    fakeRenderer(pageCount: 3);

    $scoreFile = ScoreFile::factory()->create();
    app(ScoreFileStorage::class)->put($scoreFile->path, makeMscz());

    (new RenderScoreFileJob($scoreFile))->handle(
        app(ScoreFileStorage::class),
        app(MuseScoreRenderer::class),
        app(PdfPageRasterizer::class),
        app(ScoreFileIncipitCropper::class),
    );

    $scoreFile->refresh();

    expect($scoreFile->render_status)->toBe(ScoreFileRenderStatus::Ready)
        ->and($scoreFile->page_count)->toBe(3)
        ->and($scoreFile->has_thumbnail)->toBeTrue()
        ->and($scoreFile->rendered_at)->not->toBeNull()
        ->and(Storage::disk('private')->exists($scoreFile->renderPath()))->toBeTrue()
        ->and(Storage::disk('private')->exists($scoreFile->pagePath(3)))->toBeTrue()
        ->and(Storage::disk('private')->exists($scoreFile->thumbPath()))->toBeTrue();
});

it('records the error when rendering fails', function () {
    Storage::fake('private');

    $this->mock(MuseScoreRenderer::class)
        ->shouldReceive('render')
        ->andThrow(new RuntimeException('mscore exploded'));

    $scoreFile = ScoreFile::factory()->create();
    app(ScoreFileStorage::class)->put($scoreFile->path, makeMscz());

    expect(fn () => (new RenderScoreFileJob($scoreFile))->handle(
        app(ScoreFileStorage::class),
        app(MuseScoreRenderer::class),
        app(PdfPageRasterizer::class),
        app(ScoreFileIncipitCropper::class),
    ))->toThrow(RuntimeException::class);

    $scoreFile->refresh();

    expect($scoreFile->render_status)->toBe(ScoreFileRenderStatus::Failed)
        ->and($scoreFile->render_error)->toContain('mscore exploded');
});

it('marks a file the renderer cannot read as unsupported without running it', function () {
    Storage::fake('private');

    $this->mock(MuseScoreRenderer::class)->shouldNotReceive('render');

    $scoreFile = ScoreFile::factory()->create(['original_name' => 'notes.txt']);

    (new RenderScoreFileJob($scoreFile))->handle(
        app(ScoreFileStorage::class),
        app(MuseScoreRenderer::class),
        app(PdfPageRasterizer::class),
        app(ScoreFileIncipitCropper::class),
    );

    expect($scoreFile->fresh()->render_status)->toBe(ScoreFileRenderStatus::Unsupported);
});

it('does not undo a completed render when the failed hook fires late', function () {
    Storage::fake('private');

    $scoreFile = ScoreFile::factory()->ready()->create();

    (new RenderScoreFileJob($scoreFile))->failed(new RuntimeException('the worker died'));

    expect($scoreFile->fresh()->render_status)->toBe(ScoreFileRenderStatus::Ready);
});

it('deletes the stored artifacts when the score is deleted', function () {
    Storage::fake('private');

    $scoreFile = ScoreFile::factory()->ready()->create();
    $storage = app(ScoreFileStorage::class);
    $storage->put($scoreFile->path, 'source');
    $storage->put($scoreFile->thumbPath(), 'thumb');
    $directory = $scoreFile->directory();

    $scoreFile->score->delete();

    expect(Storage::disk('private')->exists($directory))->toBeFalse()
        ->and(ScoreFile::query()->find($scoreFile->id))->toBeNull();
});

it('crops the incipit to the target width and a half-by-sixth aspect', function () {
    $incipit = app(ScoreFileIncipitCropper::class)->crop(makePng(1654, 2339));

    $image = imagecreatefromstring($incipit);

    expect(imagesx($image))->toBe(ScoreFileIncipitCropper::TARGET_WIDTH)
        // 1654/2 wide by 2339/6 high, scaled to 800 px
        ->and(imagesy($image))->toBe((int) round(800 * round(2339 / 6) / round(1654 / 2)));

    imagedestroy($image);
});

it('serves a rendered page to the owner and refuses it to anyone else', function () {
    Storage::fake('private');

    $owner = User::factory()->create();
    $score = Score::factory()->linksOnly()->create(['user_id' => $owner->id]);
    $scoreFile = ScoreFile::factory()->ready(2)->create(['score_id' => $score->id]);
    app(ScoreFileStorage::class)->put($scoreFile->pagePath(1), makePng(20, 20));

    $url = route('scores.file.page', ['score' => $score, 'scoreFile' => $scoreFile, 'page' => 1]);

    actingAs($owner);
    get($url)->assertOk()->assertHeader('content-type', 'image/png');

    actingAs(User::factory()->create());
    get($url)->assertForbidden();
});

it('404s a page number the file does not have', function () {
    Storage::fake('private');

    $owner = User::factory()->create();
    $score = Score::factory()->linksOnly()->create(['user_id' => $owner->id]);
    $scoreFile = ScoreFile::factory()->ready(1)->create(['score_id' => $score->id]);

    actingAs($owner);

    get(route('scores.file.page', ['score' => $score, 'scoreFile' => $scoreFile, 'page' => 2]))
        ->assertNotFound();
});

it('answers a conditional page request with 304 without decrypting', function () {
    Storage::fake('private');

    $owner = User::factory()->create();
    $score = Score::factory()->linksOnly()->create(['user_id' => $owner->id]);
    $scoreFile = ScoreFile::factory()->ready(1)->create(['score_id' => $score->id]);
    app(ScoreFileStorage::class)->put($scoreFile->pagePath(1), makePng(20, 20));

    actingAs($owner);

    $url = route('scores.file.page', ['score' => $score, 'scoreFile' => $scoreFile, 'page' => 1]);
    $etag = get($url)->assertOk()->headers->get('etag');

    expect($etag)->not->toBeNull();

    get($url, ['If-None-Match' => $etag])
        ->assertStatus(304)
        ->assertNoContent(304);
});

it('serves a page through a live grant and 404s once it is revoked', function () {
    Storage::fake('private');

    $owner = User::factory()->create();
    $score = Score::factory()->linksOnly()->create(['user_id' => $owner->id]);
    $scoreFile = ScoreFile::factory()->ready(1)->create(['score_id' => $score->id]);
    app(ScoreFileStorage::class)->put($scoreFile->pagePath(1), makePng(20, 20));

    $loan = Loan::factory()->of($score)->create();

    $url = route('loan.score.file.page', [
        'token' => $loan->token,
        'score' => $score,
        'scoreFile' => $scoreFile,
        'page' => 1,
    ]);

    get($url)->assertOk();

    $loan->revoke();

    get($url)->assertNotFound();
});

it('gates the original file download on the grant', function () {
    Storage::fake('private');

    $owner = User::factory()->create();
    $score = Score::factory()->linksOnly()->create(['user_id' => $owner->id]);
    $scoreFile = ScoreFile::factory()->ready(1)->create(['score_id' => $score->id]);
    app(ScoreFileStorage::class)->put($scoreFile->path, makeMscz());

    $allowed = Loan::factory()->of($score)->create(['allow_download' => true]);
    $refused = Loan::factory()->of($score)->create(['allow_download' => false]);

    get(route('loan.score.file.download', ['token' => $allowed->token, 'score' => $score, 'scoreFile' => $scoreFile]))
        ->assertOk()
        ->assertDownload($scoreFile->original_name);

    get(route('loan.score.file.download', ['token' => $refused->token, 'score' => $score, 'scoreFile' => $scoreFile]))
        ->assertForbidden();
});

it('serves a file-backed incipit from the encrypted thumbnail', function () {
    Storage::fake('private');

    $owner = User::factory()->create();
    $score = Score::factory()->linksOnly()->create(['user_id' => $owner->id]);
    $scoreFile = ScoreFile::factory()->ready(1)->create(['score_id' => $score->id]);

    $thumbnail = makePng(800, 380);
    app(ScoreFileStorage::class)->put($scoreFile->thumbPath(), $thumbnail);

    actingAs($owner);

    $response = get(route('scores.incipit', $score))->assertOk();

    expect($response->getContent())->toBe($thumbnail);
});

it('offers the rendered pages behind a modal button rather than inline in the editor', function () {
    Storage::fake('private');

    $owner = User::factory()->create();
    $score = Score::factory()->linksOnly()->create(['user_id' => $owner->id]);
    $scoreFile = ScoreFile::factory()->ready(3)->create(['score_id' => $score->id]);

    actingAs($owner);

    $pageUrls = array_map(
        fn (int $page): string => route('scores.file.page', ['score' => $score, 'scoreFile' => $scoreFile, 'page' => $page]),
        [1, 2, 3],
    );

    Livewire::test(ScoreEditor::class, ['score' => $score])
        ->assertSet('filePageUrls', [$scoreFile->id => $pageUrls])
        ->assertSee(__('View sheet music'))
        ->assertSeeHtml('data-modal="score-file-pages-'.$scoreFile->id.'"')
        ->assertDontSeeHtml('<img src="'.$pageUrls[0].'"');
});

it('leaves the page list empty while the render is not ready', function () {
    Storage::fake('private');

    $owner = User::factory()->create();
    $score = Score::factory()->linksOnly()->create(['user_id' => $owner->id]);
    $scoreFile = ScoreFile::factory()->create(['score_id' => $score->id]);

    actingAs($owner);

    Livewire::test(ScoreEditor::class, ['score' => $score])
        ->assertSet('filePageUrls', [$scoreFile->id => []])
        ->assertDontSee(__('View sheet music'));
});

it('polls the editor while the render is still queued and stops once it lands', function () {
    Storage::fake('private');

    $owner = User::factory()->create();
    $score = Score::factory()->linksOnly()->create(['user_id' => $owner->id]);
    $scoreFile = ScoreFile::factory()->create([
        'score_id' => $score->id,
        'render_status' => ScoreFileRenderStatus::Processing,
    ]);

    actingAs($owner);

    $component = Livewire::test(ScoreEditor::class, ['score' => $score]);

    $component->assertSeeHtml('wire:poll.2s')
        ->assertSee(__('Rendering the sheet music — this page updates on its own when it is ready.'));

    $scoreFile->update([
        'render_status' => ScoreFileRenderStatus::Ready,
        'page_count' => 1,
        'rendered_at' => now(),
    ]);

    $component->call('$refresh')
        ->assertDontSeeHtml('wire:poll.2s')
        ->assertSee(__('View sheet music'));
});

it('polls a shared score while the render is still queued', function () {
    Storage::fake('private');

    $score = Score::factory()->linksOnly()->create(['user_id' => User::factory()->create()->id]);
    ScoreFile::factory()->create([
        'score_id' => $score->id,
        'render_status' => ScoreFileRenderStatus::Pending,
    ]);
    $loan = Loan::factory()->of($score)->create();

    get(route('score.loan', ['token' => $loan->token]))
        ->assertOk()
        ->assertSeeHtml('wire:poll.2s')
        ->assertSee(__('Rendering the sheet music — this page updates on its own when it is ready.'))
        ->assertDontSeeHtml('data-modal="score-file-pages-share-');
});

it('offers the rendered pages behind a modal button on a shared score', function () {
    Storage::fake('private');

    $score = Score::factory()->linksOnly()->create(['user_id' => User::factory()->create()->id]);
    $scoreFile = ScoreFile::factory()->ready(2)->create(['score_id' => $score->id]);
    $loan = Loan::factory()->of($score)->create();

    $response = get(route('score.loan', ['token' => $loan->token]))->assertOk();

    $pageUrls = array_map(
        fn (int $page): string => route('loan.score.file.page', [
            'token' => $loan->token,
            'score' => $score,
            'scoreFile' => $scoreFile,
            'page' => $page,
        ]),
        [1, 2],
    );

    $response->assertSee(__('View :name', ['name' => $scoreFile->original_name]))
        ->assertSeeHtml('data-modal="score-file-pages-share-'.$scoreFile->id.'"')
        ->assertSee(Js::from($pageUrls)->toHtml(), false)
        ->assertDontSeeHtml('<img src="'.$pageUrls[0].'"');
});

it('serves a per-file thumbnail to the owner only', function () {
    Storage::fake('private');

    $owner = User::factory()->create();
    $score = Score::factory()->linksOnly()->create(['user_id' => $owner->id]);
    $scoreFile = ScoreFile::factory()->pdf('A4')->ready(1)->create(['score_id' => $score->id]);

    $thumbnail = makePng(800, 380);
    app(ScoreFileStorage::class)->put($scoreFile->thumbPath(), $thumbnail);

    $url = route('scores.file.thumbnail', ['score' => $score, 'scoreFile' => $scoreFile]);

    actingAs($owner);
    expect(get($url)->assertOk()->getContent())->toBe($thumbnail);

    actingAs(User::factory()->create());
    get($url)->assertForbidden();
});

it('keeps every uploaded file and lists them all in the editor', function () {
    Storage::fake('private');
    fakeRenderer();

    $owner = User::factory()->create();
    $score = Score::factory()->linksOnly()->create(['user_id' => $owner->id]);
    actingAs($owner);

    $source = ScoreFile::factory()->ready(2)->create([
        'score_id' => $score->id,
        'original_name' => 'veni.mscz',
    ]);

    Livewire::test(ScoreEditor::class, ['score' => $score])
        ->set('fileLabel', 'A4')
        ->set('fileRights', ScoreFileRights::PublicDomain->value)
        ->set('pendingFile', UploadedFile::fake()->createWithContent('veni-a4.pdf', '%PDF-1.7 fake'))
        ->call('addFile')
        ->assertHasNoErrors()
        ->assertSet('pendingFile', null)
        ->assertSet('fileLabel', '')
        ->assertSee('veni.mscz')
        ->assertSee('A4');

    $files = $score->fresh()->orderedFiles();

    expect($files)->toHaveCount(2)
        ->and($files[0]->id)->toBe($source->id)
        ->and($files[1]->original_name)->toBe('veni-a4.pdf')
        ->and($files[1]->label)->toBe('A4')
        ->and($files[1]->rights)->toBe(ScoreFileRights::PublicDomain);
});

it('lists uploaded files as cards rather than table rows', function () {
    Storage::fake('private');

    $owner = User::factory()->create();
    $score = Score::factory()->linksOnly()->create(['user_id' => $owner->id]);
    actingAs($owner);

    ScoreFile::factory()->ready(3)->create([
        'score_id' => $score->id,
        'original_name' => 'veni.mscz',
        'rights' => ScoreFileRights::PublicDomain,
    ]);

    $html = Livewire::test(ScoreEditor::class, ['score' => $score])
        ->assertSee('veni.mscz')
        ->assertSee(ScoreFileRights::PublicDomain->label())
        ->assertSee(trans_choice(':count page|:count pages', 3, ['count' => 3]))
        ->html();

    expect($html)->not->toContain('data-flux-table');
});

it('lists links as cards rather than plain rows', function () {
    $owner = User::factory()->create();
    $score = Score::factory()->linksOnly()->create(['user_id' => $owner->id]);
    ScoreUrl::create([
        'score_id' => $score->id,
        'url' => 'https://example.com/kotta.pdf',
        'label' => MusicUrlLabel::SheetMusic->value,
        'comment' => 'A négyszólamú változat',
    ]);

    actingAs($owner);

    Livewire::test(ScoreEditor::class, ['score' => $score])
        ->assertSee('https://example.com/kotta.pdf')
        ->assertSee('A négyszólamú változat')
        ->assertSee(MusicUrlLabel::SheetMusic->label())
        ->assertSee(__('Open link'));
});

it('renames a file and changes its rights from the edit dialog', function () {
    Storage::fake('private');

    $owner = User::factory()->create();
    $score = Score::factory()->linksOnly()->create(['user_id' => $owner->id]);
    $scoreFile = ScoreFile::factory()->ready(1)->create(['score_id' => $score->id]);

    actingAs($owner);

    Livewire::test(ScoreEditor::class, ['score' => $score])
        ->call('editFile', $scoreFile->id)
        ->assertSet('editingFileId', $scoreFile->id)
        ->assertSet('editingRights', $scoreFile->rights->value)
        ->set('editingLabel', ' A5 booklet ')
        ->set('editingRights', ScoreFileRights::LicensedCopy->value)
        ->call('updateFile')
        ->assertHasNoErrors()
        ->assertDispatched('score-file-saved')
        ->assertSet('editingFileId', null);

    $scoreFile->refresh();

    expect($scoreFile->label)->toBe('A5 booklet')
        ->and($scoreFile->rights)->toBe(ScoreFileRights::LicensedCopy)
        ->and($scoreFile->render_status)->toBe(ScoreFileRenderStatus::Ready);
});

it('re-uploads over a file as a new row, dropping the old one and its render', function () {
    Storage::fake('private');
    fakeRenderer(pageCount: 1);

    $owner = User::factory()->create();
    $score = Score::factory()->linksOnly()->create(['user_id' => $owner->id]);
    $scoreFile = ScoreFile::factory()->ready(4)->create([
        'score_id' => $score->id,
        'original_name' => 'veni.mscz',
        'label' => 'A4',
    ]);

    $storage = app(ScoreFileStorage::class);
    $storage->put($scoreFile->path, 'the old source');
    $storage->put($scoreFile->pagePath(4), makePng(20, 20));

    $oldPagePath = $scoreFile->pagePath(4);

    actingAs($owner);

    $replacement = makeMscz('Veni Creator Spiritus');

    Livewire::test(ScoreEditor::class, ['score' => $score])
        ->call('editFile', $scoreFile->id)
        ->set('replacementFile', UploadedFile::fake()->createWithContent('veni-v2.mscz', $replacement))
        ->call('updateFile')
        ->assertHasNoErrors();

    // The row is not mutated: a published version may point at it, so the bytes
    // behind an approved snapshot are never swapped underneath it.
    $current = $score->fresh()->orderedFiles();

    expect($current)->toHaveCount(1)
        ->and($current->first()->id)->not->toBe($scoreFile->id)
        ->and($current->first()->original_name)->toBe('veni-v2.mscz')
        ->and($current->first()->label)->toBe('A4')
        ->and($current->first()->checksum)->toBe(hash('sha256', $replacement))
        ->and($storage->get($current->first()->path))->toBe($replacement)
        ->and($current->first()->page_count)->toBe(1)
        // Nothing referred to the old row, so it went entirely.
        ->and(ScoreFile::query()->whereKey($scoreFile->id)->exists())->toBeFalse()
        ->and(Storage::disk('private')->exists($oldPagePath))->toBeFalse();
});

it('keeps the bytes a published version was approved with when the file is replaced', function () {
    Storage::fake('private');
    fakeRenderer(pageCount: 1);

    $owner = User::factory()->create();
    $score = Score::factory()->linksOnly()->create(['user_id' => $owner->id]);
    $scoreFile = ScoreFile::factory()->ready(1)->create([
        'score_id' => $score->id,
        'original_name' => 'veni.mscz',
        'is_published' => true,
    ]);

    app(ScoreFileStorage::class)->put($scoreFile->path, 'the approved source');

    $version = app(\App\Services\ScoreVersionService::class)->snapshot($score->fresh());

    expect($version->files->pluck('id')->all())->toBe([$scoreFile->id]);

    actingAs($owner);

    Livewire::test(ScoreEditor::class, ['score' => $score])
        ->call('editFile', $scoreFile->id)
        ->set('replacementFile', UploadedFile::fake()->createWithContent('veni-v2.mscz', makeMscz('Veni')))
        ->call('updateFile')
        ->assertHasNoErrors();

    $scoreFile->refresh();

    expect($scoreFile->isSuperseded())->toBeTrue()
        ->and($scoreFile->superseded_by_id)->not->toBeNull()
        ->and(app(ScoreFileStorage::class)->get($scoreFile->path))->toBe('the approved source')
        // and it is out of the score's own listing
        ->and($score->fresh()->orderedFiles()->pluck('id')->all())->toBe([$scoreFile->superseded_by_id]);
});

it('deletes one file and leaves the others alone', function () {
    Storage::fake('private');

    $owner = User::factory()->create();
    $score = Score::factory()->linksOnly()->create(['user_id' => $owner->id]);
    $kept = ScoreFile::factory()->ready(1)->create(['score_id' => $score->id]);
    $dropped = ScoreFile::factory()->pdf('A5')->ready(1)->create(['score_id' => $score->id]);

    app(ScoreFileStorage::class)->put($dropped->path, '%PDF-1.7 fake');

    actingAs($owner);

    Livewire::test(ScoreEditor::class, ['score' => $score])
        ->call('deleteFile', $dropped->id)
        ->assertHasNoErrors();

    expect($score->fresh()->orderedFiles()->pluck('id')->all())->toBe([$kept->id])
        ->and(Storage::disk('private')->exists($dropped->directory()))->toBeFalse();
});

it('refuses to touch a file belonging to someone else\'s score', function () {
    Storage::fake('private');

    $owner = User::factory()->create();
    $score = Score::factory()->linksOnly()->create(['user_id' => $owner->id]);
    $foreign = ScoreFile::factory()->create();

    actingAs($owner);

    Livewire::test(ScoreEditor::class, ['score' => $score])
        ->call('deleteFile', $foreign->id)
        ->assertNotFound();

    expect(ScoreFile::query()->find($foreign->id))->not->toBeNull();
});

it('accepts a PDF upload and cuts it into pages without running MuseScore', function () {
    Storage::fake('private');

    $this->mock(MuseScoreRenderer::class)->shouldNotReceive('render');
    $this->mock(PdfPageRasterizer::class)
        ->shouldReceive('rasterize')
        ->andReturn(array_fill(0, 2, makePng(1240, 1754)))
        ->shouldReceive('rasterizePage')
        ->andReturn(makePng(1654, 2339));

    $scoreFile = ScoreFile::factory()->pdf()->create();
    app(ScoreFileStorage::class)->put($scoreFile->path, '%PDF-1.7 fake');

    (new RenderScoreFileJob($scoreFile))->handle(
        app(ScoreFileStorage::class),
        app(MuseScoreRenderer::class),
        app(PdfPageRasterizer::class),
        app(ScoreFileIncipitCropper::class),
    );

    $scoreFile->refresh();

    expect($scoreFile->render_status)->toBe(ScoreFileRenderStatus::Ready)
        ->and($scoreFile->page_count)->toBe(2)
        ->and($scoreFile->has_thumbnail)->toBeTrue()
        ->and(Storage::disk('private')->exists($scoreFile->pagePath(2)))->toBeTrue()
        // The upload is its own render, so it is not stored a second time.
        ->and(Storage::disk('private')->exists($scoreFile->renderPath()))->toBeFalse();
});

it('takes the incipit from the first file that has a thumbnail', function () {
    Storage::fake('private');

    $score = Score::factory()->linksOnly()->create(['user_id' => User::factory()->create()->id]);
    ScoreFile::factory()->failed()->create(['score_id' => $score->id]);
    $withThumbnail = ScoreFile::factory()->pdf('A4')->ready(1)->create(['score_id' => $score->id]);

    expect($score->fresh()->incipitFile()?->id)->toBe($withThumbnail->id);
});

it('offers every ready file on a shared score', function () {
    Storage::fake('private');

    $score = Score::factory()->linksOnly()->create(['user_id' => User::factory()->create()->id]);
    $source = ScoreFile::factory()->ready(1)->create([
        'score_id' => $score->id,
        'original_name' => 'veni.mscz',
    ]);
    $print = ScoreFile::factory()->pdf('A4')->ready(2)->create(['score_id' => $score->id]);
    $loan = Loan::factory()->of($score)->create(['allow_download' => true]);

    get(route('score.loan', ['token' => $loan->token]))
        ->assertOk()
        ->assertSee(__('View :name', ['name' => 'veni.mscz']))
        ->assertSee(__('View :name', ['name' => 'A4']))
        ->assertSeeHtml('data-modal="score-file-pages-share-'.$source->id.'"')
        ->assertSeeHtml('data-modal="score-file-pages-share-'.$print->id.'"')
        ->assertSee(__('Download :name', ['name' => 'A4']));
});

it('actually renders a .musicxml with MuseScore', function () {
    $binary = (string) config('services.musescore.bin', 'mscore-render');
    if (! shell_exec('command -v '.escapeshellarg($binary))) {
        $this->markTestSkipped('MuseScore is not installed in this environment.');
    }

    $musicxml = <<<'XML'
    <?xml version="1.0" encoding="UTF-8"?>
    <score-partwise version="3.1">
      <part-list><score-part id="P1"><part-name>Cantus</part-name></score-part></part-list>
      <part id="P1"><measure number="1">
        <attributes><divisions>1</divisions><key><fifths>0</fifths></key>
        <time><beats>4</beats><beat-type>4</beat-type></time>
        <clef><sign>G</sign><line>2</line></clef></attributes>
        <note><pitch><step>C</step><octave>4</octave></pitch><duration>4</duration><type>whole</type></note>
      </measure></part>
    </score-partwise>
    XML;

    $pdf = MuseScoreRenderer::fromConfig()->render($musicxml, 'musicxml');

    expect($pdf)->toStartWith('%PDF');
});

it('keeps the add-file form in a dialog and closes it once the file is stored', function () {
    Storage::fake('private');
    fakeRenderer();
    $score = Score::factory()->unattached()->create();

    actingAs($score->user);

    Livewire::test(ScoreEditor::class, ['score' => $score])
        ->assertSeeHtml('data-modal="score-file-add"')
        ->assertSeeHtml("fluxModal('score-file-add'")
        ->set('fileRights', ScoreFileRights::OwnWork->value)
        ->set('pendingFile', UploadedFile::fake()->createWithContent('veni.mscz', makeMscz()))
        ->call('addFile')
        ->assertHasNoErrors()
        ->assertDispatched('score-file-added');

    expect($score->files()->count())->toBe(1);
});

it('keeps the add-file dialog open when the upload is rejected', function () {
    Storage::fake('private');
    $score = Score::factory()->unattached()->create();

    actingAs($score->user);

    Livewire::test(ScoreEditor::class, ['score' => $score])
        ->set('pendingFile', UploadedFile::fake()->createWithContent('notes.txt', 'hello'))
        ->call('addFile')
        ->assertHasErrors('pendingFile')
        ->assertNotDispatched('score-file-added');
});
