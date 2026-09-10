<?php

use App\Enums\BookletImposition;
use App\Enums\BookletOrientation;
use App\Enums\BookletPageSize;
use App\Models\Booklet;
use App\Services\BookletImposer;
use App\Support\ImpositionLayout;

/**
 * The imposition arithmetic, checked without a browser, a database or a printer.
 *
 * Two claims are worth more than the rest and are made over and over below: the
 * pages come out in the order a folded stack reads in, and every page lands on
 * the paper at exactly the size it was engraved — the whole point of imposing an
 * A5 booklet onto A4 is to print the A5 pages that were laid out, margins and
 * all, rather than slightly smaller ones.
 */

/** A booklet of a given paper, made rather than saved: none of this touches the database. */
function bookletOf(BookletPageSize $size, BookletOrientation $orientation = BookletOrientation::Portrait): Booklet
{
    return new Booklet([
        'page_size' => $size,
        'orientation' => $orientation,
        'margin_mm' => 12,
    ]);
}

/** A page as the browser composes it: a viewBox in pixels at 96 dpi, and an id to collide with. */
function pageSvg(int $number, float $widthMm = 148.0, float $heightMm = 210.0): string
{
    $width = $widthMm / (25.4 / 96);
    $height = $heightMm / (25.4 / 96);

    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.$width.' '.$height.'" '
        .'width="'.$width.'" height="'.$height.'">'
        .'<defs><path id="glyph0-1" d="M0 0h1v1h-1z"/></defs>'
        .'<use href="#glyph0-1" x="10" y="10"/>'
        .'<text x="20" y="30">page '.$number.'</text>'
        .'</svg>';
}

/**
 * The pages on one sheet, in the order they were placed, read back off the
 * nested documents.
 *
 * @return list<string>
 */
function pagesOnSheet(string $sheet): array
{
    preg_match_all('/page (\d+)/', $sheet, $found);

    return $found[1];
}

/**
 * Where each nested page sits on the sheet, and how big it is.
 *
 * @return list<array{x: float, y: float, width: float, height: float}>
 */
function slotsOnSheet(string $sheet): array
{
    $doc = new DOMDocument;
    $doc->loadXML($sheet);

    $slots = [];

    foreach ($doc->documentElement->childNodes as $child) {
        if ($child instanceof DOMElement && $child->localName === 'svg') {
            $slots[] = [
                'x' => (float) $child->getAttribute('x'),
                'y' => (float) $child->getAttribute('y'),
                'width' => (float) $child->getAttribute('width'),
                'height' => (float) $child->getAttribute('height'),
            ];
        }
    }

    return $slots;
}

it('leaves an A4 booklet alone: there is nothing to impose', function () {
    expect(ImpositionLayout::for(bookletOf(BookletPageSize::A4)))->toBeNull()
        ->and(ImpositionLayout::for(bookletOf(BookletPageSize::A4, BookletOrientation::Landscape)))->toBeNull();
});

it('pairs two A5 pages side by side on a landscape A4', function () {
    $layout = ImpositionLayout::for(bookletOf(BookletPageSize::A5));

    expect($layout->sheetWidthMm)->toBe(297.0)
        ->and($layout->sheetHeightMm)->toBe(210.0)
        ->and($layout->pairsSideBySide)->toBeTrue()
        ->and($layout->copies)->toBe(1);
});

// Four A6 pages fill a portrait A4, but they are not four different pages: the
// leaf is repeated so that one cut across the middle gives two booklets rather
// than one and a wasted half.
it('puts the A6 leaf twice on a portrait A4', function () {
    $layout = ImpositionLayout::for(bookletOf(BookletPageSize::A6));

    expect($layout->sheetWidthMm)->toBe(210.0)
        ->and($layout->sheetHeightMm)->toBe(297.0)
        ->and($layout->pairsSideBySide)->toBeTrue()
        ->and($layout->copies)->toBe(2);
});

// A landscape page folds along a horizontal line, so its leaf is one page above
// the other and the repeats stand side by side.
it('stacks a landscape page and repeats the leaf across the sheet', function () {
    $a5 = ImpositionLayout::for(bookletOf(BookletPageSize::A5, BookletOrientation::Landscape));
    $a6 = ImpositionLayout::for(bookletOf(BookletPageSize::A6, BookletOrientation::Landscape));

    expect($a5->pairsSideBySide)->toBeFalse()
        ->and($a5->sheetWidthMm)->toBe(210.0)
        ->and($a5->copies)->toBe(1)
        ->and($a6->pairsSideBySide)->toBeFalse()
        ->and($a6->sheetWidthMm)->toBe(297.0)
        ->and($a6->copies)->toBe(2);
});

it('reads two pages to a leaf in order for an n-up print', function () {
    expect(BookletImposer::readingLeaves(4))->toBe([[1, 2], [3, 4]])
        ->and(BookletImposer::readingLeaves(5))->toBe([[1, 2], [3, 4], [5, null]])
        ->and(BookletImposer::readingLeaves(1))->toBe([[1, null]])
        ->and(BookletImposer::readingLeaves(0))->toBe([]);
});

// The order a saddle-stitched booklet is printed in: the front of the first
// sheet is the back cover beside the front cover, its other side is page two
// beside the second to last, and so inwards.
it('shuffles the pages into the order a folded stack reads in', function () {
    expect(BookletImposer::bookletLeaves(4))->toBe([[4, 1], [2, 3]])
        ->and(BookletImposer::bookletLeaves(8))->toBe([[8, 1], [2, 7], [6, 3], [4, 5]]);
});

// A folded sheet is four pages whether or not anything is on them, so the count
// is rounded up and the blanks land at the back where they belong.
it('pads a booklet to a multiple of four with blanks at the back', function () {
    expect(BookletImposer::bookletLeaves(6))->toBe([[null, 1], [2, null], [6, 3], [4, 5]])
        ->and(BookletImposer::bookletLeaves(1))->toBe([[null, 1], [null, null]]);
});

it('imposes an A5 booklet two to a landscape A4 sheet', function () {
    $pages = [pageSvg(1), pageSvg(2), pageSvg(3), pageSvg(4)];

    $sheets = (new BookletImposer)->impose($pages, bookletOf(BookletPageSize::A5), BookletImposition::TwoUp);

    expect($sheets)->toHaveCount(2)
        ->and(pagesOnSheet($sheets[0]))->toBe(['1', '2'])
        ->and(pagesOnSheet($sheets[1]))->toBe(['3', '4'])
        ->and($sheets[0])->toContain('width="297mm"')
        ->and($sheets[0])->toContain('height="210mm"')
        ->and($sheets[0])->toContain('viewBox="0 0 297 210"');
});

it('orders an A5 booklet for folding', function () {
    $pages = [pageSvg(1), pageSvg(2), pageSvg(3), pageSvg(4)];

    $sheets = (new BookletImposer)->impose($pages, bookletOf(BookletPageSize::A5), BookletImposition::Booklet);

    expect($sheets)->toHaveCount(2)
        ->and(pagesOnSheet($sheets[0]))->toBe(['4', '1'])
        ->and(pagesOnSheet($sheets[1]))->toBe(['2', '3']);
});

// What the cantor asked for in so many words: 1 2 above 1 2 for an n-up A6, and
// 4 1 above 4 1 on the first sheet of a four page booklet.
it('repeats the leaf down a portrait A4 for A6 pages', function () {
    $pages = [pageSvg(1, 105, 148), pageSvg(2, 105, 148), pageSvg(3, 105, 148), pageSvg(4, 105, 148)];
    $booklet = bookletOf(BookletPageSize::A6);

    $nUp = (new BookletImposer)->impose($pages, $booklet, BookletImposition::TwoUp);
    $folded = (new BookletImposer)->impose($pages, $booklet, BookletImposition::Booklet);

    expect(pagesOnSheet($nUp[0]))->toBe(['1', '2', '1', '2'])
        ->and(pagesOnSheet($nUp[1]))->toBe(['3', '4', '3', '4'])
        ->and(pagesOnSheet($folded[0]))->toBe(['4', '1', '4', '1'])
        ->and(pagesOnSheet($folded[1]))->toBe(['2', '3', '2', '3']);
});

// The claim the whole feature rests on: the pages are placed, not resized. Two
// A5 pages are 296 mm across, so they sit half a millimetre in from each edge of
// a 297 mm sheet — and each is still exactly 148 by 210, margins untouched.
it('places every page at exactly the size it was engraved', function () {
    $sheets = (new BookletImposer)->impose(
        [pageSvg(1), pageSvg(2)],
        bookletOf(BookletPageSize::A5),
        BookletImposition::TwoUp,
    );

    expect(slotsOnSheet($sheets[0]))->toBe([
        ['x' => 0.5, 'y' => 0.0, 'width' => 148.0, 'height' => 210.0],
        ['x' => 148.5, 'y' => 0.0, 'width' => 148.0, 'height' => 210.0],
    ]);
});

it('fills a portrait A4 with four A6 pages edge to edge', function () {
    $sheets = (new BookletImposer)->impose(
        [pageSvg(1, 105, 148), pageSvg(2, 105, 148)],
        bookletOf(BookletPageSize::A6),
        BookletImposition::TwoUp,
    );

    expect(slotsOnSheet($sheets[0]))->toBe([
        ['x' => 0.0, 'y' => 0.5, 'width' => 105.0, 'height' => 148.0],
        ['x' => 105.0, 'y' => 0.5, 'width' => 105.0, 'height' => 148.0],
        ['x' => 0.0, 'y' => 148.5, 'width' => 105.0, 'height' => 148.0],
        ['x' => 105.0, 'y' => 148.5, 'width' => 105.0, 'height' => 148.0],
    ]);
});

// Two pages on a sheet are one XML document, and both name their glyphs the way
// the renderer that drew them does. Without a prefix per placement the second
// page's <use> draws the first page's glyph — which on an A6 sheet, where the
// same page is on it twice, is guaranteed.
it('keeps each placed page ids to itself', function () {
    $sheets = (new BookletImposer)->impose(
        [pageSvg(1, 105, 148), pageSvg(2, 105, 148)],
        bookletOf(BookletPageSize::A6),
        BookletImposition::TwoUp,
    );

    preg_match_all('/id="([^"]+)"/', $sheets[0], $ids);

    expect($ids[1])->toHaveCount(4)
        ->and(array_unique($ids[1]))->toHaveCount(4);

    foreach ($ids[1] as $id) {
        expect($sheets[0])->toContain('href="#'.$id.'"');
    }
});

it('hands the pages straight on when nothing is being imposed', function () {
    $pages = [pageSvg(1), pageSvg(2)];

    expect((new BookletImposer)->impose($pages, bookletOf(BookletPageSize::A5), BookletImposition::Full))
        ->toBe($pages);
});

// A page that cannot be parsed is a page that cannot be placed, and a sheet with
// music silently missing from it is worse than a download that says it failed.
it('refuses to build a sheet around a page it cannot parse', function () {
    (new BookletImposer)->impose(
        ['<svg xmlns="http://www.w3.org/2000/svg"><text>unclosed'],
        bookletOf(BookletPageSize::A5),
        BookletImposition::TwoUp,
    );
})->throws(RuntimeException::class);
