<?php

use App\Services\SvgToPdfConverter;

use function Pest\Laravel\postJson;

const SAMPLE_SVG = '<svg xmlns="http://www.w3.org/2000/svg" width="100" height="40"><text x="5" y="20">Glória</text></svg>';

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
