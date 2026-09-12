<?php

use function Pest\Laravel\get;

/**
 * The Alpine component each split bundle registers, and the entry point that
 * registers it.
 *
 * @return array<string, string>
 */
function pageBundleEntries(): array
{
    return [
        'scoreEditor' => 'resources/js/score-editor.js',
        'bookletEditor' => 'resources/js/booklet-editor.js',
        'bookletReader' => 'resources/js/booklet-reader.js',
        'aretinoMiniEditor' => 'resources/js/aretino-mini-editor.js',
        'abcMiniEditor' => 'resources/js/abc-mini-editor.js',
    ];
}

/**
 * The built file an entry point resolves to, as the head partial will name it.
 */
function builtBundleFile(string $entry): string
{
    $manifest = json_decode((string) file_get_contents(public_path('build/manifest.json')), true);

    expect($manifest)->toHaveKey($entry);

    return $manifest[$entry]['file'];
}

it('loads only the shared bundle on a page that draws no music', function () {
    $response = get(route('about'))->assertSuccessful();

    foreach (pageBundleEntries() as $entry) {
        $response->assertDontSee(builtBundleFile($entry), escape: false);
    }

    $response->assertSee(builtBundleFile('resources/js/app.js'), escape: false);
});

it('loads the aretino bundle on the aretino guide and nothing else', function () {
    $response = get(route('aretino.guide'))->assertSuccessful();

    $response->assertSee(builtBundleFile('resources/js/aretino-mini-editor.js'), escape: false);
    $response->assertDontSee(builtBundleFile('resources/js/abc-mini-editor.js'), escape: false);
    $response->assertDontSee(builtBundleFile('resources/js/score-editor.js'), escape: false);
});

it('loads the abc bundle on the abc guide and nothing else', function () {
    $response = get(route('abc.guide'))->assertSuccessful();

    $response->assertSee(builtBundleFile('resources/js/abc-mini-editor.js'), escape: false);
    $response->assertDontSee(builtBundleFile('resources/js/aretino-mini-editor.js'), escape: false);
    $response->assertDontSee(builtBundleFile('resources/js/score-editor.js'), escape: false);
});

/*
 * A view that mounts one of these Alpine components and forgets the push would
 * break only in the browser, and only on that one page — so the pairing is
 * checked over every Blade file rather than page by page.
 */
it('pushes its own bundle from every view that mounts a split Alpine component', function () {
    $entries = pageBundleEntries();

    $views = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(resource_path('views'), FilesystemIterator::SKIP_DOTS)
    );

    $mounted = [];

    foreach ($views as $view) {
        if (! str_ends_with($view->getFilename(), '.blade.php')) {
            continue;
        }

        $blade = (string) file_get_contents($view->getPathname());
        $relative = str_replace(resource_path('views').'/', '', $view->getPathname());

        foreach ($entries as $component => $entry) {
            if (! str_contains($blade, 'x-data="'.$component)) {
                continue;
            }

            $mounted[$component] = true;

            expect(str_contains($blade, "@push('page-bundles')\n{$entry}\n@endpush"))
                ->toBeTrue("{$relative} mounts {$component} without pushing {$entry}.");
        }
    }

    expect(array_keys($mounted))->toEqualCanonicalizing(array_keys($entries));
});
