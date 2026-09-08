<?php

use App\Models\Booklet;
use App\Models\Score;
use App\Models\ScoreFile;
use App\Models\ScorePublication;
use App\Models\User;
use App\Services\ScoreFileStorage;
use App\Services\SvgToPdfConverter;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;

// A5 exactly as the browser composes it: 148 x 210 mm at 96 dpi.
const A5_PAGE_SVG = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 559.3700787401574 793.7007874015748">'
    .'<rect width="559.3700787401574" height="793.7007874015748" fill="#fff"/></svg>';

function fakeConverter(?callable $onConvert = null): void
{
    $fake = new class($onConvert) extends SvgToPdfConverter
    {
        public function __construct(private $onConvert)
        {
            parent::__construct('rsvg-convert', 30);
        }

        public function convert(array $svgs, ?string $credit = null): string
        {
            if ($this->onConvert !== null) {
                ($this->onConvert)($svgs, $credit);
            }

            return '%PDF-1.4 fake';
        }
    };

    app()->instance(SvgToPdfConverter::class, $fake);
}

it('turns the pages the browser engraved into one pdf', function () {
    $user = User::factory()->create();
    $booklet = Booklet::factory()->create(['user_id' => $user->id, 'title' => 'Adventi füzet']);

    fakeConverter();
    actingAs($user);

    $response = postJson(route('booklets.export-pdf', ['booklet' => $booklet]), [
        'pages' => [A5_PAGE_SVG, A5_PAGE_SVG],
    ]);

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'attachment; filename="adventi-fuzet.cantores.hu.pdf"');

    expect($response->getContent())->toStartWith('%PDF');
});

// The converter stamps its credit line across every page, which is right for a
// single published score and wrong for a booklet of many: each score's credit is
// drawn into the flow beneath its own music instead.
it('does not stamp one credit line across the whole booklet', function () {
    $user = User::factory()->create();
    $booklet = Booklet::factory()->create(['user_id' => $user->id]);

    $seen = 'unset';
    fakeConverter(function (array $svgs, ?string $credit) use (&$seen): void {
        $seen = $credit;
    });

    actingAs($user);

    postJson(route('booklets.export-pdf', ['booklet' => $booklet]), ['pages' => [A5_PAGE_SVG]])
        ->assertOk();

    expect($seen)->toBeNull();
});

/** A booklet page carrying one vector-file system as the placeholder the browser sends. */
function pageWithPlaceholder(int $fileId, int $page = 1, string $rect = '40 55 452.3 61.1'): string
{
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 559 793">'
        ."<g transform=\"translate(20 20)\"><g data-score-page=\"{$fileId}\" data-page=\"{$page}\" data-rect=\"{$rect}\"></g></g>"
        .'</svg>';
}

it('puts the stored page back behind a placeholder the viewer may see', function () {
    Storage::fake('private');

    $user = User::factory()->create();
    $score = Score::factory()->linksOnly()->create(['user_id' => $user->id]);
    $file = ScoreFile::factory()->vector()->create(['score_id' => $score->id]);
    $booklet = Booklet::factory()->create(['user_id' => $user->id]);

    app(ScoreFileStorage::class)->put(
        $file->pageVectorPath(1),
        gzencode('<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 595 842"><circle id="ink" r="5"/></svg>'),
    );

    $seen = [];
    fakeConverter(function (array $svgs) use (&$seen): void {
        $seen = $svgs;
    });

    actingAs($user);

    postJson(route('booklets.export-pdf', ['booklet' => $booklet]), [
        'pages' => [pageWithPlaceholder($file->id)],
    ])->assertOk();

    expect($seen[0])->toContain('<circle')
        ->and($seen[0])->toContain('viewBox="40 55 452.3 61.1"')
        ->and($seen[0])->not->toContain('data-score-page');
});

it('substitutes a self-closed placeholder as the browser serializes it', function () {
    Storage::fake('private');

    $user = User::factory()->create();
    $score = Score::factory()->linksOnly()->create(['user_id' => $user->id]);
    $file = ScoreFile::factory()->vector()->create(['score_id' => $score->id]);
    $booklet = Booklet::factory()->create(['user_id' => $user->id]);

    app(ScoreFileStorage::class)->put(
        $file->pageVectorPath(1),
        gzencode('<svg xmlns="http://www.w3.org/2000/svg"><circle id="ink" r="5"/></svg>'),
    );

    $seen = [];
    fakeConverter(function (array $svgs) use (&$seen): void {
        $seen = $svgs;
    });

    actingAs($user);

    $page = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 559 793">'
        ."<g data-score-page=\"{$file->id}\" data-page=\"1\" data-rect=\"40 55 452.3 61.1\"/>"
        .'</svg>';

    postJson(route('booklets.export-pdf', ['booklet' => $booklet]), ['pages' => [$page]])
        ->assertOk();

    expect($seen[0])->toContain('<circle')
        ->and($seen[0])->not->toContain('data-score-page');
});

it('drops a placeholder for a file the viewer may not see', function () {
    Storage::fake('private');

    $owner = User::factory()->create();
    $reader = User::factory()->create();
    $score = Score::factory()->linksOnly()->create(['user_id' => $owner->id]);
    $file = ScoreFile::factory()->vector()->create(['score_id' => $score->id]);
    $booklet = Booklet::factory()->create(['user_id' => $reader->id]);

    app(ScoreFileStorage::class)->put(
        $file->pageVectorPath(1),
        gzencode('<svg xmlns="http://www.w3.org/2000/svg"><circle id="ink" r="5"/></svg>'),
    );

    $seen = [];
    fakeConverter(function (array $svgs) use (&$seen): void {
        $seen = $svgs;
    });

    actingAs($reader);

    postJson(route('booklets.export-pdf', ['booklet' => $booklet]), [
        'pages' => [pageWithPlaceholder($file->id)],
    ])->assertOk();

    expect($seen[0])->not->toContain('<circle')
        ->and($seen[0])->not->toContain('data-score-page');
});

it('inlines a placeholder for a published file any booklet may reach', function () {
    Storage::fake('private');

    $owner = User::factory()->create();
    $reader = User::factory()->create();
    $score = Score::factory()->linksOnly()->create(['user_id' => $owner->id]);
    ScorePublication::factory()->approved()->create(['score_id' => $score->id]);
    $file = ScoreFile::factory()->vector()->create(['score_id' => $score->id]);
    $booklet = Booklet::factory()->create(['user_id' => $reader->id]);

    app(ScoreFileStorage::class)->put(
        $file->pageVectorPath(1),
        gzencode('<svg xmlns="http://www.w3.org/2000/svg"><circle id="ink" r="5"/></svg>'),
    );

    $seen = [];
    fakeConverter(function (array $svgs) use (&$seen): void {
        $seen = $svgs;
    });

    actingAs($reader);

    postJson(route('booklets.export-pdf', ['booklet' => $booklet]), [
        'pages' => [pageWithPlaceholder($file->id)],
    ])->assertOk();

    expect($seen[0])->toContain('<circle');
});

it('refuses to export someone elses booklet', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $booklet = Booklet::factory()->create(['user_id' => $owner->id]);

    fakeConverter();
    actingAs($stranger);

    postJson(route('booklets.export-pdf', ['booklet' => $booklet]), ['pages' => [A5_PAGE_SVG]])
        ->assertForbidden();
});

it('refuses to export for a guest', function () {
    $booklet = Booklet::factory()->create();

    postJson(route('booklets.export-pdf', ['booklet' => $booklet]), ['pages' => [A5_PAGE_SVG]])
        ->assertUnauthorized();
});

it('rejects anything that is not an svg document', function () {
    $user = User::factory()->create();
    $booklet = Booklet::factory()->create(['user_id' => $user->id]);

    fakeConverter();
    actingAs($user);

    postJson(route('booklets.export-pdf', ['booklet' => $booklet]), [
        'pages' => ['<script>alert(1)</script>'],
    ])->assertJsonValidationErrors('pages.0');
});

it('rejects an empty booklet', function () {
    $user = User::factory()->create();
    $booklet = Booklet::factory()->create(['user_id' => $user->id]);

    fakeConverter();
    actingAs($user);

    postJson(route('booklets.export-pdf', ['booklet' => $booklet]), ['pages' => []])
        ->assertJsonValidationErrors('pages');
});

it('reports a converter failure as a bad gateway rather than a blank download', function () {
    $user = User::factory()->create();
    $booklet = Booklet::factory()->create(['user_id' => $user->id]);

    fakeConverter(function (): void {
        throw new RuntimeException('rsvg-convert is not installed');
    });

    actingAs($user);

    postJson(route('booklets.export-pdf', ['booklet' => $booklet]), ['pages' => [A5_PAGE_SVG]])
        ->assertStatus(502);
});

// The page's own viewBox is what makes it print at a real paper size, so the
// converter has to keep restating it in millimetres.
it('states an A5 page in millimetres for the printer', function () {
    $converter = new SvgToPdfConverter('rsvg-convert', 30);

    $normalized = $converter->normalizePhysicalSize(A5_PAGE_SVG);

    expect($normalized)->toContain('width="148')
        ->and($normalized)->toContain('height="210');
});

/**
 * A PDF page's declared size, in points. Cairo writes the page dictionary into a
 * compressed object stream, so it has to be inflated before /MediaBox is there
 * to read.
 *
 * @return array{0: float, 1: float}|null
 */
function pdfPageSize(string $pdf): ?array
{
    preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $pdf, $streams);

    foreach ($streams[1] as $stream) {
        $inflated = @gzuncompress($stream);

        if ($inflated !== false && preg_match('/MediaBox\s*\[\s*0\s+0\s+([\d.]+)\s+([\d.]+)\s*\]/', $inflated, $box)) {
            return [(float) $box[1], (float) $box[2]];
        }
    }

    return null;
}

// The claim the whole export rests on, checked against the real converter rather
// than a stand-in: a composed page really does come out as an A5 sheet, so what
// the editor promised in millimetres is what the printer is asked for.
it('really produces a 148 by 210 mm pdf page', function () {
    $binary = (string) config('services.rsvg.bin', 'rsvg-convert');

    if (exec('command -v '.escapeshellarg($binary)) === '') {
        $this->markTestSkipped('rsvg-convert is not installed here.');
    }

    $pdf = (new SvgToPdfConverter($binary, 30))->convert([A5_PAGE_SVG]);

    expect($pdf)->toStartWith('%PDF');

    // 1 mm = 72/25.4 pt, so A5 is 419.53 x 595.28 pt.
    [$width, $height] = pdfPageSize($pdf);

    expect($width / 72 * 25.4)->toBeGreaterThan(147.9)->toBeLessThan(148.1)
        ->and($height / 72 * 25.4)->toBeGreaterThan(209.9)->toBeLessThan(210.1);
});
