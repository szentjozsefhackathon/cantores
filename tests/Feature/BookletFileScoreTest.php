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
    );

    $scoreFile->refresh();

    expect($scoreFile->page_count)->toBe(2)
        ->and($scoreFile->stripList())->toBe([]);
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
