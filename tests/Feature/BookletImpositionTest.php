<?php

use App\Enums\BookletImposition;
use App\Enums\BookletOrientation;
use App\Enums\BookletPageSize;
use App\Livewire\Pages\BookletEditor;
use App\Models\Booklet;
use App\Models\User;
use App\Services\BookletImposer;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;

/**
 * The imposed exports, end to end: what the browser asks for, what the printer
 * is handed, and what the editor offers to ask for.
 *
 * The arithmetic itself is checked in tests/Unit/BookletImposerTest.php; this is
 * about the endpoint honouring what was chosen, refusing what cannot be done,
 * and the last page of the chain — a real PDF whose sheets measure A4.
 */

/** An A5 page as the browser composes it, numbered so the order can be read back. */
function impositionPage(int $number): string
{
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 559.3700787401574 793.7007874015748" '
        .'width="559.3700787401574" height="793.7007874015748">'
        .'<text x="20" y="30">page '.$number.'</text></svg>';
}

/**
 * The SVGs the converter was handed for a booklet of $pages pages printed this
 * way.
 *
 * @return list<string>
 */
function exportedSheets(Booklet $booklet, int $pages, ?string $imposition): array
{
    $seen = [];
    fakeConverter(function (array $svgs) use (&$seen): void {
        $seen = $svgs;
    });

    $body = ['pages' => array_map(fn (int $n): string => impositionPage($n), range(1, $pages))];

    if ($imposition !== null) {
        $body['imposition'] = $imposition;
    }

    postJson(route('booklets.export-pdf', ['booklet' => $booklet]), $body)->assertOk();

    return $seen;
}

it('sends the pages one to a sheet when nothing is chosen', function () {
    $user = User::factory()->create();
    $booklet = Booklet::factory()->create(['user_id' => $user->id, 'page_size' => BookletPageSize::A5]);

    actingAs($user);

    $sheets = exportedSheets($booklet, 4, null);

    expect($sheets)->toHaveCount(4)
        ->and($sheets[0])->toContain('page 1')
        ->and($sheets[0])->not->toContain('page 2');
});

it('lays an A5 booklet out two to an A4 sheet on request', function () {
    $user = User::factory()->create();
    $booklet = Booklet::factory()->create(['user_id' => $user->id, 'page_size' => BookletPageSize::A5]);

    actingAs($user);

    $sheets = exportedSheets($booklet, 4, BookletImposition::TwoUp->value);

    expect($sheets)->toHaveCount(2)
        ->and($sheets[0])->toContain('page 1')
        ->and($sheets[0])->toContain('page 2')
        ->and($sheets[0])->toContain('width="297mm"');
});

it('shuffles an A5 booklet into folding order on request', function () {
    $user = User::factory()->create();
    $booklet = Booklet::factory()->create(['user_id' => $user->id, 'page_size' => BookletPageSize::A5]);

    actingAs($user);

    $sheets = exportedSheets($booklet, 4, BookletImposition::Booklet->value);

    expect($sheets)->toHaveCount(2);

    preg_match_all('/page (\d)/', $sheets[0], $first);

    expect($first[1])->toBe(['4', '1']);
});

// The pages of an A4 booklet are already the size of the paper. The editor greys
// those two items out, so a request for one is a request the editor cannot have
// made — refused rather than quietly answered with a full-page PDF.
it('refuses to impose a booklet already the size of the paper', function () {
    $user = User::factory()->create();
    $booklet = Booklet::factory()->create(['user_id' => $user->id, 'page_size' => BookletPageSize::A4]);

    fakeConverter();
    actingAs($user);

    postJson(route('booklets.export-pdf', ['booklet' => $booklet]), [
        'pages' => [impositionPage(1)],
        'imposition' => BookletImposition::Booklet->value,
    ])->assertJsonValidationErrors('imposition');
});

it('rejects an imposition it has never heard of', function () {
    $user = User::factory()->create();
    $booklet = Booklet::factory()->create(['user_id' => $user->id, 'page_size' => BookletPageSize::A5]);

    fakeConverter();
    actingAs($user);

    postJson(route('booklets.export-pdf', ['booklet' => $booklet]), [
        'pages' => [impositionPage(1)],
        'imposition' => 'four_up',
    ])->assertJsonValidationErrors('imposition');
});

// An imposed PDF is not the file anybody wants to read on screen — the pages in
// it are shuffled and doubled up — so its name says which one it is. The paper
// the pages were engraved for is named too, in every export: several sizes of
// the same booklet end up in one downloads folder.
it('names an imposed file for what it is', function () {
    $user = User::factory()->create();
    $booklet = Booklet::factory()->create([
        'user_id' => $user->id,
        'title' => 'Adventi füzet',
        'page_size' => BookletPageSize::A5,
    ]);

    fakeConverter();
    actingAs($user);

    postJson(route('booklets.export-pdf', ['booklet' => $booklet]), [
        'pages' => [impositionPage(1)],
        'imposition' => BookletImposition::TwoUp->value,
    ])->assertHeader('Content-Disposition', 'attachment; filename="adventi-fuzet.a5.two-up.cantores.hu.pdf"');
});

// The menu is drawn once and greys itself out, rather than being drawn again
// each time the paper changes: what it asks is a question the browser can answer
// from what the server last told it.
it('draws one menu item per way of printing, gated on the paper', function () {
    $user = User::factory()->create();
    $booklet = Booklet::factory()->create(['user_id' => $user->id, 'page_size' => BookletPageSize::A5]);

    actingAs($user);

    Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->assertSeeHtml('exportPdf(\'full\')')
        ->assertSeeHtml('exportPdf(\'two_up\')')
        ->assertSeeHtml('exportPdf(\'booklet\')')
        ->assertSeeHtml('!imposes(\'two_up\')')
        ->assertSeeHtml('!imposes(\'booklet\')')
        ->assertSee(BookletImposition::TwoUp->label())
        ->assertSee(BookletImposition::Booklet->label());
});

it('offers the imposed exports only where the paper allows them', function () {
    $user = User::factory()->create();
    $booklet = Booklet::factory()->create(['user_id' => $user->id, 'page_size' => BookletPageSize::A5]);

    actingAs($user);

    Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->assertSet('pageSize', 'a5')
        ->tap(fn ($component) => expect($component->instance()->impositions)
            ->toBe(['full', 'two_up', 'booklet']))
        ->set('pageSize', 'a4')
        ->tap(fn ($component) => expect($component->instance()->impositions)
            ->toBe(['full']));
});

// The editor is told what the paper allows as it changes, so the menu greys its
// items without asking anything of the browser's own idea of paper sizes.
it('tells the browser what the paper allows when the page size changes', function () {
    $user = User::factory()->create();
    $booklet = Booklet::factory()->create(['user_id' => $user->id, 'page_size' => BookletPageSize::A5]);

    actingAs($user);

    Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->set('pageSize', 'a4')
        ->assertDispatched('booklet-updated', impositions: ['full'], pageSize: 'a4');
});

/**
 * The end of the chain, against the real converter rather than a stand-in: an
 * imposed A5 booklet really does come out as landscape A4 sheets.
 */
it('really produces a landscape A4 pdf sheet', function () {
    $binary = (string) config('services.rsvg.bin', 'rsvg-convert');

    if (exec('command -v '.escapeshellarg($binary)) === '') {
        $this->markTestSkipped('rsvg-convert is not installed here.');
    }

    $user = User::factory()->create();
    $booklet = Booklet::factory()->create(['user_id' => $user->id, 'page_size' => BookletPageSize::A5]);

    actingAs($user);

    $response = postJson(route('booklets.export-pdf', ['booklet' => $booklet]), [
        'pages' => [impositionPage(1), impositionPage(2), impositionPage(3), impositionPage(4)],
        'imposition' => BookletImposition::Booklet->value,
    ]);

    $response->assertOk();

    $pdf = $response->getContent();

    expect($pdf)->toStartWith('%PDF');

    // A4 landscape is 297 x 210 mm, i.e. 841.89 x 595.28 pt.
    [$width, $height] = pdfPageSize($pdf);

    expect($width / 72 * 25.4)->toBeGreaterThan(296.9)->toBeLessThan(297.1)
        ->and($height / 72 * 25.4)->toBeGreaterThan(209.9)->toBeLessThan(210.1);
});

/**
 * The claim the cantor cared about most, measured off the paper rather than
 * argued about: a page is placed on the sheet at exactly the size it was
 * engraved, so the margins printed are the margins that were set.
 *
 * The page carries a frame ruled 10 mm in from its own edges. Two A5 pages are
 * 296 mm across, so on a 297 mm sheet the left page starts half a millimetre in
 * and its frame is at 10.5 mm; the right page ends half a millimetre from the
 * other edge and its frame at 286.5 mm. Rendered at ten pixels to the
 * millimetre, that is column 105 and column 2865, give or take the width of the
 * ruled line itself.
 */
it('prints the margins that were set, to the millimetre', function () {
    $binary = (string) config('services.rsvg.bin', 'rsvg-convert');

    if (exec('command -v '.escapeshellarg($binary)) === '') {
        $this->markTestSkipped('rsvg-convert is not installed here.');
    }

    $sheets = (new BookletImposer)->impose(
        [framedPage(), framedPage()],
        Booklet::factory()->make(['page_size' => BookletPageSize::A5, 'orientation' => BookletOrientation::Portrait]),
        BookletImposition::TwoUp,
    );

    [$first, $last] = framedColumnsOf($sheets[0], $binary);

    expect($first)->toBeGreaterThan(102)->toBeLessThan(108)
        ->and($last)->toBeGreaterThan(2862)->toBeLessThan(2868);
});

/** An A5 page with a frame ruled exactly 10 mm in from each edge. */
function framedPage(): string
{
    $inset = 10 / (25.4 / 96);
    $width = 148 / (25.4 / 96);
    $height = 210 / (25.4 / 96);

    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.$width.' '.$height.'" '
        .'width="'.$width.'" height="'.$height.'">'
        .'<rect x="0" y="0" width="'.$width.'" height="'.$height.'" fill="#ffffff"/>'
        .'<rect x="'.$inset.'" y="'.$inset.'" width="'.($width - 2 * $inset).'" height="'.($height - 2 * $inset).'" '
        .'fill="none" stroke="#ff0000" stroke-width="1"/>'
        .'</svg>';
}

/**
 * The first and last column carrying red ink, once the sheet is rendered at ten
 * pixels to the millimetre.
 *
 * @return array{0: int, 1: int}
 */
function framedColumnsOf(string $sheet, string $binary): array
{
    $directory = sys_get_temp_dir().'/impose-'.bin2hex(random_bytes(6));
    mkdir($directory, 0700);

    try {
        file_put_contents($directory.'/sheet.svg', $sheet);

        exec(implode(' ', array_map('escapeshellarg', [
            $binary, '--format=png', '--dpi-x=254', '--dpi-y=254',
            '--output', $directory.'/sheet.png', $directory.'/sheet.svg',
        ])));

        $image = imagecreatefrompng($directory.'/sheet.png');
        $width = imagesx($image);
        $height = imagesy($image);
        $columns = [];

        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $height; $y += 7) {
                $colour = imagecolorat($image, $x, $y);

                if ((($colour >> 16) & 255) > 150 && (($colour >> 8) & 255) < 100 && ($colour & 255) < 100) {
                    $columns[] = $x;

                    break;
                }
            }
        }

        expect($columns)->not->toBeEmpty();

        return [min($columns), max($columns)];
    } finally {
        foreach (glob($directory.'/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($directory);
    }
}
