<?php

use App\Services\PdfPageVectorizer;

/**
 * The guards around poppler's `pdftocairo`, driven without poppler: the binary
 * is a shell stub written into a scratch directory, so the suite needs nothing
 * installed. In `pdftocairo -svg -f N -l N in.pdf out.svg` the stub sees the
 * input path as $6 and the output path as $7.
 */
/** @var list<string> */
$GLOBALS['__pdfvector_stub_dirs'] = [];

afterEach(function () {
    foreach ($GLOBALS['__pdfvector_stub_dirs'] as $dir) {
        foreach (glob($dir.'/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($dir);
    }
    $GLOBALS['__pdfvector_stub_dirs'] = [];
});

function stubVectorizer(string $script): PdfPageVectorizer
{
    $dir = sys_get_temp_dir().'/pdfvector-stub-'.bin2hex(random_bytes(4));
    mkdir($dir, 0700);
    $GLOBALS['__pdfvector_stub_dirs'][] = $dir;

    $bin = $dir.'/pdftocairo';
    file_put_contents($bin, "#!/bin/sh\n".$script."\n");
    chmod($bin, 0755);

    return new PdfPageVectorizer($bin, 10);
}

function leftoverWorkDirs(): array
{
    return glob(sys_get_temp_dir().'/pdfvector-'.str_repeat('[0-9a-f]', 16)) ?: [];
}

it('rejects input that is not a PDF before running anything', function () {
    $vectorizer = stubVectorizer('cp /dev/null "$7"');

    expect(fn () => $vectorizer->vectorizePage('not a pdf', 1))
        ->toThrow(RuntimeException::class, 'not a PDF');

    expect(leftoverWorkDirs())->toBe([]);
});

it('rejects a page number below one', function () {
    $vectorizer = stubVectorizer('cp /dev/null "$7"');

    expect(fn () => $vectorizer->vectorizePage('%PDF-1.7', 0))
        ->toThrow(RuntimeException::class);
});

it('rejects output that is not an SVG document', function () {
    $vectorizer = stubVectorizer('printf "not svg" > "$7"');

    expect(fn () => $vectorizer->vectorizePage('%PDF-1.7 real enough', 1))
        ->toThrow(RuntimeException::class, 'not an SVG');

    expect(leftoverWorkDirs())->toBe([]);
});

it('rejects output that is not well-formed xml', function () {
    $vectorizer = stubVectorizer('printf "<svg><g></svg>" > "$7"');

    expect(fn () => $vectorizer->vectorizePage('%PDF-1.7', 1))
        ->toThrow(RuntimeException::class);
});

it('rejects a poppler that fails', function () {
    $vectorizer = stubVectorizer('echo "boom" >&2; exit 1');

    expect(fn () => $vectorizer->vectorizePage('%PDF-1.7', 1))
        ->toThrow(RuntimeException::class, 'vectorisation failed');

    expect(leftoverWorkDirs())->toBe([]);
});

it('returns the svg bytes when poppler writes a valid document', function () {
    $vectorizer = stubVectorizer('printf \'<svg xmlns="http://www.w3.org/2000/svg"><g/></svg>\' > "$7"');

    $svg = $vectorizer->vectorizePage('%PDF-1.7', 1);

    expect($svg)->toContain('<svg')
        ->and($svg)->toContain('<g/>');

    expect(leftoverWorkDirs())->toBe([]);
});
