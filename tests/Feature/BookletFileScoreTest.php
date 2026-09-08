<?php

use App\Jobs\RenderScoreFileJob;
use App\Livewire\Pages\BookletEditor;
use App\Models\Booklet;
use App\Models\Score;
use App\Models\ScoreFile;
use App\Models\ScorePublication;
use App\Models\User;
use App\Services\MuseScoreRenderer;
use App\Services\MusicPlanScoreListService;
use App\Services\PdfPageRasterizer;
use App\Services\PdfPageVectorizer;
use App\Services\ScoreFileIncipitCropper;
use App\Services\ScoreFileStorage;
use App\Services\ScoreImageCompressor;
use App\Services\ScorePageBander;
use App\Services\ScoreStripCutter;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/**
 * An uploaded score in a booklet.
 *
 * It is the one kind that cannot be re-engraved at the booklet's size, so it
 * enters as the systems the renderer cut out of its pages. These check the two
 * halves of that: that the strips are produced and indexed, and that the booklet
 * hands them to the browser under the same access rules as everything else.
 */

/**
 * A page with four staves on it, drawn rather than rendered.
 *
 * Everything is laid out in fractions of the page, so the same call at another
 * size produces a true scaling of it — which is what poppler hands back when it
 * rasterises one PDF at reading and at printing resolution. The beams are
 * anti-aliased and the rest is not, as a real engraving is: mostly paper, edged
 * in grey.
 */
function bandedPage(int $width = 1240, int $height = 1754): string
{
    $page = imagecreatetruecolor($width, $height);
    imagefill($page, 0, 0, imagecolorallocate($page, 255, 255, 255));
    $black = imagecolorallocate($page, 0, 0, 0);
    imageantialias($page, true);

    $scale = $height / 1754;
    $at = fn (float $value): int => (int) round($value * $scale);

    foreach ([260, 440, 675, 970] as $top) {
        for ($line = 0; $line < 5; $line++) {
            $y = $at($top + $line * 9.5);
            imagefilledrectangle($page, $at(105), $y, $at(1125), $y + ($scale >= 2 ? 1 : 0), $black);
        }
        imagefilledrectangle($page, $at(105), $at($top), $at(107), $at($top + 38), $black);

        // Inside the staff, so no band is created, merged or moved by them.
        for ($beam = 0; $beam < 12; $beam++) {
            $x = 200 + $beam * 70;
            imageline($page, $at($x), $at($top + 4), $at($x + 55), $at($top + 34), $black);
        }
    }

    ob_start();
    imagepng($page);
    $bytes = (string) ob_get_clean();
    imagedestroy($page);

    return $bytes;
}

/**
 * Render a file through the raster path.
 *
 * PdfPageVectorizer is left real and unmocked: with no poppler behind it, it
 * throws, and the job falls back to raster — which is what these older
 * assertions describe.
 */
function renderWithBanding(ScoreFile $scoreFile): void
{
    test()->mock(MuseScoreRenderer::class)
        ->shouldReceive('render')
        ->andReturn('%PDF-1.7 fake');

    test()->mock(PdfPageRasterizer::class)
        ->shouldReceive('rasterize')
        ->andReturn([bandedPage()])
        ->shouldReceive('rasterizePage')
        ->andReturn(bandedPage(2480, 3508));

    (new RenderScoreFileJob($scoreFile))->handle(
        app(ScoreFileStorage::class),
        app(MuseScoreRenderer::class),
        app(PdfPageRasterizer::class),
        app(ScoreFileIncipitCropper::class),
        app(ScorePageBander::class),
        app(ScoreStripCutter::class),
        app(ScoreImageCompressor::class),
        app(PdfPageVectorizer::class),
    );
}

/**
 * Render a file through the vector path.
 *
 * PdfPageVectorizer is mocked exactly as PdfPageRasterizer is — a stand-in SVG
 * per page, sized so the whole file comes out smaller than its page PNGs — so
 * the suite needs neither poppler nor MuseScore.
 */
function renderVector(ScoreFile $scoreFile, int $pages = 1): void
{
    test()->mock(MuseScoreRenderer::class)
        ->shouldReceive('render')
        ->andReturn('%PDF-1.7 fake');

    test()->mock(PdfPageRasterizer::class)
        ->shouldReceive('rasterize')
        ->andReturn(array_fill(0, $pages, bandedPage()))
        ->shouldReceive('rasterizePage')
        ->andReturn(bandedPage(2480, 3508));

    test()->mock(PdfPageVectorizer::class)
        ->shouldReceive('vectorizePage')
        ->andReturn('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 595 842"><rect width="10" height="10"/></svg>');

    (new RenderScoreFileJob($scoreFile))->handle(
        app(ScoreFileStorage::class),
        app(MuseScoreRenderer::class),
        app(PdfPageRasterizer::class),
        app(ScoreFileIncipitCropper::class),
        app(ScorePageBander::class),
        app(ScoreStripCutter::class),
        app(ScoreImageCompressor::class),
        app(PdfPageVectorizer::class),
    );
}

it('cuts a rendered file into systems and stores them', function () {
    Storage::fake('private');

    $scoreFile = ScoreFile::factory()->create();
    app(ScoreFileStorage::class)->put($scoreFile->path, 'source bytes');

    renderWithBanding($scoreFile);

    $scoreFile->refresh();
    $strips = $scoreFile->stripList();

    expect($strips)->toHaveCount(4);

    foreach ($strips as $strip) {
        expect($strip['page'])->toBe(1)
            ->and($strip['width'])->toBeGreaterThan(0)
            ->and($strip['height'])->toBeGreaterThan(0)
            ->and(Storage::disk('private')->exists(
                $scoreFile->stripPath($strip['page'], $strip['index'])
            ))->toBeTrue();
    }
});

// Strips are the densest thing stored per page and are written whether or not
// anyone puts the score in a booklet, so they are stored a byte a pixel rather
// than three. The page images and the incipit go the same way.
it('stores every rendered image as a palette, not as 24-bit colour', function () {
    Storage::fake('private');

    $scoreFile = ScoreFile::factory()->create();
    app(ScoreFileStorage::class)->put($scoreFile->path, 'source bytes');

    renderWithBanding($scoreFile);

    $scoreFile->refresh();
    $storage = app(ScoreFileStorage::class);

    $paths = [$scoreFile->pagePath(1), $scoreFile->thumbPath()];
    foreach ($scoreFile->stripList() as $strip) {
        $paths[] = $scoreFile->stripPath($strip['page'], $strip['index']);
    }

    foreach ($paths as $path) {
        $image = imagecreatefromstring($storage->get($path));

        expect(imageistruecolor($image))->toBeFalse("{$path} is still truecolor");
    }
});

// Every strip of one file shares a horizontal window, which is what keeps the
// systems lined up once the booklet has scaled them to its page.
it('cuts every system to the same width', function () {
    Storage::fake('private');

    $scoreFile = ScoreFile::factory()->create();
    app(ScoreFileStorage::class)->put($scoreFile->path, 'source bytes');

    renderWithBanding($scoreFile);

    expect(array_unique(array_column($scoreFile->fresh()->stripList(), 'width')))->toHaveCount(1);
});

// A scan too skewed to read, or a page of prose: the reading view needs only
// the pages, and a file that cannot be cut up must still arrive with them.
it('still renders a file it cannot cut up', function () {
    Storage::fake('private');

    $blank = imagecreatetruecolor(1240, 1754);
    imagefill($blank, 0, 0, imagecolorallocate($blank, 255, 255, 255));
    ob_start();
    imagepng($blank);
    $blankPage = (string) ob_get_clean();
    imagedestroy($blank);

    test()->mock(MuseScoreRenderer::class)
        ->shouldReceive('render')
        ->andReturn('%PDF-1.7 fake');

    test()->mock(PdfPageRasterizer::class)
        ->shouldReceive('rasterize')
        ->andReturn([$blankPage, $blankPage])
        ->shouldReceive('rasterizePage')
        ->andReturn($blankPage);

    $scoreFile = ScoreFile::factory()->create();
    app(ScoreFileStorage::class)->put($scoreFile->path, 'source bytes');

    (new RenderScoreFileJob($scoreFile))->handle(
        app(ScoreFileStorage::class),
        app(MuseScoreRenderer::class),
        app(PdfPageRasterizer::class),
        app(ScoreFileIncipitCropper::class),
        app(ScorePageBander::class),
        app(ScoreStripCutter::class),
        app(ScoreImageCompressor::class),
        app(PdfPageVectorizer::class),
    );

    $scoreFile->refresh();

    expect($scoreFile->page_count)->toBe(2)
        ->and($scoreFile->stripList())->toBe([])
        ->and($scoreFile->isVectorRendered())->toBeFalse()
        ->and(Storage::disk('private')->exists($scoreFile->pagePath(1)))->toBeTrue();
});

// An engraving whose vector form is smaller: the page is kept as one gzipped
// SVG, every system becomes a rectangle onto it, and no PNGs are stored.
it('keeps an engraved file as vector pages', function () {
    Storage::fake('private');

    $scoreFile = ScoreFile::factory()->create();
    app(ScoreFileStorage::class)->put($scoreFile->path, 'source bytes');

    renderVector($scoreFile);

    $scoreFile->refresh();
    $strips = $scoreFile->stripList();

    expect($scoreFile->isVectorRendered())->toBeTrue()
        ->and($strips)->toHaveCount(4)
        ->and(Storage::disk('private')->exists($scoreFile->pageVectorPath(1)))->toBeTrue()
        ->and(Storage::disk('private')->exists($scoreFile->pagePath(1)))->toBeFalse();

    foreach ($strips as $strip) {
        expect($strip)->toHaveKey('rect')
            ->and($strip['rect'])->toHaveCount(4)
            ->and($strip['rect'][2])->toBe($strip['width'])
            ->and($strip['rect'][3])->toBe($strip['height'])
            ->and(Storage::disk('private')->exists(
                $scoreFile->stripPath($strip['page'], $strip['index'])
            ))->toBeFalse();
    }
});

// The rectangle is the window unioned across the file: every system shares one
// left edge and width, straight from the bands the fake page was drawn with.
it('windows every vector system to the same horizontal slice', function () {
    Storage::fake('private');

    $scoreFile = ScoreFile::factory()->create();
    app(ScoreFileStorage::class)->put($scoreFile->path, 'source bytes');

    renderVector($scoreFile);

    $strips = $scoreFile->fresh()->stripList();
    $lefts = array_map(fn (array $strip): float => $strip['rect'][0], $strips);
    $widths = array_map(fn (array $strip): float => $strip['rect'][2], $strips);
    $tops = array_map(fn (array $strip): float => $strip['rect'][1], $strips);

    expect(array_unique($lefts))->toHaveCount(1)
        ->and(array_unique($widths))->toHaveCount(1)
        // The four staves sit down the page, so the tops climb.
        ->and($tops)->toBe(array_values(collect($tops)->sort()->values()->all()));
});

// A page whose vector form is bigger than its PNG — a scan — stays on the
// raster path and keeps its strip images.
it('falls back to raster when the vector form is larger', function () {
    Storage::fake('private');

    test()->mock(MuseScoreRenderer::class)
        ->shouldReceive('render')->andReturn('%PDF-1.7 fake');
    test()->mock(PdfPageRasterizer::class)
        ->shouldReceive('rasterize')->andReturn([bandedPage()])
        ->shouldReceive('rasterizePage')->andReturn(bandedPage(2480, 3508));
    test()->mock(PdfPageVectorizer::class)
        ->shouldReceive('vectorizePage')
        ->andReturn('<svg xmlns="http://www.w3.org/2000/svg"><!-- '
            .base64_encode(random_bytes(400000)).' --></svg>');

    $scoreFile = ScoreFile::factory()->create();
    app(ScoreFileStorage::class)->put($scoreFile->path, 'source bytes');

    (new RenderScoreFileJob($scoreFile))->handle(
        app(ScoreFileStorage::class),
        app(MuseScoreRenderer::class),
        app(PdfPageRasterizer::class),
        app(ScoreFileIncipitCropper::class),
        app(ScorePageBander::class),
        app(ScoreStripCutter::class),
        app(ScoreImageCompressor::class),
        app(PdfPageVectorizer::class),
    );

    $scoreFile->refresh();

    expect($scoreFile->isVectorRendered())->toBeFalse()
        ->and($scoreFile->stripList())->toHaveCount(4)
        ->and(Storage::disk('private')->exists($scoreFile->pageVectorPath(1)))->toBeFalse()
        ->and(Storage::disk('private')->exists($scoreFile->pagePath(1)))->toBeTrue()
        ->and(Storage::disk('private')->exists($scoreFile->stripPath(1, 1)))->toBeTrue();
});

// A re-render that flips a file from raster to vector must not leave the strip
// PNGs behind, or the library conversion doubles storage instead of shrinking it.
it('drops the raster artifacts when a re-render goes vector', function () {
    Storage::fake('private');

    $scoreFile = ScoreFile::factory()->create();
    app(ScoreFileStorage::class)->put($scoreFile->path, 'source bytes');

    renderWithBanding($scoreFile);
    expect(Storage::disk('private')->exists($scoreFile->stripPath(1, 1)))->toBeTrue();

    renderVector($scoreFile->fresh());

    expect(Storage::disk('private')->exists($scoreFile->stripPath(1, 1)))->toBeFalse()
        ->and(Storage::disk('private')->exists($scoreFile->pagePath(1)))->toBeFalse()
        ->and(Storage::disk('private')->exists($scoreFile->pageVectorPath(1)))->toBeTrue();
});

it('serves a vector page to the booklet that owns it', function () {
    Storage::fake('private');

    $user = User::factory()->create();
    $score = Score::factory()->linksOnly()->create(['user_id' => $user->id]);
    $file = ScoreFile::factory()->vector()->create(['score_id' => $score->id]);
    $booklet = Booklet::factory()->create(['user_id' => $user->id]);

    app(ScoreFileStorage::class)->put($file->pageVectorPath(1), gzencode('<svg xmlns="http://www.w3.org/2000/svg"/>'));

    actingAs($user);

    get(route('booklets.score-page', [
        'booklet' => $booklet->id, 'scoreFile' => $file->id, 'page' => 1,
    ]))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/svg+xml')
        ->assertHeader('Content-Encoding', 'gzip')
        ->assertHeader('X-Content-Type-Options', 'nosniff');
});

it('refuses a vector page to someone who does not own the booklet', function () {
    Storage::fake('private');

    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $score = Score::factory()->linksOnly()->create(['user_id' => $owner->id]);
    $file = ScoreFile::factory()->vector()->create(['score_id' => $score->id]);
    $booklet = Booklet::factory()->create(['user_id' => $owner->id]);

    actingAs($stranger);

    get(route('booklets.score-page', [
        'booklet' => $booklet->id, 'scoreFile' => $file->id, 'page' => 1,
    ]))->assertForbidden();
});

it('refuses a vector page of a score the booklet owner may not read', function () {
    Storage::fake('private');

    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $score = Score::factory()->linksOnly()->create(['user_id' => $stranger->id]);
    $file = ScoreFile::factory()->vector()->create(['score_id' => $score->id]);
    $booklet = Booklet::factory()->create(['user_id' => $owner->id]);

    actingAs($owner);

    get(route('booklets.score-page', [
        'booklet' => $booklet->id, 'scoreFile' => $file->id, 'page' => 1,
    ]))->assertNotFound();
});

it('refuses a vector page from a raster file', function () {
    $user = User::factory()->create();
    $score = Score::factory()->linksOnly()->create(['user_id' => $user->id]);
    $file = ScoreFile::factory()->banded()->create(['score_id' => $score->id]);
    $booklet = Booklet::factory()->create(['user_id' => $user->id]);

    actingAs($user);

    get(route('booklets.score-page', [
        'booklet' => $booklet->id, 'scoreFile' => $file->id, 'page' => 1,
    ]))->assertNotFound();
});

it('sends a vector file to the browser as a page url and a rectangle', function () {
    $user = User::factory()->create();
    $score = Score::factory()->linksOnly()->create(['user_id' => $user->id]);
    $file = ScoreFile::factory()->vector()->create(['score_id' => $score->id]);
    $booklet = Booklet::factory()->create(['user_id' => $user->id]);
    $booklet->entries()->create(['score_id' => $score->id, 'sequence' => 1]);

    actingAs($user);

    $payload = Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->instance()
        ->renderPayload();

    expect($payload)->toHaveCount(1)
        ->and($payload[0]['kind'])->toBe('file')
        ->and($payload[0]['fileId'])->toBe($file->id)
        ->and($payload[0]['strips'][0])->not->toHaveKey('url')
        ->and($payload[0]['strips'][0]['pageUrl'])->toContain("/booklets/{$booklet->id}/score-page/{$file->id}/1")
        ->and($payload[0]['strips'][0]['rect'])->toBeString();
});

// Cairo names its glyph symbols per document, so every page of a file defines
// `glyph-0-1`. The browser scopes them by the page number the payload carries;
// without it every page shares one scope and page 2 onwards draws page 1's
// glyphs, and the export placeholder names no page the server can inline.
it('names the page each vector system stands on', function () {
    $user = User::factory()->create();
    $score = Score::factory()->linksOnly()->create(['user_id' => $user->id]);
    $file = ScoreFile::factory()->vector()->create([
        'score_id' => $score->id,
        'page_count' => 2,
        'strips' => [
            ['page' => 1, 'index' => 1, 'width' => 452.3, 'height' => 61.1, 'rect' => [40.0, 55.0, 452.3, 61.1]],
            ['page' => 2, 'index' => 1, 'width' => 452.3, 'height' => 61.1, 'rect' => [40.0, 90.0, 452.3, 61.1]],
        ],
    ]);
    $booklet = Booklet::factory()->create(['user_id' => $user->id]);
    $booklet->entries()->create(['score_id' => $score->id, 'sequence' => 1]);

    actingAs($user);

    $payload = Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->instance()
        ->renderPayload();

    expect(array_column($payload[0]['strips'], 'page'))->toBe([1, 2])
        ->and($payload[0]['strips'][1]['pageUrl'])->toContain("/booklets/{$booklet->id}/score-page/{$file->id}/2");
});

it('serves a vector page in the reading view', function () {
    Storage::fake('private');

    $user = User::factory()->create();
    $score = Score::factory()->linksOnly()->create(['user_id' => $user->id]);
    $file = ScoreFile::factory()->vector()->create(['score_id' => $score->id]);

    app(ScoreFileStorage::class)->put($file->pageVectorPath(1), gzencode('<svg xmlns="http://www.w3.org/2000/svg"/>'));

    actingAs($user);

    get(route('scores.file.page', ['score' => $score->id, 'scoreFile' => $file->id, 'page' => 1]))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/svg+xml')
        ->assertHeader('Content-Encoding', 'gzip');
});

it('offers an uploaded score to a booklet once it has been cut up', function () {
    $user = User::factory()->create();
    $score = Score::factory()->linksOnly()->create(['user_id' => $user->id]);
    ScoreFile::factory()->banded()->create(['score_id' => $score->id]);

    $source = app(MusicPlanScoreListService::class)->sourcesFor([$score->id], $user)->get($score->id);

    expect($source)->not->toBeNull()
        ->and($source['format'])->toBeNull()
        ->and($source['strips'])->toHaveCount(3);
});

it('withholds an uploaded score that has not been cut up', function () {
    $user = User::factory()->create();
    $score = Score::factory()->linksOnly()->create(['user_id' => $user->id]);
    ScoreFile::factory()->ready()->create(['score_id' => $score->id]);

    expect(app(MusicPlanScoreListService::class)->sourcesFor([$score->id], $user)->has($score->id))
        ->toBeFalse();
});

it('withholds a score that is nothing but links', function () {
    $user = User::factory()->create();
    $score = Score::factory()->linksOnly()->create(['user_id' => $user->id]);

    expect(app(MusicPlanScoreListService::class)->sourcesFor([$score->id], $user)->has($score->id))
        ->toBeFalse();
});

it('sends the systems to the browser as urls rather than as bytes', function () {
    $user = User::factory()->create();
    $score = Score::factory()->linksOnly()->create(['user_id' => $user->id]);
    $file = ScoreFile::factory()->banded()->create(['score_id' => $score->id]);
    $booklet = Booklet::factory()->create(['user_id' => $user->id]);
    $booklet->entries()->create(['score_id' => $score->id, 'sequence' => 1]);

    actingAs($user);

    $payload = Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->instance()
        ->renderPayload();

    expect($payload)->toHaveCount(1)
        ->and($payload[0]['kind'])->toBe('file')
        ->and($payload[0]['strips'])->toHaveCount(3)
        ->and($payload[0]['strips'][0]['url'])->toContain("/booklets/{$booklet->id}/strip/{$file->id}/1/1")
        ->and($payload[0]['strips'][0]['width'])->toBe(2032)
        ->and($payload[0])->not->toHaveKey('content');
});

it('serves a system to the booklet that owns it', function () {
    Storage::fake('private');

    $user = User::factory()->create();
    $score = Score::factory()->linksOnly()->create(['user_id' => $user->id]);
    $file = ScoreFile::factory()->banded()->create(['score_id' => $score->id]);
    $booklet = Booklet::factory()->create(['user_id' => $user->id]);

    app(ScoreFileStorage::class)->put($file->stripPath(1, 2), 'strip bytes');

    actingAs($user);

    get(route('booklets.strip', [
        'booklet' => $booklet->id, 'scoreFile' => $file->id, 'page' => 1, 'index' => 2,
    ]))->assertOk()->assertHeader('Content-Type', 'image/png');
});

it('refuses a system to someone who does not own the booklet', function () {
    Storage::fake('private');

    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $score = Score::factory()->linksOnly()->create(['user_id' => $owner->id]);
    $file = ScoreFile::factory()->banded()->create(['score_id' => $score->id]);
    $booklet = Booklet::factory()->create(['user_id' => $owner->id]);

    app(ScoreFileStorage::class)->put($file->stripPath(1, 1), 'strip bytes');

    actingAs($stranger);

    get(route('booklets.strip', [
        'booklet' => $booklet->id, 'scoreFile' => $file->id, 'page' => 1, 'index' => 1,
    ]))->assertForbidden();
});

// The booklet's own gate is not enough: its owner must also still hold the
// score, which is a question only the score list can answer.
it('refuses a system of a score the booklet owner may not read', function () {
    Storage::fake('private');

    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $score = Score::factory()->linksOnly()->create(['user_id' => $stranger->id]);
    $file = ScoreFile::factory()->banded()->create(['score_id' => $score->id]);
    $booklet = Booklet::factory()->create(['user_id' => $owner->id]);

    app(ScoreFileStorage::class)->put($file->stripPath(1, 1), 'strip bytes');

    actingAs($owner);

    get(route('booklets.strip', [
        'booklet' => $booklet->id, 'scoreFile' => $file->id, 'page' => 1, 'index' => 1,
    ]))->assertNotFound();
});

it('serves a system of a published score to anyone building a booklet', function () {
    Storage::fake('private');

    $owner = User::factory()->create();
    $reader = User::factory()->create();
    $score = Score::factory()->linksOnly()->create(['user_id' => $owner->id]);
    ScorePublication::factory()->approved()->create(['score_id' => $score->id]);
    $file = ScoreFile::factory()->banded()->create(['score_id' => $score->id]);
    $booklet = Booklet::factory()->create(['user_id' => $reader->id]);

    app(ScoreFileStorage::class)->put($file->stripPath(1, 1), 'strip bytes');

    actingAs($reader);

    get(route('booklets.strip', [
        'booklet' => $booklet->id, 'scoreFile' => $file->id, 'page' => 1, 'index' => 1,
    ]))->assertOk();
});

it('refuses a system that was never cut', function () {
    Storage::fake('private');

    $user = User::factory()->create();
    $score = Score::factory()->linksOnly()->create(['user_id' => $user->id]);
    $file = ScoreFile::factory()->banded(2)->create(['score_id' => $score->id]);
    $booklet = Booklet::factory()->create(['user_id' => $user->id]);

    actingAs($user);

    get(route('booklets.strip', [
        'booklet' => $booklet->id, 'scoreFile' => $file->id, 'page' => 1, 'index' => 9,
    ]))->assertNotFound();
});

it('lets an uploaded score be added to a booklet', function () {
    $user = User::factory()->create();
    $score = Score::factory()->linksOnly()->create(['user_id' => $user->id]);
    ScoreFile::factory()->banded()->create(['score_id' => $score->id]);
    $booklet = Booklet::factory()->create(['user_id' => $user->id]);

    actingAs($user);

    Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->call('toggleScore', $score->id);

    expect($booklet->entries()->where('score_id', $score->id)->exists())->toBeTrue();
});

it('keeps a links-only score out of a booklet', function () {
    $user = User::factory()->create();
    $score = Score::factory()->linksOnly()->create(['user_id' => $user->id]);
    $booklet = Booklet::factory()->create(['user_id' => $user->id]);

    actingAs($user);

    Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->call('toggleScore', $score->id);

    expect($booklet->entries()->count())->toBe(0);
});

/**
 * An uploaded score is a picture by the time it reaches a booklet, so the panel
 * it gets holds the one thing that can be said about a picture: make it smaller.
 * The page already prints it at full width, which is why there is no way up.
 */
it('lets an uploaded score be taken down from the width of the page', function () {
    $user = User::factory()->create();
    $score = Score::factory()->linksOnly()->create(['user_id' => $user->id]);
    ScoreFile::factory()->banded()->create(['score_id' => $score->id]);
    $booklet = Booklet::factory()->create(['user_id' => $user->id]);
    $entry = $booklet->entries()->create(['score_id' => $score->id, 'sequence' => 1]);

    actingAs($user);

    $component = Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->call('saveOverride', $entry->id, ['fileZoom' => 0.6]);

    expect($entry->fresh()->settings_override)->toEqual(['fileZoom' => 0.6]);

    // And the browser is told, so the systems are drawn at what was asked for.
    expect($component->instance()->renderPayload()[0]['override'])->toEqual(['fileZoom' => 0.6]);
});

it('holds an uploaded score to the one knob a picture has', function () {
    $user = User::factory()->create();
    $score = Score::factory()->linksOnly()->create(['user_id' => $user->id]);
    ScoreFile::factory()->banded()->create(['score_id' => $score->id]);
    $booklet = Booklet::factory()->create(['user_id' => $user->id]);
    $entry = $booklet->entries()->create(['score_id' => $score->id, 'sequence' => 1]);

    actingAs($user);

    Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->call('saveOverride', $entry->id, ['fileZoom' => 4, 'abcPageWidth' => 700]);

    expect($entry->fresh()->settings_override)->toEqual(['fileZoom' => 1]);
});

it('opens the size panel on an uploaded score', function () {
    $user = User::factory()->create();
    $score = Score::factory()->linksOnly()->create(['user_id' => $user->id]);
    ScoreFile::factory()->banded()->create(['score_id' => $score->id]);
    $booklet = Booklet::factory()->create(['user_id' => $user->id]);
    $entry = $booklet->entries()->create(['score_id' => $score->id, 'sequence' => 1]);

    actingAs($user);

    $html = Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->call('editSettings', $entry->id)
        ->html();

    expect($html)->toContain('data-booklet-panel="'.$entry->id.'"')
        ->and($html)->toContain("settingsOf({$entry->id})['fileZoom']")
        ->and($html)->toContain("setOverride({$entry->id}, 'fileZoom'")
        ->and($html)->not->toContain('abcPageWidth');
});

/**
 * A score holding several uploaded files.
 *
 * They are not versions of one another — the projection slide is not the
 * accompaniment — so the booklet chooses between them one by one, rather than
 * taking the oldest and calling it the score.
 */

/**
 * @return array{0: \App\Models\Score, 1: ScoreFile, 2: ScoreFile}
 */
function scoreWithTwoFiles(User $user): array
{
    $score = Score::factory()->linksOnly()->create(['user_id' => $user->id]);
    $slide = ScoreFile::factory()->banded(1)->create(['score_id' => $score->id, 'label' => 'Vetítés']);
    $parts = ScoreFile::factory()->banded(4)->create(['score_id' => $score->id, 'label' => 'SA kísérettel']);

    return [$score, $slide, $parts];
}

it('offers every cut-up file of a score, oldest first', function () {
    $user = User::factory()->create();
    [$score, $slide, $parts] = scoreWithTwoFiles($user);

    $source = app(MusicPlanScoreListService::class)->sourcesFor([$score->id], $user)->get($score->id);

    expect(array_keys($source['files']))->toBe([$slide->id, $parts->id])
        ->and($source['files'][$parts->id]['name'])->toBe('SA kísérettel')
        ->and($source['files'][$parts->id]['strips'])->toHaveCount(4)
        ->and($source['file_id'])->toBe($slide->id);
});

it('leaves out a file that has not been cut up, and keeps the score for the one that has', function () {
    $user = User::factory()->create();
    $score = Score::factory()->linksOnly()->create(['user_id' => $user->id]);
    ScoreFile::factory()->ready()->create(['score_id' => $score->id]);
    $cut = ScoreFile::factory()->banded(2)->create(['score_id' => $score->id]);

    $source = app(MusicPlanScoreListService::class)->sourcesFor([$score->id], $user)->get($score->id);

    expect(array_keys($source['files']))->toBe([$cut->id])
        ->and($source['file_id'])->toBe($cut->id);
});

it('prints the file that was chosen rather than the score default', function () {
    $user = User::factory()->create();
    [$score, $slide, $parts] = scoreWithTwoFiles($user);
    $booklet = Booklet::factory()->create(['user_id' => $user->id]);

    actingAs($user);

    Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->call('toggleScore', $score->id, null, $parts->id);

    $entry = $booklet->entries()->firstOrFail();

    expect($entry->score_file_id)->toBe($parts->id);

    $payload = Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->instance()
        ->renderPayload();

    expect($payload[0]['fileId'])->toBe($parts->id)
        ->and($payload[0]['strips'])->toHaveCount(4)
        ->and($payload[0]['strips'][0]['url'])->toContain("/strip/{$parts->id}/")
        ->and($slide->id)->not->toBe($parts->id);
});

it('takes both files of one score when both are asked for', function () {
    $user = User::factory()->create();
    [$score, $slide, $parts] = scoreWithTwoFiles($user);
    $booklet = Booklet::factory()->create(['user_id' => $user->id]);

    actingAs($user);

    Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->call('toggleScore', $score->id, null, $slide->id)
        ->call('toggleScore', $score->id, null, $parts->id);

    expect($booklet->entries()->orderBy('sequence')->pluck('score_file_id')->all())
        ->toBe([$slide->id, $parts->id]);
});

it('takes out only the file that was toggled again', function () {
    $user = User::factory()->create();
    [$score, $slide, $parts] = scoreWithTwoFiles($user);
    $booklet = Booklet::factory()->create(['user_id' => $user->id]);

    actingAs($user);

    Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->call('toggleScore', $score->id, null, $slide->id)
        ->call('toggleScore', $score->id, null, $parts->id)
        ->call('toggleScore', $score->id, null, $slide->id);

    expect($booklet->entries()->pluck('score_file_id')->all())->toBe([$parts->id]);
});

it('ticks the default file for a row that names none', function () {
    $user = User::factory()->create();
    [$score, $slide] = scoreWithTwoFiles($user);
    $booklet = Booklet::factory()->create(['user_id' => $user->id]);
    $booklet->entries()->create(['score_id' => $score->id, 'sequence' => 1]);

    actingAs($user);

    $editor = Livewire::test(BookletEditor::class, ['booklet' => $booklet])->instance();

    expect($editor->chosenFileIds())->toBe([$slide->id]);

    // And toggling that same file takes the older row out rather than doubling it.
    Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->call('toggleScore', $score->id, null, $slide->id);

    expect($booklet->entries()->count())->toBe(0);
});

it('refuses a file that belongs to another score', function () {
    $user = User::factory()->create();
    [$score] = scoreWithTwoFiles($user);
    $other = Score::factory()->linksOnly()->create(['user_id' => $user->id]);
    $theirs = ScoreFile::factory()->banded()->create(['score_id' => $other->id]);
    $booklet = Booklet::factory()->create(['user_id' => $user->id]);

    actingAs($user);

    Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->call('toggleScore', $score->id, null, $theirs->id);

    expect($booklet->entries()->count())->toBe(0);
});

it('falls back to the score default when the chosen file stops being drawable', function () {
    $user = User::factory()->create();
    [$score, $slide, $parts] = scoreWithTwoFiles($user);
    $booklet = Booklet::factory()->create(['user_id' => $user->id]);
    $booklet->entries()->create(['score_id' => $score->id, 'score_file_id' => $parts->id, 'sequence' => 1]);

    $parts->update(['strips' => null]);

    actingAs($user);

    $payload = Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->instance()
        ->renderPayload();

    expect($payload)->toHaveCount(1)
        ->and($payload[0]['fileId'])->toBe($slide->id)
        ->and($payload[0]['strips'])->toHaveCount(1);
});

it('shows a score with one file as a single line, and one with two as a line each', function () {
    $user = User::factory()->create();
    $single = Score::factory()->linksOnly()->create(['user_id' => $user->id]);
    ScoreFile::factory()->banded()->create(['score_id' => $single->id]);
    [, , $parts] = scoreWithTwoFiles($user);

    $list = app(MusicPlanScoreListService::class);

    expect($list->sourcesFor([$single->id], $user)->get($single->id)['files'])->toHaveCount(1)
        ->and($list->sourcesFor([$parts->score_id], $user)->get($parts->score_id)['files'])->toHaveCount(2);
});
