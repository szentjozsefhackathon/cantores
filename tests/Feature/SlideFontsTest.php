<?php

/**
 * A slide is engraved once and never measured again.
 *
 * Every engine places lyrics at a measured width — abc2svg goes as far as
 * dropping a hyphen when the gap between two syllables comes out too small — and
 * a web font is only fetched once something asks to be painted in it. So a
 * renderer that engraves before the face has arrived measures against the
 * fallback and bakes the wrong numbers into an SVG nothing will redraw: on a
 * hard reload of the presenter, the first score came up with lyric hyphens
 * missing until a second render put them back.
 *
 * The guard is source-level because there is no browser here to load a face in.
 *
 * @return array{0: string, 1: string} the function's body, and the whole file
 */
function slideRendererBody(string $file, string $name): array
{
    $source = file_get_contents(resource_path('js/'.$file));

    expect($source)->toContain("export async function {$name}(");

    $start = strpos($source, "export async function {$name}(");
    $end = strpos($source, "\n}\n", $start);

    return [substr($source, $start, $end - $start), $source];
}

it('waits for the lyric face before engraving a slide', function (string $file, string $renderer, string $wait, string $engine) {
    [$body] = slideRendererBody($file, $renderer);

    expect($body)->toContain($wait)
        ->and(strpos($body, $wait))->toBeLessThan(strpos($body, $engine));
})->with([
    'abc' => ['score-editor-abc.js', 'renderAbcSlide', 'await ensureAbcFontsLoaded(settings);', 'engraveAbcLines('],
    'abc slides' => ['score-editor-abc.js', 'renderAbcSlides', 'await ensureAbcFontsLoaded(settings);', 'engraveAbcLines('],
    'gabc' => ['score-editor-gabc.js', 'renderGabcSlide', 'await ensureGabcFontsLoaded(settings);', 'renderGabcToSvgMarkup('],
    'gabc slides' => ['score-editor-gabc.js', 'renderGabcSlides', 'await ensureGabcFontsLoaded(settings);', 'renderGabcToSvgMarkup('],
    'aretino' => ['score-editor-aretino.js', 'renderAretinoSlide', 'await ensureFontsLoaded(', 'engraveAretinoSlide('],
    'aretino slides' => ['score-editor-aretino.js', 'renderAretinoSlides', 'await ensureFontsLoaded(', 'engraveAretinoSlide('],
    'chordpro' => ['score-editor-chordpro.js', 'renderChordproSlides', 'await ensureFontsLoaded(', 'chordproSlidePages('],
]);

/**
 * The dispatcher hands every engraved format back as a promise, so a caller that
 * forgot to await it would drop the wait along with the slide.
 */
it('awaits every engraved slide through the projection dispatcher', function () {
    $callers = [
        'score-editor-abc.js' => 'await renderAbcSlides(',
        'score-editor-gabc.js' => 'await renderGabcSlides(',
        'score-editor-aretino.js' => 'await renderAretinoSlides(',
    ];

    foreach ($callers as $file => $call) {
        expect(file_get_contents(resource_path('js/'.$file)))->toContain($call);
    }

    expect(file_get_contents(resource_path('js/projection-render.js')))
        ->toContain('export async function renderRatioPage(')
        ->toContain('export async function renderRatioPageSlides(');
});
