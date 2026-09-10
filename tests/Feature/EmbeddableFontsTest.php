<?php

use App\Support\BookletSettingFields;
use Illuminate\Support\Facades\Blade;

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
 * A face that has been taken out of the picker is still set on the scores and
 * booklets that chose it while it was there. Dropping it from the validator as
 * well would cost those their font on the next save, so the two lists are
 * deliberately different lengths.
 */
it('still accepts a retired face, and offers the current ones in order', function () {
    expect(BookletSettingFields::selectableFonts())
        ->toBe(['Alegreya', 'Merriweather', 'EB Garamond', 'Inter', 'Barlow Condensed'])
        ->and(BookletSettingFields::fontOptions())
        ->toContain('Lora')
        ->and(BookletSettingFields::sanitize('chordpro', ['chordproFontFamily' => 'Lora']))
        ->toBe(['chordproFontFamily' => "'Lora'"]);
});

/**
 * The toolbars used to spell their font lists out by hand, once per format and
 * once per view — twelve copies of one list, kept level only by a test. They now
 * share a component, so what is worth checking is that none of them has grown a
 * hand-written list again.
 */
it('picks every score toolbar font from the one shared list', function () {
    $views = [
        'livewire/pages/score-editor.blade.php',
        'livewire/pages/score-view.blade.php',
        'livewire/pages/public-score-view.blade.php',
    ];

    foreach ($views as $view) {
        $markup = file_get_contents(resource_path('views/'.$view));

        expect(substr_count($markup, '<x-lyric-font-select'))
            ->toBe(4, "{$view} should choose a face for each of the four formats");

        foreach (BookletSettingFields::fontOptions() as $family) {
            expect($markup)->not->toContain('>'.$family.'</flux:select.option>');
        }
    }
});

it('renders the shared select with the current faces, in order', function () {
    $markup = html_entity_decode(Blade::render('<x-lyric-font-select model="lyricFont" />'));

    expect($markup)->toContain("value=\"'Alegreya'\"")
        ->and($markup)->not->toContain('>Lora</option>');

    $positions = array_map(function (string $family) use ($markup): int {
        $at = strpos($markup, '>'.$family.'</option>');

        expect($at)->not->toBeFalse("{$family} is missing from the select");

        return (int) $at;
    }, BookletSettingFields::selectableFonts());

    $sorted = $positions;
    sort($sorted);

    expect($positions)->toBe($sorted);
});

it('writes the ABC face unquoted, because abc2svg quotes it itself', function () {
    expect(html_entity_decode(Blade::render('<x-lyric-font-select model="abcLyricFont" :quoted="false" />')))
        ->toContain('value="Alegreya"');
});
