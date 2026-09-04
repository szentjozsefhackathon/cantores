<?php

use App\Services\SvgToPdfConverter;

use function Pest\Laravel\postJson;

const SAMPLE_SVG = '<svg xmlns="http://www.w3.org/2000/svg" width="100" height="40"><text x="5" y="20">Glória</text></svg>';

/**
 * Read the first page's MediaBox from a PDF and return its size in millimetres.
 *
 * Cairo writes the page dictionary into a compressed object stream, so every
 * stream is inflated before the box is looked up.
 *
 * @return array{0: float, 1: float}
 */
function pdfPageSizeInMm(string $pdf): array
{
    $haystack = $pdf;
    if (preg_match_all('/stream\r?\n/', $pdf, $matches, PREG_OFFSET_CAPTURE)) {
        foreach ($matches[0] as [$match, $offset]) {
            $start = $offset + strlen($match);
            $end = strpos($pdf, 'endstream', $start);
            $inflated = @gzuncompress(substr($pdf, $start, $end - $start));
            if ($inflated !== false) {
                $haystack .= $inflated;
            }
        }
    }

    expect($haystack)->toMatch('/\/MediaBox\s*\[\s*[\d.-]+\s+[\d.-]+\s+([\d.]+)\s+([\d.]+)/');
    preg_match('/\/MediaBox\s*\[\s*[\d.-]+\s+[\d.-]+\s+([\d.]+)\s+([\d.]+)/', $haystack, $box);

    return [(float) $box[1] / 72 * 25.4, (float) $box[2] / 72 * 25.4];
}

it('rejects a request with no pages', function () {
    postJson(route('score.export-pdf'), ['format' => 'abc'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('pages');
});

it('rejects an unsupported format', function () {
    postJson(route('score.export-pdf'), ['format' => 'chordpro', 'pages' => [SAMPLE_SVG]])
        ->assertStatus(422)
        ->assertJsonValidationErrors('format');
});

it('rejects a page that is not an svg document', function () {
    postJson(route('score.export-pdf'), ['format' => 'abc', 'pages' => ['<html>nope</html>']])
        ->assertStatus(422)
        ->assertJsonValidationErrors('pages.0');
});

it('rejects too many pages', function () {
    postJson(route('score.export-pdf'), [
        'format' => 'abc',
        'pages' => array_fill(0, 51, SAMPLE_SVG),
    ])->assertStatus(422)->assertJsonValidationErrors('pages');
});

it('returns a pdf download for a valid request', function () {
    $this->mock(SvgToPdfConverter::class)
        ->shouldReceive('convert')
        ->once()
        ->andReturn('%PDF-1.7 fake');

    postJson(route('score.export-pdf'), [
        'format' => 'abc',
        'title' => 'Glória Patri',
        'pages' => [SAMPLE_SVG, SAMPLE_SVG],
    ])
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertDownload('gloria-patri.cantores.hu.pdf');
});

it('returns a 502 when conversion fails', function () {
    $this->mock(SvgToPdfConverter::class)
        ->shouldReceive('convert')
        ->andThrow(new RuntimeException('boom'));

    postJson(route('score.export-pdf'), ['format' => 'abc', 'pages' => [SAMPLE_SVG]])
        ->assertStatus(502);
});

it('actually renders a pdf with rsvg-convert', function () {
    $binary = (string) config('services.rsvg.bin', 'rsvg-convert');
    if (! shell_exec('command -v '.escapeshellarg($binary))) {
        $this->markTestSkipped('rsvg-convert is not installed in this environment.');
    }

    $pdf = SvgToPdfConverter::fromConfig()->convert([SAMPLE_SVG, SAMPLE_SVG]);

    expect($pdf)->toStartWith('%PDF');
});

it('splits list-positioned music text into one tspan per glyph', function () {
    $svg = <<<'SVG'
    <?xml version="1.0"?>
    <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="200" height="60">
    <text x="8.3,38.9,63.6,88.3" y="41.0,53.0,50.0,47.0">&#xE050;&#xE0A4;&#xE0A4;&#xE0A4;</text>
    </svg>
    SVG;

    $result = SvgToPdfConverter::fromConfig()->expandPositionedText($svg);

    $doc = new DOMDocument;
    $doc->loadXML($result);
    $xpath = new DOMXPath($doc);
    $xpath->registerNamespace('svg', 'http://www.w3.org/2000/svg');

    $tspans = $xpath->query('//svg:text/svg:tspan');

    expect($tspans)->toHaveCount(4);
    expect($tspans->item(0)->getAttribute('x'))->toBe('8.3');
    expect($tspans->item(0)->getAttribute('y'))->toBe('41.0');
    expect($tspans->item(3)->getAttribute('x'))->toBe('88.3');
    expect($tspans->item(3)->getAttribute('y'))->toBe('47.0');
    expect($xpath->query('//svg:text')->item(0)->hasAttribute('x'))->toBeFalse();
});

it('leaves single-positioned and lyric text untouched', function () {
    $svg = <<<'SVG'
    <?xml version="1.0"?>
    <svg xmlns="http://www.w3.org/2000/svg" width="200" height="60">
    <text class="f01" x="37.0" y="25.0">privát</text>
    </svg>
    SVG;

    $result = SvgToPdfConverter::fromConfig()->expandPositionedText($svg);

    $doc = new DOMDocument;
    $doc->loadXML($result);
    $xpath = new DOMXPath($doc);
    $xpath->registerNamespace('svg', 'http://www.w3.org/2000/svg');

    expect($xpath->query('//svg:tspan'))->toHaveCount(0);
    expect($xpath->query('//svg:text')->item(0)->getAttribute('x'))->toBe('37.0');
    expect($xpath->query('//svg:text')->item(0)->textContent)->toBe('privát');
});

it('passes unparseable svg through to the renderer untouched', function () {
    $svg = '<svg xmlns="http://www.w3.org/2000/svg"><text x="1,2">oops</svg>';

    expect(SvgToPdfConverter::fromConfig()->expandPositionedText($svg))->toBe($svg);
});

it('resolves the abc2svg music font so notation renders in exported PDFs', function () {
    if (! shell_exec('command -v fc-match')) {
        $this->markTestSkipped('fontconfig (fc-match) is not installed in this environment.');
    }

    if (! shell_exec('fc-list | grep -i abc2svg')) {
        $this->markTestSkipped('The abc2svg music font is not installed in this environment (it ships in the Docker images).');
    }

    $resolved = trim((string) shell_exec("fc-match -f '%{family}' music"));

    expect($resolved)->toBe('abc2svg');
})->group('fonts');

it('sizes the page from the viewBox so the preview zoom does not enlarge the print', function () {
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 -12 643 300" width="772" height="374"><rect width="643" height="300"/></svg>';

    $result = SvgToPdfConverter::fromConfig()->normalizePhysicalSize($svg);

    $doc = new DOMDocument;
    $doc->loadXML($result);

    expect($doc->documentElement->getAttribute('width'))->toBe('170.1271mm');
    expect($doc->documentElement->getAttribute('height'))->toBe('79.375mm');
    expect($doc->documentElement->getAttribute('viewBox'))->toBe('0 -12 643 300');
});

it('sizes a page that states its width in percent from the viewBox too', function () {
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 480 240" width="100%"><rect width="480" height="240"/></svg>';

    $doc = new DOMDocument;
    $doc->loadXML(SvgToPdfConverter::fromConfig()->normalizePhysicalSize($svg));

    expect($doc->documentElement->getAttribute('width'))->toBe('127mm');
    expect($doc->documentElement->getAttribute('height'))->toBe('63.5mm');
});

it('keeps a size that is already given in a physical unit', function () {
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 643 300" width="170mm" height="79mm"><rect width="643" height="300"/></svg>';

    expect(SvgToPdfConverter::fromConfig()->normalizePhysicalSize($svg))->toBe($svg);
});

it('leaves a page without a usable viewBox untouched', function () {
    $noViewBox = '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="60"><rect width="200" height="60"/></svg>';
    $emptyViewBox = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 0 0" width="200" height="60"><rect width="200" height="60"/></svg>';
    $converter = SvgToPdfConverter::fromConfig();

    expect($converter->normalizePhysicalSize($noViewBox))->toBe($noViewBox);
    expect($converter->normalizePhysicalSize($emptyViewBox))->toBe($emptyViewBox);
    expect($converter->normalizePhysicalSize('<svg><rect></svg>'))->toBe('<svg><rect></svg>');
});

it('renders a 170 mm wide score as a 170 mm wide pdf page', function () {
    $binary = (string) config('services.rsvg.bin', 'rsvg-convert');
    if (! shell_exec('command -v '.escapeshellarg($binary))) {
        $this->markTestSkipped('rsvg-convert is not installed in this environment.');
    }

    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 643 300" width="772" height="360"><rect width="643" height="300" fill="none" stroke="#000"/></svg>';

    $pdf = SvgToPdfConverter::fromConfig()->convert([$svg]);

    [$widthMm, $heightMm] = pdfPageSizeInMm($pdf);

    expect($widthMm)->toBeGreaterThan(169.5)->toBeLessThan(170.5);
    expect($heightMm)->toBeGreaterThan(79.0)->toBeLessThan(79.8);
});

it('stamps a published score’s credit into the exported pdf', function () {
    $score = \App\Models\Score::factory()->create(['title' => 'Adoro Te']);
    \App\Models\ScorePublication::factory()->of($score)->approved()->create([
        'license' => \App\Enums\ScoreLicense::CcBySa,
    ]);

    $captured = null;

    $this->mock(\App\Services\SvgToPdfConverter::class, function ($mock) use (&$captured) {
        $mock->shouldReceive('convert')
            ->once()
            ->andReturnUsing(function (array $svgs, ?string $credit) use (&$captured) {
                $captured = $credit;

                return '%PDF-1.4 fake';
            });
    });

    $this->post(route('score.export-pdf'), [
        'format' => 'abc',
        'title' => 'Adoro Te',
        'score_id' => $score->id,
        'pages' => ['<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100"></svg>'],
    ])->assertOk();

    expect($captured)->toContain('CC BY-SA 4.0')
        ->and($captured)->toContain('Adoro Te');
});

it('does not stamp a credit on an unpublished score', function () {
    $score = \App\Models\Score::factory()->create();
    $captured = 'unset';

    $this->mock(\App\Services\SvgToPdfConverter::class, function ($mock) use (&$captured) {
        $mock->shouldReceive('convert')
            ->once()
            ->andReturnUsing(function (array $svgs, ?string $credit) use (&$captured) {
                $captured = $credit;

                return '%PDF-1.4 fake';
            });
    });

    $this->post(route('score.export-pdf'), [
        'format' => 'abc',
        'score_id' => $score->id,
        'pages' => ['<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100"></svg>'],
    ])->assertOk();

    expect($captured)->toBeNull();
});

it('puts the credit text into the svg it stamps', function () {
    $converter = app(\App\Services\SvgToPdfConverter::class);

    $stamped = $converter->stampCredit(
        '<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100"><g/></svg>',
        'Adoro Te · CC BY-SA 4.0',
    );

    expect($stamped)->toContain('Adoro Te')
        ->and($stamped)->toContain('CC BY-SA 4.0')
        ->and($stamped)->toEndWith('</svg>');

    expect($converter->stampCredit('<svg></svg>', null))->toBe('<svg></svg>');
});
