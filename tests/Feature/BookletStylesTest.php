<?php

use App\Support\BookletSettingFields;
use App\Support\BookletStyles;
use Illuminate\Support\Facades\App;

/**
 * The table a booklet's whole typography is read out of.
 *
 * Two promises hold it together: every style names a face the exporter can
 * embed, and no two styles name the same face — which is what lets the booklet's
 * own `text_font` say which style it is in, with no column to go stale.
 */
it('names a face the exporter can embed, and a different one per style', function () {
    $fonts = [];

    foreach (BookletStyles::keys() as $style) {
        $font = BookletStyles::defaults($style)['text_font'];

        expect(BookletSettingFields::fontOptions())->toContain($font);

        $fonts[] = $font;
    }

    expect($fonts)->toHaveCount(count(array_unique($fonts)));
});

it('reads the style back off the face, and falls back to the default', function () {
    foreach (BookletStyles::keys() as $style) {
        expect(BookletStyles::forFont(BookletStyles::defaults($style)['text_font']))->toBe($style);
    }

    // The face the score editor's selects emit, quotes and all.
    expect(BookletStyles::forFont("'EB Garamond'"))->toBe('graduale');

    // A projector face no style claims — a booklet set in one before styles
    // existed keeps it, and is shown the nearest style.
    expect(BookletStyles::forFont('Inter'))->toBe(BookletStyles::DEFAULT)
        ->and(BookletStyles::forFont('Barlow Condensed'))->toBe(BookletStyles::DEFAULT)
        ->and(BookletStyles::forFont(''))->toBe(BookletStyles::DEFAULT);
});

// The whole promise of the feature: a style is everything typographic and
// nothing else. A style that reflowed A5 into A4 would be useless for the one
// thing styles are for, which is seeing which one suits this booklet.
it('owns exactly the seven typographic columns and nothing physical', function () {
    foreach (BookletStyles::keys() as $style) {
        expect(array_keys(BookletStyles::defaults($style)))->toBe([
            'text_font',
            'lyric_size_pt',
            'staff_height_mm',
            'heading_scale',
            'abc_staff_sep',
            'abc_lyric_first_skip',
            'abc_lyric_skip',
        ]);
    }
});

it('carries the gaps of the engines that keep no setting for them', function () {
    foreach (BookletStyles::keys() as $style) {
        expect(array_keys(BookletStyles::engineSpacing($style)))->toBe([
            'minSpaceBelowStaff',
            'aretinoLyricDistance',
            'aretinoLyricMinStaffDistance',
        ]);
    }

    // Nobody has judged these by eye yet, so all three styles keep the number
    // their engine already draws at; the table is where they will be judged.
    expect(BookletStyles::engineSpacing('graduale'))
        ->toBe(BookletStyles::engineSpacing('hymnal'));
});

// Named after the book each one is rather than after its face: a cantor choosing
// between Énekeskönyv and Graduále is choosing what the booklet should feel
// like, not answering a question about typefaces.
it('offers every style to the picker, named in the reader\'s own language', function () {
    expect(BookletStyles::all())->toBe([
        ['value' => 'hymnal', 'label' => __('Hymnal')],
        ['value' => 'modern', 'label' => __('Modern')],
        ['value' => 'graduale', 'label' => __('Graduale')],
    ]);

    App::setLocale('hu');

    expect(array_column(BookletStyles::all(), 'label'))
        ->toBe(['Énekeskönyv', 'Modern', 'Graduále']);
});

// What the reader's screen may swap. A face and the gaps that face needs are one
// decision, so all three move together — anything less puts one face's lyrics
// over another face's gaps on a phone.
it('hands the reader the face and both spacings of every style', function () {
    $typographies = BookletStyles::typographies();

    expect(array_keys($typographies))->toBe(BookletStyles::keys());

    foreach (BookletStyles::keys() as $style) {
        $columns = BookletStyles::defaults($style);

        expect($typographies[$style])->toBe([
            'textFont' => $columns['text_font'],
            'abcLyricFirstSkip' => $columns['abc_lyric_first_skip'],
            'abcLyricSkip' => $columns['abc_lyric_skip'],
        ]);
    }
});
