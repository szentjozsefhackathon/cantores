<?php

use App\Support\BookletSettingFields;

/**
 * A selectable face lives in four places at once — the stylesheet the browser
 * paints from, the base64 table the SVG exporter inlines, the woff2 files behind
 * both, and a TTF installed system-wide for rsvg-convert, which ignores embedded
 *
 * @font-face entirely. Miss one and the font is offered but comes out as a
 * substitute somewhere down the line, which is only ever noticed in a printed
 * booklet.
 */
it('backs every offered face with a stylesheet, an embeddable woff2 and a system TTF', function () {
    $css = file_get_contents(resource_path('css/app.css'));
    $svgFonts = file_get_contents(resource_path('js/svg-fonts.js'));
    $lyricFaces = glob(resource_path('fonts/lyric/*.ttf'));

    foreach (BookletSettingFields::fontOptions() as $family) {
        expect($css)->toContain("font-family: '".$family."';");

        preg_match("/'".preg_quote($family, '/')."': \[(.*?)\],/s", $svgFonts, $block);

        expect($block)->not->toBeEmpty("{$family} is missing from svg-fonts.js");

        preg_match_all("/url: '([^']+)'/", $block[1], $urls);

        expect($urls[1])->not->toBeEmpty();

        foreach ($urls[1] as $url) {
            expect(public_path($url))->toBeReadableFile();
        }

        $stem = str_replace(' ', '', $family);

        expect(array_filter($lyricFaces, fn (string $path): bool => str_starts_with(basename($path), $stem)))
            ->not->toBeEmpty("{$family} has no TTF for the PDF exporter");
    }
});

it('offers the two serif faces added for booklets', function () {
    expect(BookletSettingFields::fontOptions())
        ->toContain('Alegreya')
        ->toContain('Merriweather')
        ->and(BookletSettingFields::sanitize('chordpro', ['chordproFontFamily' => 'Merriweather']))
        ->toBe(['chordproFontFamily' => "'Merriweather'"]);
});

/**
 * The score editor's toolbars spell their font lists out by hand, once per
 * format and once per view, so the only thing keeping them level with the
 * booklet's own list is a test. Lora is the yardstick: it appears in the
 * web-font selects and nowhere else, unlike Garamond, which the ChordPro
 * toolbar also names as a system face.
 */
it('offers the same faces in every score toolbar', function () {
    $views = [
        'livewire/pages/score-editor.blade.php',
        'livewire/pages/score-view.blade.php',
        'livewire/pages/public-score-view.blade.php',
    ];

    foreach ($views as $view) {
        $markup = file_get_contents(resource_path('views/'.$view));
        $selects = substr_count($markup, '>Lora</flux:select.option>');

        expect($selects)->toBeGreaterThan(0);

        foreach (BookletSettingFields::fontOptions() as $family) {
            expect(substr_count($markup, '>'.$family.'</flux:select.option>'))
                ->toBe($selects, "{$family} is missing from a font select in {$view}");
        }
    }
});
